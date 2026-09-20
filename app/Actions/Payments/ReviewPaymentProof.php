<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Models\DuesObligation;
use App\Models\Payment;
use App\Models\User;
use App\Support\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReviewPaymentProof
{
    public function approve(Payment $payment, User $reviewer): Payment
    {
        if (! $reviewer->can('manage_payments')) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($payment, $reviewer): Payment {
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($lockedPayment->review_status !== 'Pending' || $lockedPayment->dues_obligation_id === null) {
                throw ValidationException::withMessages(['payment' => __('Only a pending payment proof can be approved.')]);
            }

            $obligation = DuesObligation::query()->lockForUpdate()->findOrFail($lockedPayment->dues_obligation_id);
            $totalDue = Money::toMinor((string) $obligation->amount_due) + Money::toMinor((string) $obligation->penalty_amount);
            $alreadyPaid = Money::toMinor((string) $obligation->amount_paid);
            $received = Money::toMinor((string) $lockedPayment->amount_paid);

            if ($received > $totalDue - $alreadyPaid) {
                throw ValidationException::withMessages(['payment' => __('The submitted amount exceeds the remaining obligation balance.')]);
            }

            $balance = $totalDue - $alreadyPaid - $received;
            $obligation->update([
                'amount_paid' => Money::fromMinor($alreadyPaid + $received),
                'status' => $balance === 0 ? 'Paid' : ($obligation->due_date->isPast() ? 'Overdue' : 'Partial'),
            ]);

            $lockedPayment->update([
                'balance' => Money::fromMinor($balance),
                'status' => $balance === 0 ? 'Paid' : 'Partial',
                'review_status' => 'Approved',
                'reviewed_at' => now(),
                'reviewed_by' => $reviewer->id,
            ]);

            return $lockedPayment->refresh();
        });
    }

    public function reject(Payment $payment, User $reviewer): Payment
    {
        if (! $reviewer->can('manage_payments')) {
            throw new AuthorizationException;
        }

        if ($payment->review_status !== 'Pending') {
            throw ValidationException::withMessages(['payment' => __('Only a pending payment proof can be rejected.')]);
        }

        $payment->update([
            'status' => 'Rejected',
            'review_status' => 'Rejected',
            'reviewed_at' => now(),
            'reviewed_by' => $reviewer->id,
        ]);

        return $payment->refresh();
    }
}
