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
        if (! $user->can('manage_complaints') || $complaint->trashed()) {
            return false;
        }

        if (! in_array($complaint->status, ['Pending', 'Under Review'], true)) {
            return false;
        }

        return $user->hasRole('hoa_admin') || $complaint->handled_by === $user->id;
    }

    public function reopen(User $user, Complaint $complaint): bool
    {
        if (! $user->can('manage_complaints') || $complaint->trashed() || $complaint->status !== 'Resolved') {
            return false;
        }

        return $user->hasRole('hoa_admin') || $complaint->handled_by === $user->id;
    }

    public function delete(User $user, Complaint $complaint): bool
    {
        return $user->hasRole('hoa_admin')
            && ! $complaint->trashed()
            && in_array($complaint->status, ['Pending', 'Under Review'], true);
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
