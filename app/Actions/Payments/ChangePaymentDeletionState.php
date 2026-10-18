<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Models\DuesObligation;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class ChangePaymentDeletionState
{
    public function delete(Payment $payment, User $actor): void
    {
        Gate::forUser($actor)->authorize('delete', $payment);
        DB::transaction(function () use ($payment): void {
            $snapshot = Payment::query()->findOrFail($payment->id);
            if ($snapshot->dues_obligation_id) {
                DuesObligation::query()->lockForUpdate()->findOrFail($snapshot->dues_obligation_id);
            }
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $locked->delete();
        });
    }

    public function restore(Payment $payment, User $actor): void
    {
        Gate::forUser($actor)->authorize('restore', $payment);
        DB::transaction(function () use ($payment): void {
            $snapshot = Payment::withTrashed()->findOrFail($payment->id);
            if ($snapshot->dues_obligation_id) {
                DuesObligation::query()->lockForUpdate()->findOrFail($snapshot->dues_obligation_id);
            }
            $locked = Payment::withTrashed()->lockForUpdate()->findOrFail($payment->id);
            $locked->restore();
        });
    }
}
