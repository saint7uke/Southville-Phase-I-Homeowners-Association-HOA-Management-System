<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SmsDelivery;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Sms\WorkflowSms;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class MySqlSmsConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function runDatabaseMigrations(): void
    {
        if (config('database.default') !== 'mysql' || ! str_starts_with((string) config('database.connections.mysql.database'), 'hoa_sms_tests_')) {
            $this->markTestSkipped('Requires a separately configured disposable hoa_sms_tests_* MariaDB database.');
        }
        // Legacy migrations cannot all be rolled back independently of their later FKs.
        // This guarded disposable schema is rebuilt rather than rolling back application migrations.
        $this->artisan('migrate:fresh')->assertExitCode(0);
        $this->beforeApplicationDestroyed(fn () => Schema::dropAllTables());
    }

    private function batches(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Queue::fake();
        config(['textbee.enabled' => true]);
        SystemSetting::current()->update(['sms_enabled' => true, 'sms_daily_segments' => 1]);
        $admin = User::factory()->create();
        $admin->assignRole('hoa_admin');
        $ids = [];
        foreach (['+639171234567', '+639181234567'] as $index => $phone) {
            $ids[] = app(WorkflowSms::class)->raw('test', 'race-'.$index, $phone, 'Synthetic race SMS', ['actor_id' => $admin->id], now()->addMinutes(5), false)->id;
        }

        return $ids;
    }

    public function test_concurrent_budget_reservations_cannot_exceed_daily_cap(): void
    {
        $ids = $this->batches();
        $this->race($ids);
        $this->assertSame(1, SmsDelivery::where('status', 'Accepted')->count());
        $this->assertSame(1, (int) DB::table('sms_daily_usage')->value('segments'));
        $this->assertSame(1, DB::table('sms_sync_states')->where('key', 'like', 'call:%')->count());
    }

    public function test_two_workers_claim_same_batch_only_once(): void
    {
        $ids = $this->batches();
        $this->race([$ids[0], $ids[0]]);
        $this->assertSame(1, SmsDelivery::where('status', 'Accepted')->count());
        $this->assertSame(1, DB::table('sms_sync_states')->where('key', 'like', 'call:%')->count());
    }

    private function race(array $ids): void
    {
        $processes = [];
        foreach ($ids as $id) {
            $code = 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); '
                .'config(["textbee.enabled"=>true,"textbee.api_key"=>"synthetic-key","textbee.test_numbers"=>["+639171234567","+639181234567"]]); '
                .'Illuminate\\Support\\Facades\\Http::preventStrayRequests(); '
                .'Illuminate\\Support\\Facades\\Http::fake(function(){ Illuminate\\Support\\Facades\\DB::table("sms_sync_states")->insert(["key"=>"call:".Illuminate\\Support\\Str::uuid(),"value"=>"synthetic"]); '
                .'usleep(200000); return Illuminate\\Support\\Facades\\Http::response(["data"=>["success"=>true,"smsBatchId"=>"race-".getmypid(),"recipientCount"=>1]]); }); '
                .'usleep(200000); app()->call([new App\\Jobs\\DeliverSmsBatch('.$id.'),"handle"]); echo "ok";';
            $process = new Process([PHP_BINARY, '-r', $code], base_path(), timeout: 120);
            $process->start();
            $processes[] = $process;
        }
        foreach ($processes as $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
            $this->assertSame('ok', $process->getOutput());
        }
    }
}
