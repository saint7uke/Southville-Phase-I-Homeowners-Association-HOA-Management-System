<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DuesSetting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DuesSchedulePeriods
{
    /** Lock names in sorted order before existing schedule rows. */
    public function lockNames(array $names): void
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('A schedule name lock requires a transaction.');
        }
        $names = array_unique($names);
        sort($names);
        foreach ($names as $name) {
            DB::table('dues_setting_name_locks')->insertOrIgnore([
                'normalized_name' => $name, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('dues_setting_name_locks')->where('normalized_name', $name)->lockForUpdate()->first();
        }
    }

    /** Call inside a transaction; callers updating rows must already hold name locks. */
    public function lockAndAssertNoOverlap(array $data, ?int $exceptId = null): void
    {
        $this->lockNames([$data['normalized_name']]);

        $start = CarbonImmutable::parse($data['starts_on'])->startOfMonth()->toDateString();
        $end = filled($data['ends_on'] ?? null) ? CarbonImmutable::parse($data['ends_on'])->endOfMonth()->toDateString() : null;
        $conflict = DuesSetting::query()->where('normalized_name', $data['normalized_name'])
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->when($end !== null, fn ($query) => $query->where('starts_on', '<=', $end))
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhere('ends_on', '>=', $start))
            ->lockForUpdate()->first(['id']);
        if ($conflict) {
            throw ValidationException::withMessages(['name' => __('An equivalent dues schedule already covers part of this billing period.')]);
        }
    }
}
