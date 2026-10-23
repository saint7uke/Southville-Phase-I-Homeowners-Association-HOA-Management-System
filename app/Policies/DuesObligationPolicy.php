<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DuesObligation;
use App\Models\User;
use App\Services\PropertyDuesAccounts;
use Illuminate\Auth\Access\Response;

final class DuesObligationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage_payments') || $user->can('view_own_payments');
    }

    public function view(User $user, DuesObligation $obligation): Response
    {
        if ($user->can('manage_payments') || $obligation->homeowner->user_id === $user->id) {
            return Response::allow();
        }
        $resident = $user->can('view_own_payments') ? $user->homeowner()->first() : null;

        return $resident !== null && app(PropertyDuesAccounts::class)->payerIsEligible($resident, $obligation->homeowner)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, DuesObligation $obligation): bool
    {
        return false;
    }

    public function delete(User $user, DuesObligation $obligation): bool
    {
        return false;
    }
}
