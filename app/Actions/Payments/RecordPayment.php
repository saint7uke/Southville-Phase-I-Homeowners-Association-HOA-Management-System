<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Models\DuesObligation;
use App\Models\DuesSetting;
use App\Models\Payment;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class RecordPayment
{
    /** @param array<string, mixed> $data */
    public function handle(array $data, User $recorder): Payment
    {
        Gate::forUser($recorder)->authorize('create', Payment::class);

        return DB::transaction(function () use ($data, $recorder): Payment {
            $obligation = $this->resolveObligation($data);

            $amountPaid = Money::toMinor((string) $data['amount_paid']);
            $penalty = Money::toMinor((string) ($data['penalty'] ?? '0.00'));
            $previousPenalty = Money::toMinor((string) $obligation->penalty_amount);
            $totalDue = Money::toMinor((string) $obligation->amount_due) + $previousPenalty + $penalty;
            $alreadyPaid = Money::toMinor((string) $obligation->amount_paid);
            $balance = max(0, $totalDue - $alreadyPaid - $amountPaid);

            if ($amountPaid > ($totalDue - $alreadyPaid)) {
                throw ValidationException::withMessages([
                    'amount_paid' => __('Payment cannot exceed the remaining obligation balance.'),
                ]);
            }

            $newPaid = $alreadyPaid + $amountPaid;
            $obligation->update([
                'penalty_amount' => Money::fromMinor($previousPenalty + $penalty),
                'amount_paid' => Money::fromMinor($newPaid),
                'status' => $balance === 0 ? 'Paid' : ($obligation->due_date->isPast() ? 'Overdue' : 'Partial'),
            ]);

            return Payment::query()->create([
                ...$data,
                'homeowner_id' => $obligation->homeowner_id,
                'dues_setting_id' => $obligation->dues_setting_id,
                'dues_obligation_id' => $obligation->id,
                'or_number' => 'OR-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                'penalty' => Money::fromMinor($penalty),
                'balance' => Money::fromMinor($balance),
                'status' => $balance > 0 ? 'Partial' : 'Paid',
                'review_status' => 'Recorded',
                'reviewed_at' => now(),
                'reviewed_by' => $recorder->id,
                'recorded_by' => $recorder->id,
            ]);
        });
    }

    /** @param array<string, mixed> $data */
    private function resolveObligation(array $data): DuesObligation
    {
        if (filled($data['dues_obligation_id'] ?? null)) {
            return DuesObligation::query()->lockForUpdate()->findOrFail($data['dues_obligation_id']);
        }

        $dues = DuesSetting::query()->findOrFail($data['dues_setting_id']);
        $period = CarbonImmutable::parse((string) $data['payment_date'])->startOfMonth();

        return DuesObligation::query()->firstOrCreate([
            'homeowner_id' => $data['homeowner_id'],
            'dues_setting_id' => $dues->id,
            'billing_year' => $period->year,
            'billing_month' => $period->month,
        ], [
            'due_date' => $period->day(10)->toDateString(),
            'amount_due' => $dues->amount,
            'penalty_amount' => '0.00',
            'amount_paid' => '0.00',
            'status' => 'Pending',
        ])->fresh(['duesSetting', 'homeowner']);
    }
}
