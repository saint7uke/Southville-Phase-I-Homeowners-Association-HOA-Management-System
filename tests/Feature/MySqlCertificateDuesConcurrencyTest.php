<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DuesObligation;
use App\Models\DuesSetting;
use App\Models\ServiceRequest;
use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class MySqlCertificateDuesConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Requires a separately configured disposable MariaDB test database.');
        }
        if (! str_contains((string) config('database.connections.mysql.database'), 'certificate_dues_tests')) {
            throw new \RuntimeException('Refusing to recreate any database except certificate_dues_tests.');
        }
        // A canonical-identity migration intentionally has no destructive down().
        // Recreate only this explicitly disposable database; never roll migrations back.
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
    }

    public static function settlements(): array
    {
        return ['partial remains blocked' => ['40.00', 'blocked'], 'full unlocks' => ['100.00', 'accepted'], 'new assessment blocks empty ledger' => ['assessment', 'blocked']];
    }

    #[DataProvider('settlements')]
    public function test_submission_waits_for_payment_commit_and_reads_current_balance(string $amount, string $expected): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        SystemSetting::current()->update(['request_notifications' => false, 'payment_notifications' => false, 'sms_enabled' => false]);
        $user = User::factory()->create(['account_status' => 'Active']);
        $user->assignRole('homeowner');
        $owner = $user->homeowner()->create(['block' => '7', 'lot' => '8', 'phase' => 'Southville Phase I', 'phase_suffix' => 'A', 'ownership_type' => 'Owner', 'status' => 'Active', 'residency_date' => '2020-01-01']);
        $admin = User::factory()->create(['account_status' => 'Active']);
        $admin->assignRole('hoa_admin');
        $setting = DuesSetting::create(['name' => 'Synthetic concurrency dues', 'amount' => '100.00', 'frequency' => 'Monthly', 'starts_on' => '2026-01-01', 'is_active' => true]);
        $due = DuesObligation::create(['homeowner_id' => $owner->id, 'dues_setting_id' => $setting->id, 'billing_year' => 2026, 'billing_month' => 11, 'due_date' => '2026-11-01', 'amount_due' => '100.00', 'penalty_amount' => '0.00', 'amount_paid' => '0.00', 'status' => 'Overdue']);
        $ready = tempnam(sys_get_temp_dir(), 'hoa-dues-ready-');
        $release = tempnam(sys_get_temp_dir(), 'hoa-dues-release-');
        file_put_contents($ready, '');
        file_put_contents($release, '');
        $boot = 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); Illuminate\\Support\\Facades\\Notification::fake(); Illuminate\\Support\\Facades\\Http::preventStrayRequests(); ';
        if ($amount === 'assessment') {
            $due->delete();
            $operation = 'app(App\\Actions\\Dues\\GenerateSettingObligations::class)->handle(App\\Models\\DuesSetting::find('.$setting->id.'),Carbon\\CarbonImmutable::create(2026,11,1));';
        } else {
            $operation = 'app(App\\Actions\\Payments\\RecordPayment::class)->handle('.var_export(['dues_obligation_id' => $due->id, 'amount_paid' => $amount, 'payment_method' => 'Cash', 'payment_date' => '2026-11-10'], true).',App\\Models\\User::find('.$admin->id.'));';
        }
        $payment = new Process([PHP_BINARY, '-r', $boot.'Illuminate\\Support\\Facades\\DB::transaction(function(){ '.$operation.' file_put_contents('.var_export($ready, true).',"ready"); $end=microtime(true)+20; while(file_get_contents('.var_export($release, true).')!=="release"){if(microtime(true)>$end)throw new RuntimeException("release timeout");usleep(20000);}}); echo "committed";'], base_path(), timeout: 40);
        $request = new Process([PHP_BINARY, '-r', $boot.'$user=App\\Models\\User::find('.$user->id.'); try{app(App\\Actions\\ServiceRequests\\CreateServiceRequest::class)->handle($user->homeowner,["request_type"=>"Certificate of Residency","subject"=>"Synthetic","details"=>"Synthetic"],uploader:$user);echo "accepted";}catch(App\\Exceptions\\CertificateDuesBlocked){echo "blocked";}'], base_path(), timeout: 40);
        try {
            $payment->start();
            $end = microtime(true) + 20;
            while (file_get_contents($ready) !== 'ready') {
                if (! $payment->isRunning() || microtime(true) > $end) {
                    $this->fail($payment->getErrorOutput().$payment->getOutput());
                }
                usleep(20000);
            }
            $request->start();
            usleep(400000);
            $this->assertTrue($request->isRunning(), 'Request should wait for the held property mutex.');
            file_put_contents($release, 'release');
            $payment->wait();
            $request->wait();
            $this->assertTrue($payment->isSuccessful(), $payment->getErrorOutput());
            $this->assertTrue($request->isSuccessful(), $request->getErrorOutput());
            $this->assertSame($expected, trim($request->getOutput()));
            $this->assertSame($expected === 'accepted' ? 1 : 0, ServiceRequest::count());
        } finally {
            $payment->stop();
            $request->stop();
            unlink($ready);
            unlink($release);
        }
    }
}
