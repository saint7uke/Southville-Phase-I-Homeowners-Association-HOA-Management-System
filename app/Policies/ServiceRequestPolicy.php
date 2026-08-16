<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ServiceRequest;
use App\Models\User;

final class ServiceRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage_requests') || $user->can('view_own_requests');
    }

    public function view(User $user, ServiceRequest $request): bool
    {
        return $user->can('manage_requests') || $request->homeowner->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('manage_requests') || ($user->can('submit_own_request') && $user->account_status === 'Active');
    }

    public function update(User $user, ServiceRequest $request): bool
    {
        return $user->can('manage_requests');
    }

    public function delete(User $user, ServiceRequest $request): bool
    {
        return $user->hasRole('hoa_admin');
    }

    public function restore(User $user, ServiceRequest $request): bool
    {
        return $user->hasRole('hoa_admin');
    }

    public function forceDelete(User $user, ServiceRequest $request): bool
    {
        return false;
    }
}
