<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SystemSetting;
use App\Models\User;

final class SystemSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage_settings');
    }

    public function view(User $user, SystemSetting $setting): bool
    {
        return $user->can('manage_settings');
    }

    public function create(User $user): bool
    {
        return $user->can('manage_settings') && ! SystemSetting::query()->exists();
    }

    public function update(User $user, SystemSetting $setting): bool
    {
        return $user->can('manage_settings');
    }

    public function delete(User $user, SystemSetting $setting): bool
    {
        return false;
    }
}
