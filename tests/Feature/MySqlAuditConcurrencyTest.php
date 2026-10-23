<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Complaints\UpdateComplaint;
use App\Actions\Dues\CloseDuesSetting;
use App\Actions\Dues\GenerateSettingObligations;
use App\Actions\Dues\SaveDuesSetting;
use App\Models\Complaint;
use App\Models\DuesObligation;
use App\Models\DuesSetting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class MySqlAuditConcurrencyTest extends TestCase
{
    use DatabaseMigrations {
        runDatabaseMigrations as private runDisposableMigrations;
    }

    public function runDatabaseMigrations(): void
    {
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Schedule/complaint row-lock races require a separately named disposable MySQL test database.');
        }
        $this->runDisposableMigrations();
    }

    public function test_generation_waits_for_a_schedule_update_and_uses_its_current_amount(): void
    {
        [$actor, $setting] = $this->fixture();
        $process = null;
        try {
            DB::transaction(function () use ($actor, $setting, &$process): void {
                DuesSetting::query()->whereKey($setting->id)->lockForUpdate()->firstOrFail();
                $process = $this->generatorWorker($setting, '2026-10-01');
                $this->waitForReady($process);
                app(SaveDuesSetting::class)->update($setting, $this->updateData($setting), $actor);
            });
            $this->assertSame(1, $this->workerResult($process)['created']);
            $this->assertSame('750.25', DuesObligation::query()->sole()->amount_due);
        } finally {
            $process?->stop();
        }
    }

    public function test_generation_waits_for_closure_and_does_not_assess_a_closed_schedule(): void
    {
        [$actor, $setting] = $this->fixture();
        $process = null;
        try {
            DB::transaction(function () use ($actor, $setting, &$process): void {
                DuesSetting::query()->whereKey($setting->id)->lockForUpdate()->firstOrFail();
                $process = $this->generatorWorker($setting, '2026-11-01');
                $this->waitForReady($process);
                app(CloseDuesSetting::class)->handle($setting, $actor);
            });
            $this->assertSame(0, $this->workerResult($process)['created']);
            $this->assertDatabaseCount('dues_obligations', 0);
        } finally {
            $process?->stop();
        }
    }

    public function test_generation_that_commits_first_freezes_financial_schedule_edits(): void
    {
        [$actor, $setting] = $this->fixture();
        $process = null;
        try {
            DB::transaction(function () use ($actor, $setting, &$process): void {
                app(GenerateSettingObligations::class)->handle($setting, CarbonImmutable::parse('2026-10-01'));
                $payload = var_export($this->updateData($setting), true);
                $process = $this->worker('$setting = App\\Models\\DuesSetting::findOrFail('.$setting->id.'); echo "READY\n"; flush(); '
                    .'try { app(App\\Actions\\Dues\\SaveDuesSetting::class)->update($setting, '.$payload.', App\\Models\\User::findOrFail('.$actor->id.')); echo json_encode(["status" => "updated"]); } '
                    .'catch (Illuminate\\Validation\\ValidationException $e) { echo json_encode(["status" => "invalid", "fields" => array_keys($e->errors())]); }');
                $this->waitForReady($process);
            });
            $result = $this->workerResult($process);
            $this->assertSame('invalid', $result['status']);
            $this->assertContains('amount', $result['fields']);
            $this->assertSame('500.00', $setting->fresh()->amount);
            $this->assertSame('500.00', DuesObligation::query()->sole()->amount_due);
        } finally {
            $process?->stop();
        }
    }

    public function test_a_concurrent_stale_model_save_cannot_overwrite_a_resolved_complaint(): void
    {
        [$actor] = $this->fixture();
        $owner = User::query()->role('homeowner')->firstOrFail()->homeowner;
        $complaint = Complaint::query()->create(['homeowner_id' => $owner->id, 'subject' => 'Concurrent complaint',
            'description' => 'Synthetic complaint details for a row-lock regression.', 'category' => 'Common Area',
            'priority' => 'High', 'status' => 'Under Review', 'handled_by' => $actor->id]);
        $process = null;
        try {
            DB::transaction(function () use ($actor, $complaint, &$process): void {
                Complaint::query()->whereKey($complaint->id)->lockForUpdate()->firstOrFail();
                $process = $this->worker('$stale = App\\Models\\Complaint::findOrFail('.$complaint->id.'); echo "READY\n"; flush(); '
                    .'try { $stale->update(["admin_remarks" => "Stale overwrite"]); echo json_encode(["status" => "updated"]); } '
                    .'catch (Illuminate\\Validation\\ValidationException $e) { echo json_encode(["status" => "invalid"]); }');
                $this->waitForReady($process);
                app(UpdateComplaint::class)->handle($complaint, [...$complaint->only(['homeowner_id', 'subject', 'description', 'category', 'handled_by', 'priority']), 'status' => 'Resolved', 'admin_remarks' => 'Final resolution protected.'], $actor);
            });
            $this->assertSame('invalid', $this->workerResult($process)['status']);
            $this->assertSame('Resolved', $complaint->fresh()->status);
            $this->assertSame('Final resolution protected.', $complaint->fresh()->admin_remarks);
        } finally {
            $process?->stop();
        }
    }

    private function fixture(): array
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-22 12:00:00'));
        $this->seed(RolesAndPermissionsSeeder::class);
        Notification::fake();
        Bus::fake();
        $actor = User::factory()->create();
        $actor->assignRole('hoa_admin');
        $resident = User::factory()->create();
        $resident->assignRole('homeowner');
        $resident->homeowner()->create(['phase' => 'Southville Phase I', 'phase_suffix' => 'A', 'block' => '9', 'lot' => '9', 'street' => 'Synthetic concurrency street', 'ownership_type' => 'Owner', 'status' => 'Active', 'residency_date' => '2020-01-01']);
        $setting = DuesSetting::query()->create(['name' => 'Concurrent audit dues', 'amount' => '500.00', 'frequency' => 'Monthly', 'due_day' => 10, 'starts_on' => '2026-01-01', 'is_active' => true]);

        return [$actor, $setting];
    }

    private function updateData(DuesSetting $setting): array
    {
        return [...$setting->only(['name', 'frequency', 'due_day', 'is_active']), 'starts_on' => '2026-01-01', 'ends_on' => null, 'description' => null, 'amount' => '750.25'];
    }

    private function generatorWorker(DuesSetting $setting, string $period): Process
    {
        return $this->worker('$stale = App\\Models\\DuesSetting::findOrFail('.$setting->id.'); echo "READY\n"; flush(); '
            .'$count = app(App\\Actions\\Dues\\GenerateSettingObligations::class)->handle($stale, Carbon\\CarbonImmutable::parse("'.$period.'")); echo json_encode(["created" => $count]);');
    }

    private function worker(string $code): Process
    {
        $script = 'require "tests/bootstrap.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); '
            .'Illuminate\\Support\\Facades\\Notification::fake(); Illuminate\\Support\\Facades\\Bus::fake(); '
            .'Carbon\\Carbon::setTestNow("2026-10-22 12:00:00"); Carbon\\CarbonImmutable::setTestNow("2026-10-22 12:00:00"); '.$code;
        $process = new Process([PHP_BINARY, '-r', $script], base_path(), timeout: 60);
        $process->start();

        return $process;
    }

    private function waitForReady(Process $process): void
    {
        $this->assertTrue($process->waitUntil(fn ($type, $output) => str_contains($output, 'READY')), $process->getErrorOutput().$process->getOutput());
    }

    private function workerResult(Process $process): array
    {
        $process->wait();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());

        return json_decode(trim(substr($process->getOutput(), strpos($process->getOutput(), "READY\n") + 6)), true, flags: JSON_THROW_ON_ERROR);
    }
}
