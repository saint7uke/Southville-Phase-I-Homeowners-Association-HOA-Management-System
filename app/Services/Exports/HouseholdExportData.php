<?php

declare(strict_types=1);

namespace App\Services\Exports;

use App\Models\Household;

/** Data preparation only; each entry point must retain its own authorization. */
final class HouseholdExportData
{
    public function prepare(Household $household, bool $manager): array
    {
        $members = $household->members()->whereNull('ended_at')->orderBy('id')->get($manager
            ? ['full_name', 'relationship', 'date_of_birth', 'sex', 'residency_status']
            : ['full_name', 'relationship', 'residency_status']);

        return compact('household', 'members', 'manager');
    }
}
