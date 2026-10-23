<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DuesObligation;
use App\Models\DuesSetting;
use App\Models\Homeowner;
use App\Models\Payment;
use App\Models\User;
use App\Services\PropertyAddressAvailability;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class MySqlTenantPropertyPaymentsTest extends TestCase
{
    use DatabaseTruncation {
        truncateDatabaseTables as private truncateDisposableTables;
    }

    protected function truncateDatabaseTables(): void
    {
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Tenant payer races require a separately named disposable MySQL test database.');
        }
        // The old down migration cannot restore block/lot uniqueness over valid
        // shared households. Test committed races with fresh disposable schemas,
        // never by changing migration history or deleting legitimate live records.
        $this->truncateDisposableTables();
        $this->beforeApplicationDestroyed(static function (): void {
            RefreshDatabaseState::$migrated = false;
        });
    }

    public static function revokedRelationships(): array
    {
        return ['unlinked tenant' => ['unlink'], 'inactive landlord account' => ['deactivate']];
    }

    #[DataProvider('revokedRelationships')]
    public function test_recording_rechecks_relationships_committed_while_waiting_for_the_property(string $change): void
    {
        [$staff, $owner, $landlord, $tenant, $due] = $this->fixture();
        $process = null;
        try {
            DB::transaction(function () use ($staff, $owner, $landlord, $tenant, $due, $change, &$process): void {
                app(PropertyAddressAvailability::class)->lockProperty($owner->getAttributes());
                $process = $this->worker($staff, $tenant, $due);
                $this->assertTrue($process->waitUntil(fn ($type, $output) => str_contains($output, 'READY')), $process->getErrorOutput().$process->getOutput());
                if ($change === 'unlink') {
                    $tenant->update(['landlord_homeowner_id' => null]);
                } else {
                    $landlord->user->forceFill(['account_status' => 'Inactive'])->saveQuietly();
                }
            });
            $result = $this->workerResult($process);
            $this->assertSame('invalid', $result['status']);
            $this->assertContains('payer_homeowner_id', $result['fields']);
            $this->assertDatabaseCount('payments', 0);
            $this->assertSame('0.00', $due->fresh()->amount_paid);
        } finally {
            $process?->stop();
        }
    }

    public function test_concurrent_cash_payments_by_a_co_owner_linked_tenant_cannot_overallocate(): void
    {
        [$staff, $owner, , $tenant, $due] = $this->fixture();
        $processes = [];
        try {
            $processes[] = $this->worker($staff, $tenant, $due);
            $processes[] = $this->worker($staff, $tenant, $due);
            $results = array_map(fn (Process $process) => $this->workerResult($process), $processes);
            $this->assertEqualsCanonicalizing(['recorded', 'invalid'], array_column($results, 'status'));
            $this->assertSame('400.00', $due->fresh()->amount_paid);
            $this->assertSame($owner->id, Payment::sole()->homeowner_id);
            $this->assertSame($tenant->id, Payment::sole()->payer_homeowner_id);
            $this->assertDatabaseCount('payments', 1);
            $this->assertDatabaseCount('dues_obligations', 1);
        } finally {
            foreach ($processes as $process) {
                $process->stop();
            }
        }
    }

    private function fixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Notification::fake();
        Bus::fake();
        $staff = User::factory()->create(['account_status' => 'Active']);
        $staff->assignRole('hoa_staff');
        $residents = [];
        foreach (['Owner', 'Co-owner', 'Tenant'] as $type) {
            $user = User::factory()->create(['account_status' => 'Active']);
            $user->assignRole('homeowner');
            $residents[] = $user->homeowner()->create(['phase' => 'Southville Phase I', 'phase_suffix' => 'A', 'block' => '1', 'lot' => '2',
                'street' => 'Synthetic concurrency street', 'residency_date' => '2020-01-01', 'ownership_type' => $type, 'status' => 'Active',
                'landlord_homeowner_id' => $type === 'Tenant' ? $residents[1]->id : null]);
        }
        [$owner, $landlord, $tenant] = $residents;
        $setting = DuesSetting::create(['name' => 'SC07 concurrent dues', 'amount' => '489.00', 'frequency' => 'Monthly', 'starts_on' => '2026-01-01', 'is_active' => true]);
        $due = DuesObligation::create(['homeowner_id' => $owner->id, 'dues_setting_id' => $setting->id, 'billing_year' => 2026, 'billing_month' => 10,
            'due_date' => '2026-10-31', 'amount_due' => '489.00', 'amount_paid' => '0.00', 'penalty_amount' => '0.00', 'status' => 'Pending']);

        return [$staff, $owner, $landlord, $tenant, $due];
    }

    private function worker(User $staff, Homeowner $tenant, DuesObligation $due): Process
    {
        $payload = var_export(['dues_obligation_id' => $due->id, 'payer_homeowner_id' => $tenant->id, 'amount_paid' => '400.00', 'payment_date' => '2026-10-23', 'payment_method' => 'Cash', 'submission_token' => (string) Str::uuid()], true);
        $script = 'require "tests/bootstrap.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); '
            .'Illuminate\\Support\\Facades\\Notification::fake(); Illuminate\\Support\\Facades\\Bus::fake(); '
            .'Carbon\\Carbon::setTestNow("2026-10-23 10:00:00"); '
            .'$tenant = App\\Models\\Homeowner::with("landlord.user", "user")->findOrFail('.$tenant->id.'); '
            .'echo "READY\n"; flush(); '
            .'try { $payment = app(App\\Actions\\Payments\\RecordPayment::class)->handle('.$payload.', App\\Models\\User::findOrFail('.$staff->id.')); '
            .'echo json_encode(["status" => "recorded", "id" => $payment->id]); } '
            .'catch (Illuminate\\Validation\\ValidationException $e) { echo json_encode(["status" => "invalid", "fields" => array_keys($e->errors())]); }';
        $process = new Process([PHP_BINARY, '-r', $script], base_path(), timeout: 60);
        $process->start();

        return $process;
    }

    private function workerResult(Process $process): array
    {
        $process->wait();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());

        return json_decode(trim(substr($process->getOutput(), strpos($process->getOutput(), "READY\n") + 6)), true, flags: JSON_THROW_ON_ERROR);
    }
}
