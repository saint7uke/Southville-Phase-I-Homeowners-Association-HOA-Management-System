<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DuesSetting;
use App\Models\User;

final class DuesSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage_dues');
    }

    public function view(User $user, DuesSetting $setting): bool
    {
        return $user->can('manage_dues');
    }

    public function create(User $user): bool
    {
        return $user->can('manage_dues');
    }

    public function update(User $user, DuesSetting $setting): bool
    {
        return $user->can('manage_dues');
    }

    public function delete(User $user, DuesSetting $setting): bool
    {
        return $user->hasRole('hoa_admin') && ! $setting->payments()->exists() && ! $setting->obligations()->exists();
    }
}
