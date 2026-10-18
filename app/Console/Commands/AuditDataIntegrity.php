<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class AuditDataIntegrity extends Command
{
    protected $signature = 'hoa:audit-data {--json : Output aggregate counts as JSON}';

    protected $description = 'Inspect account, property, and payment integrity using read-only aggregate queries';

    public function handle(): int
    {
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
            'invalid_current_landlord_links' => DB::table('homeowners as t')->leftJoin('homeowners as l', 'l.id', '=', 't.landlord_homeowner_id')
                ->whereNull('t.deleted_at')->whereNotNull('t.landlord_homeowner_id')
                ->where(fn ($query) => $query->where('t.ownership_type', '!=', 'Tenant')->orWhereNull('l.id')->orWhereNotNull('l.deleted_at')
                    ->orWhereNotIn('l.ownership_type', ['Owner', 'Co-owner'])->orWhereColumn('t.id', 'l.id')->orWhereColumn('t.address_key', '!=', 'l.address_key'))->count(),
        ];

        $observations = [
            'legacy_payments_without_obligations' => DB::table('payments')->whereNull('dues_obligation_id')->count(),
            'unlinked_current_tenants' => DB::table('homeowners')->whereNull('deleted_at')->where('ownership_type', 'Tenant')->whereNull('landlord_homeowner_id')->count(),
            'duplicate_property_billing_groups' => DB::query()->fromSub(DB::table('dues_obligations as d')
                ->join('homeowners as h', 'h.id', '=', 'd.homeowner_id')->whereNotNull('h.address_key')
                ->selectRaw('h.address_key, d.dues_setting_id, d.billing_year, d.billing_month, COUNT(*) AS records')
                ->groupBy('h.address_key', 'd.dues_setting_id', 'd.billing_year', 'd.billing_month')->havingRaw('COUNT(*) > 1'), 'duplicates')->count(),
            'equivalent_email_groups' => DB::query()->fromSub(DB::table('users')->selectRaw('LOWER(TRIM(email)) AS normalized_email, COUNT(*) AS records')
                ->groupByRaw('LOWER(TRIM(email))')->havingRaw('COUNT(*) > 1'), 'duplicates')->count(),
            'overlapping_equivalent_dues_schedules' => DB::table('dues_settings as a')->join('dues_settings as b', function ($join): void {
                $join->on('a.normalized_name', '=', 'b.normalized_name')->on('a.id', '<', 'b.id');
            })->whereRaw('(a.ends_on IS NULL OR SUBSTR(a.ends_on, 1, 7) >= SUBSTR(b.starts_on, 1, 7)) AND (b.ends_on IS NULL OR SUBSTR(b.ends_on, 1, 7) >= SUBSTR(a.starts_on, 1, 7))')->count(),
        ];

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
