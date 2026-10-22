<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Payments\RecordPayment;
use App\Models\DuesObligation;
use App\Models\DuesSetting;
use App\Models\Homeowner;
use App\Models\PaymentDocument;
use App\Models\User;
use App\Services\ButawReceipts;
use App\Services\ButawReceiptSource;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class MySqlPropertyAndReceiptConcurrencyTest extends TestCase
{
    use DatabaseMigrations {
        runDatabaseMigrations as private runDisposableMigrations;
    }

    public function runDatabaseMigrations(): void
    {
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Property/receipt row-lock races require a separately configured disposable MySQL test database.');
        }
        $this->runDisposableMigrations();
    }

    public function test_concurrent_owner_claims_ignore_street_spelling_and_create_only_one_account(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $scripts = [];
        foreach ([1, 2] as $index) {
            $data = ['first_name' => 'Race', 'last_name' => 'Owner', 'sex' => 'Male', 'contact_number' => '09171234567', 'date_of_birth' => '1990-01-01',
                'email' => 'owner-race-'.$index.'@example.test', 'password' => 'SyntheticPass!2026', 'phase_suffix' => 'A', 'street' => $index === 1 ? 'Narra Street' : 'Different Road',
                'block' => '7', 'lot' => '8', 'ownership_type' => 'Owner', 'residency_date' => '2020-01-01'];
            $scripts[] = 'try { $user = app(App\\Actions\\Homeowners\\RegisterHomeowner::class)->handle('.var_export($data, true).'); echo json_encode(["status" => "registered", "id" => $user->id]); } '
                .'catch (Illuminate\\Validation\\ValidationException $e) { echo json_encode(["status" => "invalid"]); }';
        }
        $results = $this->race($scripts);
        $this->assertEqualsCanonicalizing(['registered', 'invalid'], array_column($results, 'status'));
        $this->assertSame(1, Homeowner::count());
        $this->assertSame(1, User::where('email', 'like', 'owner-race-%')->count());
    }

    public function test_two_receipt_previews_generate_one_document_without_ledger_changes(): void
    {
        if (! is_file((string) config('certificates.butaw_font'))) {
            $this->markTestSkipped('The original licensed Calibri font is unavailable.');
        }
        $this->seed(RolesAndPermissionsSeeder::class);
        Notification::fake();
        Storage::set('local', Storage::fake('butaw-race-'.getmypid()));
        $admin = User::factory()->create(['first_name' => 'Test', 'middle_name' => null, 'last_name' => 'Collector']);
        $admin->assignRole('hoa_admin');
        $resident = User::factory()->create();
        $resident->assignRole('homeowner');
        $owner = Homeowner::create(['user_id' => $resident->id, 'house_number' => null, 'street' => 'Test Street', 'block' => '7', 'lot' => '8',
            'phase' => 'Southville Phase I', 'phase_suffix' => 'A', 'residency_date' => '2020-01-01', 'ownership_type' => 'Owner', 'status' => 'Active']);
        $schedule = DuesSetting::create(['name' => 'Race monthly dues', 'amount' => '489.00', 'frequency' => 'Monthly', 'starts_on' => '2026-01-01', 'is_active' => true]);
        $due = DuesObligation::create(['homeowner_id' => $owner->id, 'dues_setting_id' => $schedule->id, 'billing_year' => 2026, 'billing_month' => 10,
            'due_date' => '2026-10-31', 'amount_due' => '489.00', 'penalty_amount' => '0.00', 'amount_paid' => '0.00', 'status' => 'Pending']);
        $payment = app(RecordPayment::class)->handle(['dues_obligation_id' => $due->id, 'payment_method' => 'Cash', 'amount_paid' => '100.00', 'payment_date' => '2026-10-20'], $admin);
        app(ButawReceiptSource::class)->approve(['reviewed' => true, 'note' => 'Synthetic approval for isolated concurrency verification only.'], $admin);
        $scripts = [];
        foreach ([1, 2] as $index) {
            $token = app(ButawReceipts::class)->prepare($payment->id, $admin, $owner->id);
            $scripts[] = 'config(["filesystems.disks.local.root" => '.var_export(Storage::disk('local')->path(''), true).']); '
                .'$document = app(App\\Services\\ButawReceipts::class)->generate('.var_export($token, true).', '.$payment->id.', App\\Models\\User::findOrFail('.$admin->id.'), '.$owner->id.'); '
                .'echo json_encode(["status" => "generated", "id" => $document->id]);';
        }
        $results = $this->race($scripts);
        $this->assertSame(['generated', 'generated'], array_column($results, 'status'));
        $this->assertSame($results[0]['id'], $results[1]['id']);
        $this->assertSame(1, PaymentDocument::count());
        $this->assertSame('100.00', $due->fresh()->amount_paid);
        $this->assertCount(1, Storage::disk('local')->allFiles('payment-documents'));
    }

    private function race(array $scripts): array
    {
        $processes = [];
        foreach ($scripts as $script) {
            $prefix = 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); '
                .'Illuminate\\Support\\Facades\\Notification::fake(); usleep(200000); ';
            $process = new Process([PHP_BINARY, '-r', $prefix.$script], base_path(), timeout: 120);
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
