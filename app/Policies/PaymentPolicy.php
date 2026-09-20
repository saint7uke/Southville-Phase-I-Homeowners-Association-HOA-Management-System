<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

final class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage_payments') || $user->can('view_own_payments');
    }

    public function view(User $user, Payment $payment): Response
    {
        return $user->can('manage_payments') || $payment->homeowner->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function create(User $user): bool
    {
        return $user->can('manage_payments');
    }

    public function update(User $user, Payment $payment): bool
    {
        return $user->can('manage_payments')
            && ! $payment->trashed()
            && $payment->review_status === 'Recorded'
            && ($user->hasRole('hoa_admin') || $payment->recorded_by === $user->id);
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
