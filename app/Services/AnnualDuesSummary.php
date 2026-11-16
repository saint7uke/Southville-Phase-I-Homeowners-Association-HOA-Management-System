<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DuesObligation;
use App\Models\Homeowner;
use App\Support\Money;
use App\Support\PropertyIdentity;

final class AnnualDuesSummary
{
    public function forResident(Homeowner $resident, int $year): array
    {
        $accounts = app(PropertyDuesAccounts::class);
        $liable = $accounts->primaryForAddress(PropertyIdentity::key($resident));
        $ids = [$resident->id];
        if ($liable !== null && $accounts->payerIsEligible($resident, $liable)) {
            $ids[] = $liable->id;
        }
        $obligations = DuesObligation::whereIn('homeowner_id', array_unique($ids))->where('billing_year', $year)
            ->where('status', '!=', 'Cancelled')->whereHas('duesSetting', fn ($query) => $query->where('frequency', 'Monthly'))
            ->with(['payments' => fn ($query) => $query->where('review_status', 'Pending')])->get()->groupBy('billing_month');
        $totals = ['charges' => 0, 'paid' => 0, 'pending' => 0, 'penalties' => 0, 'balance' => 0];
        $months = [];
        for ($month = 1; $month <= 12; $month++) {
            $rows = $obligations->get($month, collect());
            $values = $totals;
            foreach ($values as $key => $_) {
                $values[$key] = 0;
            }
            foreach ($rows as $row) {
                $values['charges'] += Money::toMinor((string) $row->amount_due);
                $values['paid'] += Money::toMinor((string) $row->amount_paid);
                $values['penalties'] += Money::toMinor((string) $row->penalty_amount);
                $values['balance'] += max(0, Money::toMinor((string) $row->amount_due) + Money::toMinor((string) $row->penalty_amount) - Money::toMinor((string) $row->amount_paid));
                $values['pending'] += $row->payments->sum(fn ($payment) => Money::toMinor((string) $payment->amount_paid));
            }
            foreach ($values as $key => $value) {
                $totals[$key] += $value;
            }
            $months[] = ['month' => $month, 'assessed' => $rows->isNotEmpty(), ...array_map(Money::fromMinor(...), $values)];
        }

        return ['months' => $months, 'totals' => array_map(Money::fromMinor(...), $totals)];
    }
}
