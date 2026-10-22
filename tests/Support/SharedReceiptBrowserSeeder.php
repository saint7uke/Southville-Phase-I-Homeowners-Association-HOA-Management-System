<?php

namespace Tests\Support;

use App\Actions\Payments\RecordPayment;
use App\Models\DuesObligation;
use App\Models\User;
use App\Services\ButawReceiptSource;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Notification;

final class SharedReceiptBrowserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'sqlite'
            || ! str_contains((string) config('database.connections.sqlite.database'), 'phase23-browser-test-shared-receipts')) {
            throw new \RuntimeException('Requires the isolated shared-receipt browser database.');
        }
        $this->call(RegistrationReceiptBrowserSeeder::class);
        Notification::fake();
        $admin = User::where('email', 'admin@phase23.test')->firstOrFail();
        $owner = User::where('email', 'resident@phase23.test')->firstOrFail()->homeowner;
        $tenant = User::where('email', 'tenant@phase23.test')->firstOrFail()->homeowner;
        $due = DuesObligation::where('homeowner_id', $owner->id)->where('billing_year', 2026)->where('billing_month', 10)->firstOrFail();
        app(RecordPayment::class)->handle(['dues_obligation_id' => $due->id, 'payer_homeowner_id' => $tenant->id,
            'amount_paid' => '12.34', 'payment_method' => 'Cash', 'payment_date' => '2026-10-20'], $admin);
        if (is_file((string) config('certificates.butaw_font'))) {
            app(ButawReceiptSource::class)->approve(['reviewed' => true, 'note' => 'Synthetic isolated browser fixture ONLY. This is not a real HOA source approval.'], $admin);
        }
    }
}
