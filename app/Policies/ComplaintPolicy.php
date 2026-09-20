<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Complaint;
use App\Models\User;

final class ComplaintPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage_complaints') || $user->can('view_own_complaints');
    }

    public function view(User $user, Complaint $complaint): bool
    {
        return $user->can('manage_complaints') || $complaint->homeowner->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('hoa_admin') || ($user->can('submit_own_complaint') && $user->account_status === 'Active');
    }

    public function update(User $user, Complaint $complaint): bool
    {
        return $user->can('manage_complaints') && (! $complaint->trashed() || $user->hasRole('hoa_admin'));
    }

    public function delete(User $user, Complaint $complaint): bool
    {
        return $user->hasRole('hoa_admin');
    }

    public function restore(User $user, Complaint $complaint): bool
    {
        return $user->hasRole('hoa_admin');
    }

    public function forceDelete(User $user, Complaint $complaint): bool
    {
        return false;
    }
}
