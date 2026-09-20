<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Homeowner;
use App\Models\User;
use Illuminate\Auth\Access\Response;

final class HomeownerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage_homeowners');
    }

    public function view(User $user, Homeowner $homeowner): Response
    {
        return $user->can('manage_homeowners') || $homeowner->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function create(User $user): bool
    {
        return $user->can('manage_homeowners');
    }

    public function update(User $user, Homeowner $homeowner): bool
    {
        return ($user->can('manage_homeowners') && (! $homeowner->trashed() || $user->hasRole('hoa_admin')))
            || ($homeowner->user_id === $user->id && $user->can('update_own_profile'));
    }

    public function delete(User $user, Homeowner $homeowner): bool
    {
        return $user->can('delete_homeowners');
    }

    public function restore(User $user, Homeowner $homeowner): bool
    {
        return $user->can('delete_homeowners');
    }

    public function forceDelete(User $user, Homeowner $homeowner): bool
    {
        return false;
    }
}
