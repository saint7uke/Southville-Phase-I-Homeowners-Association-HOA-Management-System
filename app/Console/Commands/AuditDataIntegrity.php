<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\PropertyAddress;
use App\Support\PropertyIdentity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class AuditDataIntegrity extends Command
{
    protected $signature = 'hoa:audit-data {--json : Output aggregate counts as JSON}';

    protected $description = 'Inspect account, property, and payment integrity using read-only aggregate queries';

    public function handle(): int
    {
        // Join only selected address columns; normalize in the shared resolver, not
        // through database-specific Unicode/collation guesses or legacy street keys.
        $landlordLinks = DB::table('homeowners as t')->leftJoin('homeowners as l', 'l.id', '=', 't.landlord_homeowner_id')
            ->whereNull('t.deleted_at')->whereNotNull('t.landlord_homeowner_id')
            ->select(['t.id', 't.ownership_type', 'l.id as landlord_id', 'l.ownership_type as landlord_type', 'l.deleted_at as landlord_deleted_at']);
        foreach (['phase', 'phase_suffix', 'block', 'lot', 'house_number', 'street', 'address_key'] as $field) {
            $landlordLinks->addSelect(["t.{$field} as tenant_{$field}", "l.{$field} as landlord_{$field}"]);
        }
        $invalidLinks = 0;
        $unresolvedLinks = 0;
        foreach ($landlordLinks->cursor() as $link) {
            if ($link->ownership_type !== 'Tenant' || $link->landlord_id === null || $link->landlord_deleted_at !== null
                || ! in_array($link->landlord_type, ['Owner', 'Co-owner'], true) || $link->id === $link->landlord_id) {
                $invalidLinks++;

                continue;
            }
            $tenant = $landlord = [];
            foreach (['phase', 'phase_suffix', 'block', 'lot', 'house_number', 'street', 'address_key'] as $field) {
                $tenant[$field] = $link->{'tenant_'.$field};
                $landlord[$field] = $link->{'landlord_'.$field};
            }
            if (PropertyIdentity::confirmedKey($tenant) === null || PropertyIdentity::confirmedKey($landlord) === null) {
                $unresolvedLinks++;
                // A different base property is definitely invalid. A missing suffix
                // alone requires review, not a guessed match or automatic repair.
                $invalidLinks += PropertyIdentity::baseKey($tenant) !== PropertyIdentity::baseKey($landlord) ? 1 : 0;
            } else {
                $invalidLinks += PropertyIdentity::same($tenant, $landlord) ? 0 : 1;
            }
        }
        $propertyKey = PropertyIdentity::storedKeyExpression('h');
        $accepted = DB::table('payments')->whereNull('deleted_at')->whereIn('review_status', ['Approved', 'Recorded'])
            ->whereNotNull('dues_obligation_id')->selectRaw('dues_obligation_id, SUM(amount_paid) AS accepted_paid, SUM(penalty) AS accepted_penalty')
            ->groupBy('dues_obligation_id');
        $reserved = DB::table('payments')->whereNull('deleted_at')->where('review_status', 'Pending')
            ->whereNotNull('dues_obligation_id')->selectRaw('dues_obligation_id, SUM(amount_paid) AS reserved_amount')
            ->groupBy('dues_obligation_id');

        $checks = [
            'obligations_with_invalid_balances' => DB::table('dues_obligations')->whereRaw('amount_due < 0 OR penalty_amount < 0 OR amount_paid < 0 OR amount_paid > amount_due + penalty_amount')->count(),
            'obligations_with_accepted_payment_mismatch' => DB::table('dues_obligations as d')->leftJoinSub($accepted, 'p', 'p.dues_obligation_id', '=', 'd.id')
                ->whereRaw('ROUND(d.amount_paid * 100) <> ROUND(COALESCE(p.accepted_paid, 0) * 100)')->count(),
            'obligations_with_excess_pending_reservations' => DB::table('dues_obligations as d')->joinSub($reserved, 'p', 'p.dues_obligation_id', '=', 'd.id')
                ->whereRaw('ROUND(p.reserved_amount * 100) > ROUND((d.amount_due + d.penalty_amount - d.amount_paid) * 100)')->count(),
            'payments_with_conflicting_obligation_identity' => DB::table('payments as p')->join('dues_obligations as d', 'd.id', '=', 'p.dues_obligation_id')
                ->whereRaw('p.homeowner_id <> d.homeowner_id OR p.dues_setting_id <> d.dues_setting_id')->count(),
            'current_homeowners_with_deleted_or_missing_users' => DB::table('homeowners as h')->leftJoin('users as u', 'u.id', '=', 'h.user_id')
                ->whereNull('h.deleted_at')->where(fn ($query) => $query->whereNull('u.id')->orWhereNotNull('u.deleted_at'))->count(),
            'invalid_current_landlord_links' => $invalidLinks,
        ];

        if (Schema::hasColumn('dues_obligations', 'property_snapshot')) {
            $checks['obligations_missing_property_snapshot_state'] = DB::table('dues_obligations')
                ->whereNull('property_snapshot')->where('property_snapshot_needs_review', false)->count();
        }

        $observations = [
            'legacy_payments_without_obligations' => DB::table('payments')->whereNull('dues_obligation_id')->count(),
            'unlinked_current_tenants' => DB::table('homeowners')->whereNull('deleted_at')->where('ownership_type', 'Tenant')->whereNull('landlord_homeowner_id')->count(),
            'unresolved_current_landlord_links' => $unresolvedLinks,
            'unresolved_property_billing_records' => DB::table('dues_obligations as d')->join('homeowners as h', 'h.id', '=', 'd.homeowner_id')->whereNull('h.property_key')->count(),
            'duplicate_property_billing_groups' => DB::query()->fromSub(DB::table('dues_obligations as d')
                ->join('homeowners as h', 'h.id', '=', 'd.homeowner_id')->whereRaw("{$propertyKey} IS NOT NULL")
                ->selectRaw("{$propertyKey} AS resolved_property, d.dues_setting_id, d.billing_year, d.billing_month, COUNT(*) AS records")
                ->groupByRaw($propertyKey)->groupBy('d.dues_setting_id', 'd.billing_year', 'd.billing_month')->havingRaw('COUNT(*) > 1'), 'duplicates')->count(),
            'equivalent_email_groups' => DB::query()->fromSub(DB::table('users')->selectRaw('LOWER(TRIM(email)) AS normalized_email, COUNT(*) AS records')
                ->groupByRaw('LOWER(TRIM(email))')->havingRaw('COUNT(*) > 1'), 'duplicates')->count(),
            'overlapping_equivalent_dues_schedules' => DB::table('dues_settings as a')->join('dues_settings as b', function ($join): void {
                $join->on('a.normalized_name', '=', 'b.normalized_name')->on('a.id', '<', 'b.id');
            })->whereRaw('(a.ends_on IS NULL OR SUBSTR(a.ends_on, 1, 7) >= SUBSTR(b.starts_on, 1, 7)) AND (b.ends_on IS NULL OR SUBSTR(b.ends_on, 1, 7) >= SUBSTR(a.starts_on, 1, 7))')->count(),
            'confirmed_properties_with_conflicting_streets' => DB::table('homeowners')
                ->whereNull('deleted_at')->whereNotNull('property_key')
                ->get(['property_key', 'street'])->groupBy('property_key')
                ->filter(fn ($residents): bool => $residents->map(fn ($resident): string => PropertyAddress::streetKey($resident->street))->unique()->count() > 1)
                ->count(),
        ];

        if (Schema::hasColumn('dues_obligations', 'property_snapshot')) {
            $observations['obligations_requiring_property_snapshot_review'] = DB::table('dues_obligations')
                ->where('property_snapshot_needs_review', true)->count();
        }

        $result = ['generated_at' => now()->toIso8601String(), 'connection' => DB::connection()->getDriverName(), 'checks' => $checks, 'review_observations' => $observations];
        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Check', 'Count'], collect([...$checks, ...$observations])->map(fn ($count, $name): array => [$name, $count])->values()->all());
            $this->info('Read-only inspection complete. Nonzero counts require review; no records were changed.');
        }

        return array_sum($checks) === 0 ? self::SUCCESS : self::FAILURE;
    }
}
