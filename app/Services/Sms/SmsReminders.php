<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Models\DuesObligation;
use App\Models\SystemSetting;
use App\Support\Money;
use Carbon\CarbonImmutable;

final class SmsReminders
{
    public function generate(bool $dryRun = false): array
    {
        if (! app(SmsEligibility::class)->enabled()) {
            return ['due' => 0, 'overdue' => 0];
        }
        $settings = SystemSetting::current();
        $today = CarbonImmutable::now('Asia/Manila')->startOfDay();
        $groups = ['due' => [], 'overdue' => []];
        DuesObligation::query()->with(['homeowner.user', 'duesSetting'])->whereNotIn('status', ['Paid', 'Cancelled'])
            ->whereHas('duesSetting', fn ($query) => $query->where('frequency', 'Monthly'))->orderBy('id')->chunkById(200, function ($rows) use (&$groups, $today, $settings): void {
                foreach ($rows as $row) {
                    if (! $row->homeowner || ! $row->homeowner->user || Money::toMinor($row->amount_due) + Money::toMinor($row->penalty_amount) <= Money::toMinor($row->amount_paid)) {
                        continue;
                    }
                    $due = CarbonImmutable::parse($row->due_date->toDateString(), 'Asia/Manila');
                    if ($due->toDateString() === $today->addDays($settings->sms_due_days)->toDateString()) {
                        $groups['due'][] = $row;
                    }
                    $elapsed = (int) $due->diffInDays($today, false);
                    if ($elapsed >= 1 && ($elapsed === 1 || ($settings->sms_overdue_days > 0 && ($elapsed - 1) % $settings->sms_overdue_days === 0))) {
                        $groups['overdue'][] = $row;
                    }
                }
            });
        foreach ($groups as $type => $rows) {
            if (! $dryRun && $rows !== []) {
                app(WorkflowSms::class)->queue($type, 'reminder:'.$type.':'.$today->toDateString(),
                    array_map(fn ($row) => $row->homeowner->user, $rows),
                    ['date' => $today->toDateString(), 'obligation_ids' => array_map(fn ($row) => $row->id, $rows)], $today->endOfDay());
            }
        }

        return array_map('count', $groups);
    }
}
