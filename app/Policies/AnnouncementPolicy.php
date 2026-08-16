<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Announcement;
use App\Models\User;

final class AnnouncementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage_announcements');
    }

    public function view(User $user, Announcement $announcement): bool
    {
        return $user->can('manage_announcements') || ($announcement->status === 'Published' && $user->can('view_published_announcements'));
    }

    public function create(User $user): bool
    {
        return $user->can('manage_announcements');
    }

    public function update(User $user, Announcement $announcement): bool
    {
        return $user->can('manage_announcements');
    }

    public function delete(User $user, Announcement $announcement): bool
    {
        return $user->hasRole('hoa_admin');
    }

    public function restore(User $user, Announcement $announcement): bool
    {
        return $user->hasRole('hoa_admin');
    }

    public function forceDelete(User $user, Announcement $announcement): bool
    {
        return false;
    }
}
