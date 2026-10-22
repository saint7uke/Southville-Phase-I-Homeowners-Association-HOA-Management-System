<?php

declare(strict_types=1);

namespace App\Services;

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
            ->whereIn('status', ['Paid', 'Partial'])->where('penalty', 0)->whereNotNull('recorded_by')->whereNotNull('property_snapshot')
            ->where('property_snapshot->collector_authorized', true)->whereNotNull('property_snapshot->block')->whereNotNull('property_snapshot->lot')->whereNotNull('property_snapshot->collector_name')
            ->whereHas('duesObligation', fn ($q) => $q->where('billing_year', 2026)->where('penalty_amount', 0)->where('status', '!=', 'Cancelled')->whereHas('duesSetting', fn ($s) => $s->where('frequency', 'Monthly')))
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

    public function snapshot(Payment $payment, ?int $expectedHomeownerId = null): array
    {
        if ($expectedHomeownerId !== null && (int) $payment->homeowner_id !== $expectedHomeownerId) {
            throw ValidationException::withMessages(['payment_id' => __('Select a payment belonging to the selected homeowner / liable property account.')]);
        }
        $payment->loadMissing(['homeowner.user', 'payer.user', 'duesObligation.duesSetting', 'recorder']);
        $obligation = $payment->duesObligation;
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
