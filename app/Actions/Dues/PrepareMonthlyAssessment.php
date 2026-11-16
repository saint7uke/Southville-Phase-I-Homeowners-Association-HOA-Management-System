<?php

declare(strict_types=1);

namespace App\Actions\Dues;

use App\Models\DuesObligation;
use App\Models\DuesSetting;
use App\Models\Homeowner;
use App\Models\User;
use App\Services\PropertyAddressAvailability;
use App\Services\PropertyDuesAccounts;
use App\Support\OperationalAccess;
use App\Support\PropertyIdentity;
use App\Support\PropertySnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class PrepareMonthlyAssessment
{
    public function handle(array $data, User $actor): DuesObligation
    {
        OperationalAccess::authorize($actor, 'manage_payments');
        $validated = Validator::make($data, [
            'homeowner_id' => ['required', 'integer', 'exists:homeowners,id'],
            'dues_setting_id' => ['required', 'integer', 'exists:dues_settings,id'],
            'billing_year' => ['required', 'integer', 'min:1900', 'max:'.now('Asia/Manila')->year],
            'billing_month' => ['required', 'integer', 'between:1,12'],
        ])->validate();
        $period = CarbonImmutable::create($validated['billing_year'], $validated['billing_month'], 1, 0, 0, 0, 'Asia/Manila');

        return DB::transaction(function () use ($validated, $period): DuesObligation {
            $resident = Homeowner::findOrFail($validated['homeowner_id']);
            app(PropertyAddressAvailability::class)->lockProperty($resident->getAttributes());
            $resident = Homeowner::lockForUpdate()->findOrFail($resident->id);
            $accounts = app(PropertyDuesAccounts::class);
            $liable = $accounts->primaryForAddress(PropertyIdentity::key($resident), true);
            if ($liable === null || ! $accounts->eligiblePayers($liable, true)->whereKey($resident->id)->exists()) {
                throw ValidationException::withMessages(['homeowner_id' => __('Select an active resident associated with a liable property account.')]);
            }
            $setting = DuesSetting::lockForUpdate()->findOrFail($validated['dues_setting_id']);
            if ($setting->frequency !== 'Monthly') {
                throw ValidationException::withMessages(['dues_setting_id' => __('Select a monthly dues schedule.')]);
            }
            $existing = DuesObligation::where('dues_setting_id', $setting->id)->where('billing_year', $period->year)->where('billing_month', $period->month)
                ->whereHas('homeowner', fn ($query) => PropertyIdentity::scope($query, $resident))->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }
            if (! $setting->is_active || ! app(GenerateSettingObligations::class)->appliesToPeriod($setting, $period)) {
                throw ValidationException::withMessages(['dues_setting_id' => __('This schedule does not apply to the selected billing month.')]);
            }
            $accounts->assertAssessmentIdentity($liable->getAttributes(), $period, $setting->id);

            return DuesObligation::create([
                'homeowner_id' => $liable->id, 'dues_setting_id' => $setting->id,
                'billing_year' => $period->year, 'billing_month' => $period->month,
                'due_date' => $period->day(min((int) ($setting->due_day ?: 10), $period->daysInMonth)),
                'amount_due' => $setting->amount, 'penalty_amount' => '0.00', 'amount_paid' => '0.00', 'status' => 'Pending',
                'property_snapshot' => PropertySnapshot::from($liable), 'property_snapshot_needs_review' => false,
            ]);
        }, 3);
    }
}
