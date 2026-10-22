<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Models\DuesObligation;
use App\Models\Payment;
use App\Models\User;
use App\Support\PaymentMethods;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;

/**
 * Retained entry point for legacy callers. Existing proofs are reviewed through
 * ReviewPaymentProof; no new uploads or pending payments may be created.
 */
final class SubmitPaymentProof
{
    /** @param array<string, mixed> $data */
    public function handle(User $actor, DuesObligation $obligation, array $data, UploadedFile $proof): Payment
    {
        throw new AuthorizationException(__(PaymentMethods::CASH_ONLY_MESSAGE));
    }
}
