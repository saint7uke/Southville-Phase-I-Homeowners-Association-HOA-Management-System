<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\DuesObligation;
use App\Models\DuesSetting;
use App\Models\ServiceRequest;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Database\Seeder;

final class CertificateDuesBrowserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'sqlite'
            || ! str_contains((string) config('database.connections.sqlite.database'), 'hoa-alert-browser-certificate-dues-')) {
            throw new \RuntimeException('Requires a disposable certificate dues browser database.');
        }
        $this->call(AlertBrowserSeeder::class);
        SystemSetting::current()->update(['request_notifications' => false, 'payment_notifications' => false, 'sms_enabled' => false]);
        $owner = User::where('email', 'resident@alerts.example.test')->sole()->homeowner;
        $setting = DuesSetting::create(['name' => 'Synthetic monthly dues', 'amount' => '100.00', 'frequency' => 'Monthly', 'starts_on' => '2026-01-01', 'is_active' => true]);
        DuesObligation::create(['homeowner_id' => $owner->id, 'dues_setting_id' => $setting->id, 'billing_year' => now()->year, 'billing_month' => now()->month,
            'due_date' => today()->subDay(), 'amount_due' => '100.00', 'penalty_amount' => '0.00', 'amount_paid' => '0.00', 'status' => 'Overdue']);
        ServiceRequest::create(['homeowner_id' => $owner->id, 'request_type' => 'Certificate of Residency', 'subject' => 'Synthetic processing request', 'details' => 'Synthetic browser test only.', 'status' => 'Processing']);
    }
}
