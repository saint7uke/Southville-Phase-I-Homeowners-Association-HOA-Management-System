<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DuesObligation;
use App\Models\DuesSetting;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class MySqlPaymentConcurrencyTest extends TestCase
{
    use DatabaseMigrations {
        runDatabaseMigrations as private runDisposableMigrations;
    }

    public function runDatabaseMigrations(): void
    {
        $this->requireMySql();
        $this->runDisposableMigrations();
    }

    public function test_concurrent_payments_cannot_overallocate_an_obligation(): void
    {
        $this->requireMySql();
        [$actor, $due] = $this->fixture();
        $results = $this->race($actor, $due, false);
        $this->assertEqualsCanonicalizing(['recorded', 'invalid'], array_column($results, 'status'));
        $this->assertSame('400.00', $due->fresh()->amount_paid);
        $this->assertSame(1, Payment::query()->count());
    }

    public function test_concurrent_matching_tokens_return_one_payment(): void
    {
        $this->requireMySql();
        [$actor, $due] = $this->fixture();
        $results = $this->race($actor, $due, true);
        $this->assertSame(['recorded', 'recorded'], array_column($results, 'status'));
        $this->assertSame($results[0]['id'], $results[1]['id']);
        $this->assertSame('400.00', $due->fresh()->amount_paid);
        $this->assertSame(1, Payment::query()->count());
    }

    private function requireMySql(): void
    {
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Row-lock concurrency requires the separately configured disposable MySQL test database.');
        }
    }

    private function fixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $actor = User::factory()->create();
        $actor->assignRole('hoa_admin');
        $resident = User::factory()->create();
        $resident->assignRole('homeowner');
        $homeowner = $resident->homeowner()->create([
            'house_number' => '9', 'street' => 'Concurrency Street', 'block' => '9', 'lot' => '9',
            'phase' => 'Southville Phase I', 'ownership_type' => 'Owner', 'status' => 'Active', 'residency_date' => '2020-01-01',
        ]);
        $setting = DuesSetting::query()->create([
            'name' => 'Concurrency dues', 'amount' => '500.00', 'frequency' => 'Monthly', 'starts_on' => '2026-01-01', 'is_active' => true,
        ]);
        $due = DuesObligation::query()->create([
            'homeowner_id' => $homeowner->id, 'dues_setting_id' => $setting->id,
            'billing_year' => 2026, 'billing_month' => 10, 'due_date' => '2026-10-31',
            'amount_due' => '500.00', 'amount_paid' => '0.00', 'penalty_amount' => '0.00', 'status' => 'Pending',
        ]);

        return [$actor, $due];
    }

    private function race(User $actor, DuesObligation $due, bool $sameToken): array
    {
        $token = (string) Str::uuid();
        $processes = [];
        foreach (range(1, 2) as $index) {
            $payload = var_export([
                'dues_obligation_id' => $due->id,
                'amount_paid' => '400.00', 'payment_date' => today()->toDateString(), 'payment_method' => 'Cash',
                'submission_token' => $sameToken ? $token : (string) Str::uuid(),
            ], true);
            $script = 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; '
                .'$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); '
                .'$actor = App\\Models\\User::findOrFail('.$actor->id.'); usleep(100000); '
                .'try { $p = app(App\\Actions\\Payments\\RecordPayment::class)->handle('.$payload.', $actor); '
                .'echo json_encode(["status" => "recorded", "id" => $p->id]); } '
                .'catch (Illuminate\\Validation\\ValidationException $e) { echo json_encode(["status" => "invalid"]); }';
            $process = new Process([PHP_BINARY, '-r', $script], base_path(), timeout: 60);
            $process->start();
            $processes[] = $process;
        }

        return array_map(function (Process $process): array {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());

            return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        }, $processes);
    }
}
