<?php

declare(strict_types=1);

namespace App\Actions\Certificates;

use App\Models\Certificate;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

final class RevokeCertificate
{
    public function handle(Certificate $certificate, User $actor, string $reason): Certificate
    {
        if (! $actor->hasRole('hoa_admin')) {
            throw new AuthorizationException;
        }

        if ($certificate->trashed() || $certificate->status !== 'Issued') {
            throw ValidationException::withMessages(['certificate' => __('Only an issued certificate can be revoked.')]);
        }

        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['reason' => __('A revocation reason of 500 characters or fewer is required.')]);
        }

        $certificate->update([
            'status' => 'Revoked',
            'revoked_at' => now(),
            'revoked_by' => $actor->id,
            'revocation_reason' => $reason,
        ]);

        return $certificate->refresh();
    }
}
