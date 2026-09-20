<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Models\DuesObligation;
use App\Models\Payment;
use App\Models\User;
use App\Support\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class SubmitPaymentProof
{
    /** @param array<string, mixed> $data */
    public function handle(User $actor, DuesObligation $obligation, array $data, UploadedFile $proof): Payment
    {
        if (! $actor->hasRole('homeowner') || $actor->homeowner?->id !== $obligation->homeowner_id) {
            throw new AuthorizationException;
        }

        if (! in_array($obligation->status, ['Pending', 'Partial', 'Overdue'], true)) {
            throw new AuthorizationException;
        }

        if (! $proof->isValid()
            || $proof->getSize() > 5 * 1024 * 1024
            || ! in_array($proof->getMimeType(), ['application/pdf', 'image/jpeg', 'image/png'], true)
        ) {
            throw ValidationException::withMessages(['proof' => __('The payment proof must be a valid PDF, JPEG, or PNG file no larger than 5 MB.')]);
        }

        $amount = Money::toMinor((string) $data['amount_paid']);
        $totalDue = Money::toMinor((string) $obligation->amount_due) + Money::toMinor((string) $obligation->penalty_amount);
        $remaining = max(0, $totalDue - Money::toMinor((string) $obligation->amount_paid));
        if ($amount <= 0 || $amount > $remaining) {
            throw ValidationException::withMessages(['amount_paid' => __('The submitted amount must be greater than zero and cannot exceed the outstanding balance.')]);
        }

        $extension = $proof->extension();
        $path = 'payment-proofs/'.now()->format('Y/m').'/'.Str::ulid().'.'.$extension;
        Storage::disk('local')->putFileAs(dirname($path), $proof, basename($path));

        try {
            return Payment::query()->create([
                'homeowner_id' => $obligation->homeowner_id,
                'dues_setting_id' => $obligation->dues_setting_id,
                'dues_obligation_id' => $obligation->id,
                'amount_paid' => Money::fromMinor($amount),
                'balance' => Money::fromMinor($remaining),
                'penalty' => '0.00',
                'payment_date' => $data['payment_date'],
                'covered_period' => $data['covered_period'],
                'payment_method' => $data['payment_method'],
                'status' => 'Pending Review',
                'notes' => $data['notes'] ?? null,
                'proof_path' => $path,
                'proof_original_name' => mb_substr($proof->getClientOriginalName(), 0, 255),
                'proof_mime_type' => $proof->getMimeType(),
                'proof_size' => $proof->getSize(),
                'review_status' => 'Pending',
                'recorded_by' => $actor->id,
            ]);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }
    }
}
