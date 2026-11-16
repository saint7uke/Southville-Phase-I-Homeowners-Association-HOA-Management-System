<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DuesObligation;
use App\Models\Homeowner;
use App\Models\Payment;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class ButawReceiptEligibility
{
    public function query(): Builder
    {
        if (! Schema::hasColumn('payments', 'property_snapshot')) {
            // The selector must not query a missing JSON column during a staged rollout.
            return Payment::query()->whereRaw('1 = 0');
        }

        return Payment::query()->where('payment_method', 'Cash')->whereIn('review_status', ['Recorded', 'Approved'])
            ->where('status', 'Paid')->where('balance', 0)->where('penalty', 0)->whereNotNull('recorded_by')->whereNotNull('property_snapshot')
            ->where('property_snapshot->collector_authorized', true)->whereNotNull('property_snapshot->block')->whereNotNull('property_snapshot->lot')->whereNotNull('property_snapshot->collector_name')
            ->whereHas('duesObligation', fn ($q) => $q->where('billing_year', 2026)->where('penalty_amount', 0)->where('status', 'Paid')->whereColumn('amount_paid', 'amount_due')->whereHas('duesSetting', fn ($s) => $s->where('frequency', 'Monthly')))
            ->with(['homeowner.user', 'payer.user', 'duesObligation.duesSetting', 'recorder']);
    }

    public function homeowners(): Builder
    {
        // Preserve access to historical liable accounts, but only when a qualifying payment exists.
        return Homeowner::withTrashed()->with('user')->where(function (Builder $query): void {
            $query->where(fn (Builder $current) => $current->whereNull('homeowners.deleted_at')
                ->whereHas('user', fn (Builder $user) => $user->whereNull('users.deleted_at')))
                ->orWhereIn('homeowners.id', $this->query()->select('homeowner_id'));
        });
    }

    public function snapshot(Payment $payment, ?int $expectedHomeownerId = null, bool $lock = false): array
    {
        $this->installmentSnapshot($payment, $expectedHomeownerId);
        $obligation = $payment->duesObligation;
        $all = $obligation->relationLoaded('payments') && ! $lock
            ? $obligation->payments : $obligation->payments()->orderBy('id')
                ->with(['homeowner.user', 'payer.user', 'recorder'])
                ->when($lock, fn ($query) => $query->lockForUpdate())->get();
        $payments = $all->whereIn('review_status', ['Recorded', 'Approved'])->sortBy([
            ['reviewed_at', 'asc'], ['id', 'asc'],
        ])->values();
        if ($payments->contains(fn (Payment $item): bool => (int) $item->homeowner_id !== (int) $obligation->homeowner_id
            || (int) $item->dues_setting_id !== (int) $obligation->dues_setting_id)) {
            throw ValidationException::withMessages(['payment_id' => __('The settlement history requires HOA review before a monthly receipt can be generated.')]);
        }
        $total = $payments->sum(fn (Payment $item): int => Money::toMinor((string) $item->amount_paid));
        if ($obligation->status !== 'Paid' || Money::toMinor((string) $obligation->amount_paid) !== $total
            || $total !== Money::toMinor((string) $obligation->amount_due)
            || $all->contains('review_status', 'Pending')) {
            throw ValidationException::withMessages(['payment_id' => __('Fully settle the monthly dues before generating a monthly receipt.')]);
        }
        $installments = $payments->map(fn (Payment $item): array => [
            ...$this->installmentSnapshot($item, obligation: $obligation),
            'balance' => (string) $item->balance, 'reviewed_at' => $item->reviewed_at?->toIso8601String(),
            'reviewed_by' => $item->reviewed_by, 'transaction_reference' => $item->transaction_reference,
        ])->all();
        // A proof may settle last despite having an older ID; timestamps have second precision.
        $settling = $payments->filter(fn (Payment $item): bool => $item->status === 'Paid' && Money::toMinor((string) $item->balance) === 0);
        $final = $settling->first();
        if ($settling->count() !== 1) {
            throw ValidationException::withMessages(['payment_id' => __('The settlement history requires HOA review before a monthly receipt can be generated.')]);
        }
        $snapshot = $this->installmentSnapshot($final, $expectedHomeownerId, $obligation);
        foreach ($installments as $item) {
            foreach (['block', 'lot', 'phase', 'phase_suffix'] as $field) {
                if (($item['property'][$field] ?? null) !== ($snapshot['property'][$field] ?? null)) {
                    throw ValidationException::withMessages(['payment_id' => __('The settlement history requires HOA review before a monthly receipt can be generated.')]);
                }
            }
        }
        $snapshot['values']['amount'] = Money::fromMinor($total);
        $snapshot['settlement_obligation_id'] = $obligation->id;
        $snapshot['installments'] = $installments;
        $snapshot['assessed_amount'] = (string) $obligation->amount_due;

        return $snapshot;
    }

    /** Historical documents retain their original per-payment eligibility. */
    public function installmentSnapshot(Payment $payment, ?int $expectedHomeownerId = null, ?DuesObligation $obligation = null): array
    {
        if ($expectedHomeownerId !== null && (int) $payment->homeowner_id !== $expectedHomeownerId) {
            throw ValidationException::withMessages(['payment_id' => __('Select a payment belonging to the selected homeowner / liable property account.')]);
        }
        $payment->loadMissing(['homeowner.user', 'payer.user', 'recorder']);
        if ($obligation === null) {
            $payment->loadMissing('duesObligation.duesSetting');
            $obligation = $payment->duesObligation;
        }
        $property = $payment->property_snapshot ?? [];
        if ($payment->trashed() || $payment->payment_method !== 'Cash' || ! in_array($payment->review_status, ['Recorded', 'Approved'], true)
            || ! in_array($payment->status, ['Paid', 'Partial'], true) || Money::toMinor((string) $payment->penalty) !== 0
            || ! $obligation || $obligation->status === 'Cancelled' || $obligation->duesSetting?->frequency !== 'Monthly'
            || Money::toMinor((string) $obligation->penalty_amount) !== 0
            || (int) $obligation->billing_year !== 2026 || ! in_array((int) $obligation->billing_month, range(1, 12), true)
            || ! $payment->recorded_by || ! $payment->recorder || blank($property['block'] ?? null) || blank($property['lot'] ?? null)
            || blank($property['collector_name'] ?? null) || ($property['collector_authorized'] ?? false) !== true
            || (int) ($property['collector_id'] ?? 0) !== $payment->recorded_by || Money::toMinor((string) $payment->amount_paid) <= 0) {
            throw ValidationException::withMessages(['payment_id' => __('Select an accepted 2026 monthly Cash payment with no penalty and a verified property and collector snapshot. Use the detailed payment receipt for unsupported or historical records.')]);
        }

        return [
            'payment_id' => $payment->id, 'or_number' => $payment->or_number,
            'payment_date' => $payment->payment_date?->toDateString(), 'payment_method' => $payment->payment_method,
            'homeowner_id' => $payment->homeowner_id, 'payer_homeowner_id' => $payment->payer_homeowner_id,
            'liable_name' => $payment->homeowner?->user?->full_name, 'payer_name' => $payment->payer?->user?->full_name,
            'property' => $property, 'billing_year' => (int) $obligation->billing_year, 'billing_month' => (int) $obligation->billing_month,
            'review_status' => $payment->review_status, 'status' => $payment->status, 'recorded_by' => $payment->recorded_by,
            'values' => ['amount' => Money::fromMinor(Money::toMinor((string) $payment->amount_paid)),
                'month' => CarbonImmutable::create(2026, (int) $obligation->billing_month)->format('F'),
                'block' => $property['block'], 'lot' => $property['lot'], 'collector' => $property['collector_name']],
        ];
    }
}
