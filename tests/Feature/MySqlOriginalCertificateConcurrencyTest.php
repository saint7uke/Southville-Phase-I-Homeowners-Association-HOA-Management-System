<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\CertificateTemplates;
use App\Services\ResidencyCertificatePreviews;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class MySqlOriginalCertificateConcurrencyTest extends TestCase
{
    use DatabaseMigrations {
        runDatabaseMigrations as private runDisposableMigrations;
    }

    public function runDatabaseMigrations(): void
    {
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Certificate row-lock concurrency requires the separately configured disposable MySQL test database.');
        }
        $font = config('certificates.residency_font');
        if (! is_string($font) || ! is_file($font)) {
            $this->markTestSkipped('Requires the licensed original Times New Roman Bold font; no substitute is used in concurrency acceptance.');
        }
        $this->runDisposableMigrations();
    }

    public function test_two_issuers_can_create_only_one_original_certificate_for_an_approved_request(): void
    {
        $this->travelTo(Carbon::parse('2026-10-20 10:00:00'));
        Notification::fake();
        Storage::set('local', Storage::fake('certificate-race-'.getmypid()));
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('hoa_admin');
        $resident = User::factory()->create();
        $resident->assignRole('homeowner');
        $owner = $resident->homeowner()->create(['house_number' => '9', 'street' => 'Test Street', 'block' => '12', 'lot' => '34', 'phase' => 'Southville Phase I', 'residency_date' => '2020-01-01', 'ownership_type' => 'Owner', 'status' => 'Active']);
        $service = app(CertificateTemplates::class);
        $template = $service->createOriginalResidencyDraft(true, $admin);
        $service->configureSignatory(['name' => 'JOSEPHINE ALARZAR', 'title' => 'SHOAi PRESIDENT', 'confirmed' => true, 'note' => 'Synthetic signatory approval for concurrency test.'], $admin);
        $service->approve($template->id, 'Synthetic template approval for concurrency test.', true, $admin);
        $request = ServiceRequest::create(['homeowner_id' => $owner->id, 'request_type' => 'Certificate of Residency', 'subject' => 'Concurrent issuance', 'details' => 'Barangay clearance', 'status' => 'Approved']);
        $token = app(ResidencyCertificatePreviews::class)->prepare(['homeowner_id' => $owner->id, 'purpose' => 'Barangay clearance'], $admin, $request->id);
        $root = var_export(Storage::disk('local')->path(''), true);
        $processes = [];
        for ($i = 0; $i < 2; $i++) {
            $script = 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; '
                .'$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); '
                .'Carbon\\Carbon::setTestNow("2026-10-20 10:00:00"); '
                .'config(["filesystems.disks.local.root" => '.$root.']); '
                .'Illuminate\\Support\\Facades\\Notification::fake(); usleep(200000); '
                .'try { $certificate = app(App\\Actions\\Certificates\\IssueCertificate::class)->fromApprovedRequest('
                .'App\\Models\\ServiceRequest::findOrFail('.$request->id.'), App\\Models\\User::findOrFail('.$admin->id.'), "Barangay clearance", '.var_export($token, true).'); '
                .'echo json_encode(["status" => "issued", "id" => $certificate->id]); } '
                .'catch (Illuminate\\Auth\\Access\\AuthorizationException $e) { echo json_encode(["status" => "rejected"]); }';
            $process = new Process([PHP_BINARY, '-r', $script], base_path(), timeout: 120);
            $process->start();
            $processes[] = $process;
        }
        $results = array_map(function (Process $process): array {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());

            return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        }, $processes);
        $this->assertSame(['issued', 'issued'], array_column($results, 'status'));
        $this->assertSame($results[0]['id'], $results[1]['id']);
        $this->assertSame(1, Certificate::where('service_request_id', $request->id)->count());
        $this->assertSame('Completed', $request->fresh()->status);
        $this->assertCount(1, Storage::disk('local')->allFiles('certificates'));
        $this->assertSame($template->id, Certificate::sole()->certificate_template_id);
    }
}
