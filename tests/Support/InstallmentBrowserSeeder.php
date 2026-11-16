<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Actions\Payments\RecordPayment;
use App\Models\DuesObligation;
use App\Models\DuesSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Notification;

final class InstallmentBrowserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'sqlite'
            || ! str_contains((string) config('database.connections.sqlite.database'), 'hoa-alert-browser-interface-')) {
            throw new \RuntimeException('Installment fixtures require the isolated interface runner.');
        }
        $this->call(AlertBrowserSeeder::class);
        Notification::fake();
        $admin = User::where('email', 'admin@alerts.example.test')->sole();
        $resident = User::where('email', 'resident@alerts.example.test')->sole()->homeowner;
        foreach ([1280 => '100.00', 390 => '120.00'] as $width => $amount) {
            $setting = DuesSetting::create(['name' => 'Installment fixture '.$width, 'amount' => '500.00', 'frequency' => 'Monthly', 'starts_on' => now()->startOfYear(), 'due_day' => 31, 'is_active' => true]);
            $due = DuesObligation::create(['homeowner_id' => $resident->id, 'dues_setting_id' => $setting->id,
                'billing_year' => now()->year, 'billing_month' => now()->month, 'due_date' => today()->endOfMonth(),
                'amount_due' => '500.00', 'amount_paid' => '0.00', 'penalty_amount' => '0.00', 'status' => 'Pending']);
            app(RecordPayment::class)->handle(['dues_obligation_id' => $due->id, 'amount_paid' => $amount,
                'payment_method' => 'Cash', 'payment_date' => today()->toDateString()], $admin);
        }
        $inactive = User::factory()->create(['first_name' => 'Review', 'middle_name' => null, 'last_name' => 'Only', 'account_status' => 'Inactive']);
        $inactive->assignRole('homeowner');
        $property = $inactive->homeowner()->create(['phase' => 'Southville Phase I', 'phase_suffix' => 'A', 'block' => '88', 'lot' => '99',
            'ownership_type' => 'Owner', 'status' => 'Active', 'residency_date' => '2020-01-01']);
        DuesObligation::create(['homeowner_id' => $property->id, 'dues_setting_id' => $setting->id,
            'billing_year' => now()->year, 'billing_month' => now()->month, 'due_date' => today()->endOfMonth(),
            'amount_due' => '500.00', 'amount_paid' => '0.00', 'penalty_amount' => '0.00', 'status' => 'Pending']);
        $archived = User::factory()->create(['first_name' => 'Archived', 'middle_name' => null, 'last_name' => 'Review', 'account_status' => 'Active']);
        $archived->assignRole('homeowner');
        $property = $archived->homeowner()->create(['phase' => 'Southville Phase I', 'phase_suffix' => 'A', 'block' => '89', 'lot' => '99',
            'ownership_type' => 'Owner', 'status' => 'Active', 'residency_date' => '2020-01-01']);
        DuesObligation::create(['homeowner_id' => $property->id, 'dues_setting_id' => $setting->id,
            'billing_year' => now()->year, 'billing_month' => now()->month, 'due_date' => today()->endOfMonth(),
            'amount_due' => '500.00', 'amount_paid' => '0.00', 'penalty_amount' => '0.00', 'status' => 'Pending']);
        $property->delete();
    }
}
