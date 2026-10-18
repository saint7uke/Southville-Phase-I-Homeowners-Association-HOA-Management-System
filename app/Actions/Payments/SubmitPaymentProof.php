<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Models\DuesObligation;
use App\Models\Homeowner;
use App\Models\Payment;
use App\Models\User;
use App\Services\PropertyDuesAccounts;
use App\Support\Money;
use App\Validation\PaymentValidator;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class SubmitPaymentProof
{
    public function __construct(
        private readonly PaymentValidator $validator,
        private readonly PropertyDuesAccounts $accounts,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, DuesObligation $obligation, array $data, UploadedFile $proof): Payment
    {
        $payer = $actor->homeowner?->loadMissing('user');
        $obligation->loadMissing('homeowner.user');
        if (! $actor->hasRole('homeowner') || ! $actor->can('submit_own_payment_proof') || $payer === null || ! $this->accounts->payerIsEligible($payer, $obligation->homeowner)) {
            throw new AuthorizationException;
        }

        $data['dues_obligation_id'] = $obligation->id;
        $data['payer_homeowner_id'] = $payer->id;
        $data['submission_token'] ??= (string) Str::uuid();
        $validated = $this->validator->validate($data, true);

        if ($existing = Payment::withTrashed()->where('submission_token', $validated['submission_token'])->first()) {
            return $this->idempotentResult($existing, $validated);
        }
        if (! $proof->isValid() || $proof->getSize() > 5 * 1024 * 1024
            || ! in_array($proof->getMimeType(), ['application/pdf', 'image/jpeg', 'image/png'], true)) {
            throw ValidationException::withMessages(['proof' => __('The payment proof must be a valid PDF, JPEG, or PNG file no larger than 5 MB.')]);
        }

        $path = 'payment-proofs/'.now()->format('Y/m').'/'.Str::ulid().'.'.$proof->extension();
        try {
            return DB::transaction(function () use ($validated, $actor, $payer, $proof, $path): Payment {
                $locked = DuesObligation::query()->lockForUpdate()->findOrFail($validated['dues_obligation_id']);
                if ($existing = Payment::withTrashed()->where('submission_token', $validated['submission_token'])->lockForUpdate()->first()) {
                    return $this->idempotentResult($existing, $validated);
                }
                $currentPayer = Homeowner::query()->with('user')->find($payer->id);
                $locked->load('homeowner.user');
                if ($currentPayer === null || ! $this->accounts->payerIsEligible($currentPayer, $locked->homeowner)) {
                    throw new AuthorizationException;
                }
                if (! in_array($locked->status, ['Pending', 'Partial', 'Overdue'], true)) {
                    throw ValidationException::withMessages(['dues_obligation_id' => __('This obligation is no longer open for payment.')]);
                }

                $pending = $locked->payments()->where('review_status', 'Pending')->lockForUpdate()->get()
                    ->sum(fn (Payment $payment): int => Money::toMinor((string) $payment->amount_paid));
                $remaining = Money::toMinor((string) $locked->amount_due)
                    + Money::toMinor((string) $locked->penalty_amount)
                    - Money::toMinor((string) $locked->amount_paid)
                    - $pending;
                $amount = Money::toMinor((string) $validated['amount_paid']);
                if ($amount > $remaining) {
                    throw ValidationException::withMessages(['amount_paid' => __('The submitted amount cannot exceed the unreserved outstanding balance.')]);
                }

                $stored = Storage::disk('local')->putFileAs(dirname($path), $proof, basename($path));
                if ($stored === false) {
                    throw ValidationException::withMessages(['proof' => __('The payment proof could not be stored. Please try again.')]);
                }

                return Payment::query()->create([
                    'homeowner_id' => $locked->homeowner_id,
                    'payer_homeowner_id' => $payer->id,
                    'dues_setting_id' => $locked->dues_setting_id,
                    'dues_obligation_id' => $locked->id,
                    'amount_paid' => Money::fromMinor($amount),
                    'balance' => Money::fromMinor($remaining),
                    'penalty' => '0.00',
                    'payment_date' => $validated['payment_date'],
                    'covered_period' => CarbonImmutable::create($locked->billing_year, $locked->billing_month)->format('F Y'),
                    'payment_method' => $validated['payment_method'],
                    'transaction_reference' => $validated['transaction_reference'],
                    'transaction_reference_key' => $validated['transaction_reference_key'],
                    'submission_token' => $validated['submission_token'],
                    'status' => 'Pending Review',
                    'notes' => $validated['notes'] ?? null,
                    'proof_path' => $path,
                    'proof_original_name' => mb_substr($proof->getClientOriginalName(), 0, 255),
                    'proof_mime_type' => $proof->getMimeType(),
                    'proof_size' => $proof->getSize(),
                    'review_status' => 'Pending',
                    'recorded_by' => $actor->id,
                ]);
            }, 3);
        } catch (QueryException $exception) {
            Storage::disk('local')->delete($path);
            if ($existing = Payment::withTrashed()->where('submission_token', $validated['submission_token'])->first()) {
                return $this->idempotentResult($existing, $validated);
            }
            if (Payment::withTrashed()->where('transaction_reference_key', $validated['transaction_reference_key'])->exists()) {
                throw ValidationException::withMessages(['transaction_reference' => __('This transaction reference has already been used.')]);
            }
            throw $exception;
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
    }

    /** @param array<string, mixed> $data */
    private function idempotentResult(Payment $payment, array $data): Payment
    {
        $matches = ! $payment->trashed()
            && $payment->proof_path !== null
            && $payment->dues_obligation_id === (int) $data['dues_obligation_id']
            && $payment->payer_homeowner_id === (int) $data['payer_homeowner_id']
            && Money::toMinor((string) $payment->amount_paid) === Money::toMinor((string) $data['amount_paid'])
            && $payment->payment_date?->toDateString() === CarbonImmutable::parse($data['payment_date'])->toDateString()
            && $payment->payment_method === $data['payment_method']
            && $payment->transaction_reference_key === $data['transaction_reference_key'];
        if (! $matches) {
            throw ValidationException::withMessages(['submission_token' => __('This submission token has already been used for a different payment.')]);
        }

        return $payment;
    }
}
