<?php

declare(strict_types=1);

namespace App\Actions\Dues;

use App\Models\DuesObligation;
use App\Models\SystemSetting;
use App\Notifications\OverdueDuesNotice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class MarkOverdueObligations
{
    public function handle(?CarbonImmutable $today = null): int
    {
        $today ??= now()->toImmutable()->startOfDay();

        $changed = 0;
        $notificationsEnabled = SystemSetting::current()->delinquency_notifications;

        DuesObligation::query()
            ->with(['homeowner.user', 'duesSetting'])
            ->whereIn('status', ['Pending', 'Partial'])
            ->whereDate('due_date', '<', $today->toDateString())
            ->orderBy('id')
            ->chunkById(200, function (Collection $obligations) use (&$changed, $notificationsEnabled): void {
                foreach ($obligations as $obligation) {
                    $updated = DuesObligation::query()->whereKey($obligation->id)->whereIn('status', ['Pending', 'Partial'])->update(['status' => 'Overdue', 'updated_at' => now()]);
                    $changed += $updated;

                    if ($updated === 1 && $notificationsEnabled) {
                        $obligation->homeowner->user->notify(new OverdueDuesNotice(
                            $obligation->duesSetting->name,
                            $obligation->due_date->format('M d, Y'),
                        ));
                    }
                }
            });

        return $changed;
    }
}
