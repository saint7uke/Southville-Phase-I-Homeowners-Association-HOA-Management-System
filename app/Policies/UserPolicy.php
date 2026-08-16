<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

final class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage_users');
    }

    public function view(User $user, User $record): bool
    {
        return $user->can('manage_users');
    }

    public function create(User $user): bool
    {
        return $user->can('manage_users');
    }

    public function update(User $user, User $record): bool
    {
        return $user->can('manage_users') && ($record->id !== $user->id || $record->account_status === 'Active');
    }

    public function delete(User $user, User $record): bool
    {
        return $user->can('manage_users') && $record->id !== $user->id;
    }

    public function restore(User $user, User $record): bool
    {
        return $user->can('manage_users');
    }

    public function forceDelete(User $user, User $record): bool
    {
        return false;
    }
}
