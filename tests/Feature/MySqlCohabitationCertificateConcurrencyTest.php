<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\CohabitationCertificateData;
use App\Services\CohabitationCertificateSource;
use App\Services\ResidencyCertificatePreviews;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Tests\Support\UsesSyntheticCohabitation;
use Tests\TestCase;

final class MySqlCohabitationCertificateConcurrencyTest extends TestCase
{
    use DatabaseMigrations { runDatabaseMigrations as private runDisposableMigrations; }
    use UsesSyntheticCohabitation;

    public function runDatabaseMigrations(): void
    {
        if (config('database.default') !== 'mysql' || ! is_file((string) config('certificates.cohabitation_font'))) {
            $this->markTestSkipped('Requires a disposable MySQL test database and the licensed Arial font.');
        }
        $this->runDisposableMigrations();
    }

    public function test_parallel_confirmation_creates_one_original_document_and_completes_request_once(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 21)->setTime(10, 0));
        $this->seed(RolesAndPermissionsSeeder::class);
        Notification::fake();
        Storage::set('local', Storage::fake('cohabitation-race-'.getmypid()));
        $this->syntheticCohabitationSource();
        $admin = User::factory()->create();
        $admin->assignRole('hoa_admin');
        $resident = User::factory()->create(['first_name' => 'Test', 'middle_name' => null, 'last_name' => 'Resident', 'date_of_birth' => '1990-01-01']);
        $owner = $resident->homeowner()->create(['street' => 'Test Street', 'block' => '1', 'lot' => '2', 'phase' => 'Southville Phase I', 'phase_suffix' => 'A', 'residency_date' => '2020-01-01', 'ownership_type' => 'Owner', 'status' => 'Active']);
        $request = ServiceRequest::create(['homeowner_id' => $owner->id, 'request_type' => CohabitationCertificateData::TYPE, 'subject' => 'Synthetic race', 'details' => 'Request details',
            'status' => 'Approved', 'cohabitant_one' => $resident->full_name, 'cohabitant_two' => 'Second Resident', 'address_snapshot' => $owner->only(['house_number', 'street', 'block', 'lot', 'phase', 'phase_suffix'])]);
        $data = ['homeowner_id' => $owner->id, 'type' => CohabitationCertificateData::TYPE, 'purpose' => 'Request details', 'cohabitant_one' => $resident->full_name,
            'cohabitant_two' => 'Second Resident', 'cohabitant_one_birth_date' => '1990-01-01', 'cohabitant_two_birth_date' => '1991-01-01', 'years_together' => 5,
            'requester' => 'one'];
        app(CohabitationCertificateSource::class)->confirm(['reviewed' => true, 'confirmed' => true, 'note' => 'Synthetic concurrency approval ONLY.'], $admin);
        $token = app(ResidencyCertificatePreviews::class)->prepare($data, $admin, $request->id);
        $configuration = ['filesystems.disks.local.root' => Storage::disk('local')->path(''), 'certificates.cohabitation_source_directory' => config('certificates.cohabitation_source_directory'),
            'certificates.cohabitation_font' => config('certificates.cohabitation_font')];
        $script = 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); '
            .'Carbon\\Carbon::setTestNow("2026-10-21 10:00:00"); config('.var_export($configuration, true).'); '
            .'Illuminate\\Support\\Facades\\Notification::fake(); usleep(200000); '
            .'$certificate = app(App\\Actions\\Certificates\\IssueCertificate::class)->fromApprovedRequest('
            .'App\\Models\\ServiceRequest::findOrFail('.$request->id.'), App\\Models\\User::findOrFail('.$admin->id.'), "Request details", '.var_export($token, true).', '.var_export($data, true).'); '
            .'echo json_encode(["id" => $certificate->id]);';
        $processes = [];
        for ($i = 0; $i < 2; $i++) {
            $process = new Process([PHP_BINARY, '-r', $script], base_path(), timeout: 120);
            $process->start();
            $processes[] = $process;
        }
        $ids = [];
        foreach ($processes as $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
            $ids[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR)['id'];
        }
        $this->assertSame($ids[0], $ids[1]);
        $this->assertDatabaseCount('certificates', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles('certificates'));
        $this->assertSame('Completed', $request->fresh()->status);
        $this->assertSame($owner->id, Certificate::sole()->homeowner_id);
    }
}
