<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Homeowner;
use App\Models\User;

final class HomeownerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage_homeowners');
    }

    public function view(User $user, Homeowner $homeowner): bool
    {
        return $user->can('manage_homeowners') || $homeowner->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('manage_homeowners');
    }

    public function update(User $user, Homeowner $homeowner): bool
    {
        return $user->can('manage_homeowners');
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
