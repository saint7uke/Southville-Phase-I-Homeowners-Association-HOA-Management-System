<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ContactMessage;
use App\Models\User;

final class ContactMessagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage_contact_messages');
    }

    public function view(User $user, ContactMessage $message): bool
    {
        return $user->can('manage_contact_messages');
    }

    public function update(User $user, ContactMessage $message): bool
    {
        return $user->can('manage_contact_messages');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function delete(User $user, ContactMessage $message): bool
    {
        return $user->hasRole('hoa_admin');
    }
}
