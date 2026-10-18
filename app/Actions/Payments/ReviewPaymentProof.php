<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Models\DuesObligation;
use App\Models\Payment;
use App\Models\SystemSetting;
use App\Models\User;
use App\Notifications\PaymentReviewCompleted;
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

        $reviewed = DB::transaction(function () use ($payment, $reviewer): Payment {
            $snapshot = Payment::query()->findOrFail($payment->id);
            if ($snapshot->dues_obligation_id === null) {
                throw ValidationException::withMessages(['payment' => __('Only a pending payment proof can be approved.')]);
            }
            $obligation = DuesObligation::query()->lockForUpdate()->findOrFail($snapshot->dues_obligation_id);
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($lockedPayment->review_status !== 'Pending' || $lockedPayment->dues_obligation_id === null) {
                throw ValidationException::withMessages(['payment' => __('Only a pending payment proof can be approved.')]);
            }
            $obligation->payments()->where('review_status', 'Pending')->orderBy('id')->lockForUpdate()->get();

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

        $this->notifyParties($reviewed);

        return $reviewed;
    }

    public function reject(Payment $payment, User $reviewer, string $reason): Payment
    {
        if (! $reviewer->can('manage_payments')) {
            throw new AuthorizationException;
        }

        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 1000) {
            throw ValidationException::withMessages(['review_notes' => __('A rejection reason of no more than 1,000 characters is required.')]);
        }

        $reviewed = DB::transaction(function () use ($payment, $reviewer, $reason): Payment {
            $snapshot = Payment::query()->findOrFail($payment->id);
            if ($snapshot->dues_obligation_id !== null) {
                $obligation = DuesObligation::query()->lockForUpdate()->findOrFail($snapshot->dues_obligation_id);
                $obligation->payments()->where('review_status', 'Pending')->orderBy('id')->lockForUpdate()->get();
            }
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($locked->review_status !== 'Pending') {
                throw ValidationException::withMessages(['payment' => __('Only a pending payment proof can be rejected.')]);
            }
            $locked->update([
                'status' => 'Rejected',
                'review_status' => 'Rejected',
                'review_notes' => $reason,
                'reviewed_at' => now(),
                'reviewed_by' => $reviewer->id,
            ]);

            return $locked->refresh();
        });

        $this->notifyParties($reviewed);

        return $reviewed;
    }

    private function notifyParties(Payment $payment): void
    {
        if (! SystemSetting::current()->payment_notifications) {
            return;
        }

        $payment->loadMissing(['homeowner.user', 'payer.user']);
        collect([$payment->payer?->user, $payment->homeowner?->user])
            ->filter()
            ->unique('id')
            ->each(fn (User $user) => $user->notify(new PaymentReviewCompleted($payment)));
    }
}
