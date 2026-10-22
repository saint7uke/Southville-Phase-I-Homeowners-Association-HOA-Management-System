<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\DuesObligation;
use App\Models\Payment;
use App\Models\User;
use App\Support\PaymentReference;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Fixtures for reviewing proofs received before the cash-only policy. Never a submission bypass. */
final class LegacyPaymentProof
{
    public static function create(User $payer, DuesObligation $due, array $data, UploadedFile $file): Payment
    {
        $path = Storage::disk('local')->putFile('payment-proofs/legacy', $file);

        return Payment::query()->create([
            ...$data, 'homeowner_id' => $due->homeowner_id, 'payer_homeowner_id' => $payer->homeowner->id,
            'dues_setting_id' => $due->dues_setting_id, 'dues_obligation_id' => $due->id,
            'penalty' => '0.00', 'balance' => $due->amount_due,
            'covered_period' => sprintf('%04d-%02d', $due->billing_year, $due->billing_month),
            'submission_token' => $data['submission_token'] ?? (string) Str::uuid(),
            'transaction_reference_key' => PaymentReference::key($data['transaction_reference']),
            'recorded_by' => $payer->id, 'status' => 'Pending Review', 'review_status' => 'Pending',
            'proof_path' => $path, 'proof_original_name' => $file->getClientOriginalName(),
            'proof_mime_type' => $file->getMimeType(), 'proof_size' => $file->getSize(),
        ]);
    }
}
