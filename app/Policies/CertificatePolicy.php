<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Certificate;
use App\Models\User;
use Illuminate\Auth\Access\Response;

final class CertificatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage_requests') || $user->can('view_own_certificates');
    }

    public function view(User $user, Certificate $certificate): Response
    {
        return $user->can('manage_requests') || $certificate->homeowner->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function create(User $user): bool
    {
        return $user->can('manage_requests');
    }

    public function update(User $user, Certificate $certificate): bool
    {
        return $user->can('manage_requests') && (! $certificate->trashed() || $user->hasRole('hoa_admin'));
    }

    public function delete(User $user, Certificate $certificate): bool
    {
        return $user->hasRole('hoa_admin');
    }

    public function restore(User $user, Certificate $certificate): bool
    {
        return $user->hasRole('hoa_admin');
    }

    public function forceDelete(User $user, Certificate $certificate): bool
    {
        return false;
    }
}
