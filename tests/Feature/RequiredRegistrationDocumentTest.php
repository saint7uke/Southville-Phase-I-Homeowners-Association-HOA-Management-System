<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Homeowners\RegisterHomeowner;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class RequiredRegistrationDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
        Notification::fake();
        Queue::fake();
        Http::preventStrayRequests();
    }

    private function payload(string $kind = 'Owner'): array
    {
        return ['first_name' => 'New', 'last_name' => 'Applicant', 'sex' => 'Female', 'date_of_birth' => '1990-01-01',
            'contact_number' => '09171234567', 'email' => strtolower($kind).'@example.test', 'password' => 'ResidentPass!2026', 'password_confirmation' => 'ResidentPass!2026',
            'phase_suffix' => 'A', 'block' => '77', 'lot' => '88', 'ownership_type' => $kind, 'residency_date' => '2020-01-01', 'privacy_consent' => '1',
            'landlord_name' => 'Confirmed Landlord', 'landlord_contact' => '09181234567', 'lease_start_date' => '2020-01-01', 'lease_end_date' => '2030-01-01'];
    }

    public function test_document_is_required_for_all_ownership_types_and_direct_actions(): void
    {
        foreach (['Owner', 'Co-owner', 'Tenant'] as $kind) {
            $this->postJson(route('homeowner.register.store'), $this->payload($kind))->assertUnprocessable()->assertJsonValidationErrors('verification_document');
            try {
                app(RegisterHomeowner::class)->handle($this->payload($kind));
                $this->fail('Missing document accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('verification_document', $exception->errors());
            }
        }
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('homeowners', 0);
        $this->assertDatabaseCount('email_deliveries', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
        Notification::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_malformed_unsupported_and_oversized_documents_are_rejected_before_writes(): void
    {
        foreach (['proof.pdf', ['proof.pdf'], UploadedFile::fake()->create('proof.pdf', 10, 'text/plain'),
            UploadedFile::fake()->create('proof.exe', 10, 'application/pdf'), UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf')] as $document) {
            $data = [...$this->payload(), 'verification_document' => $document];
            $this->postJson(route('homeowner.register.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('verification_document');
            try {
                app(RegisterHomeowner::class)->handle($data);
                $this->fail('Invalid document accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('verification_document', $exception->errors());
            }
        }
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('email_deliveries', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_supported_documents_are_stored_privately_for_all_ownership_types(): void
    {
        $landlord = User::factory()->create(['first_name' => 'Confirmed', 'last_name' => 'Landlord', 'middle_name' => null, 'suffix' => null, 'account_status' => 'Active']);
        $landlord->assignRole('homeowner');
        $landlord->homeowner()->create(['phase' => 'Southville Phase I', 'phase_suffix' => 'A', 'block' => '77', 'lot' => '88', 'ownership_type' => 'Owner', 'status' => 'Active', 'residency_date' => '2019-01-01']);
        foreach (['Owner' => 'pdf', 'Co-owner' => 'jpg', 'Tenant' => 'png'] as $kind => $extension) {
            $document = $extension === 'pdf' ? UploadedFile::fake()->create('proof.pdf', 10240, 'application/pdf') : UploadedFile::fake()->image('proof.'.$extension);
            $data = [...$this->payload($kind), 'verification_document' => $document];
            if ($kind === 'Owner') {
                $data['block'] = '99';
            }
            $this->post(route('homeowner.register.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
            $owner = User::where('email', $data['email'])->sole()->homeowner;
            $this->assertStringStartsWith('verification_documents/', $owner->verification_document_path);
            Storage::disk('local')->assertExists($owner->verification_document_path);
        }
        $this->assertSame(3, count(Storage::disk('local')->allFiles('verification_documents')));
    }

    public function test_translated_required_message_and_existing_records_are_preserved(): void
    {
        $existing = User::factory()->create()->homeowner()->create(['phase' => 'Southville Phase I', 'phase_suffix' => 'B', 'block' => '1', 'lot' => '2', 'ownership_type' => 'Owner', 'residency_date' => '2020-01-01', 'status' => 'Active', 'verification_document_path' => null]);
        foreach (['en', 'fil'] as $locale) {
            $this->withSession(['locale' => $locale])->postJson(route('homeowner.register.store'), $this->payload())
                ->assertUnprocessable()->assertJsonPath('errors.verification_document.0', $locale === 'en'
                    ? 'Upload a verification document to submit your application.'
                    : 'Mag-upload ng dokumento ng beripikasyon upang maisumite ang iyong aplikasyon.');
            $this->get('/homeowner/register')->assertOk()->assertSee('name="verification_document" type="file" required', false);
        }
        $this->assertNull($existing->fresh()->verification_document_path);
    }

    public function test_jpeg_filename_alias_is_accepted(): void
    {
        $this->post(route('homeowner.register.store'), [...$this->payload(), 'verification_document' => UploadedFile::fake()->image('proof.jpeg')])
            ->assertSessionHasNoErrors()->assertRedirect();
        Storage::disk('local')->assertExists(User::where('email', 'owner@example.test')->sole()->homeowner->verification_document_path);
    }

    public function test_storage_failure_rolls_back_account_and_notification_intents(): void
    {
        $disk = \Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('putFileAs')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);
        try {
            app(RegisterHomeowner::class)->handle([...$this->payload(), 'verification_document' => UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf')]);
            $this->fail('Storage failure must reject registration.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('verification_document', $exception->errors());
        }
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('homeowners', 0);
        $this->assertDatabaseCount('email_deliveries', 0);
        Notification::assertNothingSent();
        Queue::assertNothingPushed();
    }
}
