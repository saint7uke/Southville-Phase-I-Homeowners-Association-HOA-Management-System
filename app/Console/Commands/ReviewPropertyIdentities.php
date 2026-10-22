<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DuesObligation;
use App\Models\Homeowner;
use App\Models\Household;
use App\Support\PropertyIdentity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

final class ReviewPropertyIdentities extends Command
{
    protected $signature = 'hoa:review-property-identities {--json : Emit a read-only JSON review report}';

    protected $description = 'Dry-run review of unknown phases, multiple Owners, households and current-period assessments; never modifies records';

    public function handle(): int
    {
        $identitySchemaReady = Schema::hasColumn('homeowners', 'property_key');
        $residents = Homeowner::query()->whereHas('user', fn ($q) => $q->whereNull('users.deleted_at'))->get();
        $groups = $residents->groupBy(fn ($resident) => $resident->property_key ?? 'unresolved-base:'.PropertyIdentity::baseKey($resident));
        $issues = [];
        foreach ($residents as $resident) {
            if (! $resident->property_key) {
                $issues[] = ['issue' => 'Unknown phase suffix', 'homeowner_id' => $resident->id, 'base_key' => PropertyIdentity::baseKey($resident)];
            }
        }
        // Archived liable accounts can still carry an assessment. Do not silently
        // treat their unconfirmed property as a new, unbilled physical property.
        $unresolved = DuesObligation::query()->with('homeowner')->where('billing_year', now()->year)->where('billing_month', now()->month)
            ->where('status', '!=', 'Cancelled')
            ->when($identitySchemaReady, fn ($query) => $query->whereHas('homeowner', fn ($resident) => $resident->whereNull('property_key')))->get();
        foreach ($unresolved as $obligation) {
            if ($obligation->homeowner && ! $obligation->homeowner->property_key) {
                $issues[] = ['issue' => 'Unconfirmed current-period assessment', 'obligation_id' => $obligation->id,
                    'homeowner_id' => $obligation->homeowner_id, 'archived_homeowner' => $obligation->homeowner->trashed(),
                    'base_key' => PropertyIdentity::baseKey($obligation->homeowner)];
            }
        }
        foreach ($groups as $key => $members) {
            if ($members->where('ownership_type', 'Owner')->count() > 1) {
                $issues[] = ['issue' => 'Multiple Owners', 'property_key' => $key, 'homeowner_ids' => $members->modelKeys()];
            }
            $households = Schema::hasTable('households')
                ? ($identitySchemaReady ? PropertyIdentity::scope(Household::withTrashed(), $members->first()) : Household::withTrashed()->where('address_key', $members->first()->address_key))->count()
                : 0;
            if ($households > 1) {
                $issues[] = ['issue' => 'Conflicting households', 'property_key' => $key];
            }
            $conflicts = DuesObligation::whereIn('homeowner_id', $members->modelKeys())->where('billing_year', now()->year)->where('billing_month', now()->month)
                ->where('status', '!=', 'Cancelled')->select('dues_setting_id')->selectRaw('COUNT(*) AS obligations')->groupBy('dues_setting_id')->havingRaw('COUNT(*) > 1')->get();
            foreach ($conflicts as $conflict) {
                $issues[] = ['issue' => 'Current-period obligation conflict', 'property_key' => $key, 'schedule_id' => $conflict->dues_setting_id, 'count' => $conflict->obligations];
            }
        }
        if ($this->option('json')) {
            $this->line(json_encode(['read_only' => true, 'identity_schema_ready' => $identitySchemaReady, 'generated_at' => now()->toIso8601String(), 'issues' => $issues], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } else {
            $this->info('Read-only review; no records changed. Findings: '.count($issues));
            foreach ($issues as $issue) {
                $this->line(json_encode($issue, JSON_THROW_ON_ERROR));
            }
        }

        return self::SUCCESS;
    }
}
