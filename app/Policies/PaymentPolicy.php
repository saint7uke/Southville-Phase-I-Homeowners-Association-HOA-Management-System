<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

final class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage_payments');
    }

    public function view(User $user, Payment $payment): bool
    {
        return $user->can('manage_payments') || $payment->homeowner->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('manage_payments');
    }

    public function update(User $user, Payment $payment): bool
    {
        return $user->can('manage_payments');
    }

    public function delete(User $user, Payment $payment): bool
    {
        return $user->can('delete_payments');
    }

    public function restore(User $user, Payment $payment): bool
    {
        return $user->can('delete_payments');
    }

    public function forceDelete(User $user, Payment $payment): bool
    {
        return false;
    }
}
