<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\ServiceRequests\UpdateServiceRequest;
use App\Filament\Resources\Certificates\Pages\CreateCertificate;
use App\Filament\Resources\ServiceRequests\Pages\EditServiceRequest;
use App\Models\AuditLog;
use App\Models\Certificate;
use App\Models\Homeowner;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\WorkflowStatusChanged;
use App\Services\CohabitationCertificateData;
use App\Services\CohabitationCertificateSource;
use App\Services\ServiceRequestWorkflow;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\UsesSyntheticCohabitation;
use Tests\TestCase;

final class ServiceRequestEditingTest extends TestCase
{
    use RefreshDatabase;
    use UsesSyntheticCohabitation;

    private User $admin;

    private User $staff;

    private Homeowner $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 22));
        $this->seed(RolesAndPermissionsSeeder::class);
        Notification::fake();
        Storage::fake('local');
        $this->admin = User::factory()->create();
        $this->admin->assignRole('hoa_admin');
        $this->staff = User::factory()->create();
        $this->staff->assignRole('hoa_staff');
        $resident = User::factory()->create(['first_name' => 'Synthetic', 'last_name' => 'Resident', 'middle_name' => null, 'date_of_birth' => '1990-01-01']);
        $resident->assignRole('homeowner');
        $this->owner = $resident->homeowner()->create(['street' => 'Test Street', 'block' => '1', 'lot' => '2',
            'phase' => 'Southville Phase I', 'phase_suffix' => 'A', 'residency_date' => '2020-01-01', 'ownership_type' => 'Owner', 'status' => 'Active']);
    }

    private function requestRecord(string $status = 'Pending', string $type = CohabitationCertificateData::TYPE): ServiceRequest
    {
        $request = ServiceRequest::create(['homeowner_id' => $this->owner->id, 'request_type' => $type,
            'subject' => 'Synthetic edit request', 'details' => 'Original request details.', 'status' => $status, 'handled_by' => $this->staff->id,
            'cohabitant_one' => $this->owner->user->full_name, 'cohabitant_two' => 'Verified Partner',
            'address_snapshot' => $this->owner->only(['house_number', 'street', 'block', 'lot', 'phase', 'phase_suffix'])]);
        Notification::fake();

        return $request;
    }

    private function login(User $actor, string $panel): void
    {
        foreach (['admin', 'staff', 'homeowner', 'web'] as $guard) {
            Auth::guard($guard)->logout();
        }
        $this->actingAs($actor, $panel);
        Filament::setCurrentPanel($panel);
    }

    public function test_both_panels_show_only_the_current_status_and_permitted_next_steps(): void
    {
        $matrix = ['Pending' => ['Pending', 'Processing', 'Rejected'], 'Processing' => ['Processing', 'Approved', 'Rejected'],
            'Approved' => ['Approved'], 'Completed' => ['Completed'], 'Rejected' => ['Rejected']];
        foreach ([[$this->admin, 'admin'], [$this->staff, 'staff']] as [$actor, $panel]) {
            $this->login($actor, $panel);
            foreach ($matrix as $status => $options) {
                $record = $this->requestRecord($status);
                Livewire::test(EditServiceRequest::class, ['record' => $record->id])
                    ->assertFormFieldExists('status', fn (Select $field): bool => array_keys($field->getOptions()) === $options);
            }
        }
    }

    public function test_staff_can_process_then_approve_without_reloading_the_edit_page(): void
    {
        $record = $this->requestRecord();
        $original = $record->fresh()->only(['homeowner_id', 'request_type', 'cohabitant_one', 'cohabitant_two', 'address_snapshot']);
        $this->login($this->staff, 'staff');
        $page = Livewire::test(EditServiceRequest::class, ['record' => $record->id])
            ->fillForm(['status' => 'Processing', 'admin_remarks' => 'Review started.'])->call('save', false)
            ->assertHasNoFormErrors()->assertNotified('Saved')
            ->assertFormFieldExists('status', fn (Select $field): bool => array_keys($field->getOptions()) === ['Processing', 'Approved', 'Rejected']);
        $this->assertSame('Processing', $record->fresh()->status);
        $this->assertSame($this->staff->id, $record->fresh()->processed_by);
        $this->assertNotNull($record->fresh()->processed_at);
        $page->fillForm(['status' => 'Approved', 'admin_remarks' => 'Verified details.'])->call('save', false)
            ->assertHasNoFormErrors()->assertFormFieldExists('status', fn (Select $field): bool => array_keys($field->getOptions()) === ['Approved']);
        $saved = $record->fresh();
        $this->assertSame('Approved', $saved->status);
        $this->assertSame($this->staff->id, $saved->approved_by);
        $this->assertNotNull($saved->approved_at);
        $this->assertNull($saved->completed_at);
        $this->assertSame($original, $saved->only(array_keys($original)));
        Notification::assertSentToTimes($this->owner->user, WorkflowStatusChanged::class, 2);
        $this->assertSame(2, AuditLog::where('auditable_type', ServiceRequest::class)->where('auditable_id', $record->id)->where('action', 'ServiceRequest.updated')->where('user_id', $this->staff->id)->count());
        $this->assertDatabaseCount('certificates', 0);
    }

    public function test_invalid_direct_approval_is_a_visible_field_error_and_preserves_entered_values(): void
    {
        foreach ([[$this->admin, 'admin'], [$this->staff, 'staff']] as [$actor, $panel]) {
            $record = $this->requestRecord();
            $this->login($actor, $panel);
            Livewire::test(EditServiceRequest::class, ['record' => $record->id])
                ->fillForm(['status' => 'Approved', 'admin_remarks' => 'Keep my unsaved remarks.'])->call('save', false)
                ->assertHasFormErrors(['status'])->assertSet('data.admin_remarks', 'Keep my unsaved remarks.')
                ->assertSee('The selected status is invalid.');
            $this->assertSame('Pending', $record->fresh()->status);
            $this->assertNull($record->fresh()->admin_remarks);
            Notification::assertNothingSent();
        }
    }

    public function test_domain_validation_is_mapped_to_the_form_with_a_danger_notification(): void
    {
        $record = $this->requestRecord();
        $this->login($this->staff, 'staff');
        // Simulate the authoritative action rejecting a save after form validation.
        $this->app->instance(UpdateServiceRequest::class, new class
        {
            public function handle(): never
            {
                throw ValidationException::withMessages(['status' => 'A concurrent change requires a fresh workflow step.']);
            }
        });
        Livewire::test(EditServiceRequest::class, ['record' => $record->id])
            ->fillForm(['status' => 'Processing', 'admin_remarks' => 'Do not discard this.'])->call('save', false)
            ->assertHasFormErrors(['status'])->assertNotified('Please review the form')
            ->assertSee('A concurrent change requires a fresh workflow step.')
            ->assertSet('data.admin_remarks', 'Do not discard this.');
        $this->assertSame('Pending', $record->fresh()->status);
    }

    public function test_direct_action_rejects_illegal_and_stale_transitions_without_partial_changes(): void
    {
        foreach ([['Pending', 'Approved'], ['Pending', 'Completed'], ['Processing', 'Pending'], ['Approved', 'Processing'], ['Rejected', 'Processing'], ['Completed', 'Approved']] as [$from, $to]) {
            $record = $this->requestRecord($from);
            $before = $record->fresh()->getAttributes();
            try {
                app(UpdateServiceRequest::class)->handle($record, ['subject' => 'Must roll back', 'details' => 'Must roll back', 'status' => $to], $this->staff);
                $this->fail('An invalid workflow transition must be rejected.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('status', $exception->errors());
            }
            $this->assertSame($before, $record->fresh()->getAttributes());
            Notification::assertNothingSent();
        }
        $stale = $this->requestRecord('Processing');
        DB::table('service_requests')->where('id', $stale->id)->update(['status' => 'Rejected', 'admin_remarks' => 'Concurrent rejection.']);
        try {
            app(UpdateServiceRequest::class)->handle($stale, ['subject' => $stale->subject, 'details' => $stale->details, 'status' => 'Approved'], $this->staff);
            $this->fail('The locked database status, not the stale model, must govern updates.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }
        $this->assertSame('Rejected', $stale->fresh()->status);
        $this->assertNull($stale->fresh()->approved_at);
    }

    public function test_certificates_cannot_be_manually_completed_through_action_or_model(): void
    {
        foreach (['Certificate of Residency', CohabitationCertificateData::TYPE, 'Certificate of Good Standing'] as $type) {
            $record = $this->requestRecord('Approved', $type);
            foreach (['action', 'model'] as $path) {
                try {
                    $path === 'action'
                        ? app(UpdateServiceRequest::class)->handle($record, ['subject' => $record->subject, 'details' => $record->details, 'status' => 'Completed'], $this->staff)
                        : $record->fresh()->update(['status' => 'Completed']);
                    $this->fail('Certificate completion requires issuance.');
                } catch (ValidationException $exception) {
                    $this->assertSame([__(ServiceRequestWorkflow::COMPLETION_MESSAGE)], $exception->errors()['status']);
                }
            }
            $this->assertSame('Approved', $record->fresh()->status);
            $this->assertNull($record->fresh()->completed_at);
        }
        $this->assertDatabaseCount('certificates', 0);
    }

    public function test_grandfathered_non_certificate_services_can_still_be_completed(): void
    {
        $record = $this->requestRecord('Approved', 'Repair/Maintenance');
        DB::table('service_requests')->where('id', $record->id)->update(['grandfathered' => true]);
        $this->login($this->staff, 'staff');
        Livewire::test(EditServiceRequest::class, ['record' => $record->id])
            ->assertFormFieldExists('status', fn (Select $field): bool => array_keys($field->getOptions()) === ['Approved', 'Completed'])
            ->fillForm(['status' => 'Completed', 'admin_remarks' => 'The repair is complete.'])->call('save', false)->assertHasNoFormErrors();
        $this->assertSame('Completed', $record->fresh()->status);
        $this->assertNotNull($record->fresh()->completed_at);
        $this->assertTrue($record->fresh()->grandfathered);
    }

    public function test_rejection_requires_remarks_in_both_form_and_domain_action(): void
    {
        $record = $this->requestRecord();
        $this->login($this->staff, 'staff');
        $page = Livewire::test(EditServiceRequest::class, ['record' => $record->id])
            ->fillForm(['status' => 'Rejected', 'admin_remarks' => ''])->call('save', false)->assertHasFormErrors(['admin_remarks']);
        try {
            app(UpdateServiceRequest::class)->handle($record, ['subject' => $record->subject, 'details' => $record->details, 'status' => 'Rejected', 'admin_remarks' => '  '], $this->staff);
            $this->fail('A direct rejection also requires readable remarks.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('admin_remarks', $exception->errors());
        }
        $page->fillForm(['status' => 'Rejected', 'admin_remarks' => 'Please supply the missing information.'])->call('save', false)->assertHasNoFormErrors();
        $this->assertSame('Rejected', $record->fresh()->status);
    }

    public function test_ordinary_edits_keep_current_status_and_existing_authorization(): void
    {
        foreach ([[$this->admin, 'admin'], [$this->staff, 'staff']] as [$actor, $panel]) {
            $this->login($actor, $panel);
            foreach (ServiceRequestWorkflow::statuses() as $status) {
                $record = $this->requestRecord($status);
                Livewire::test(EditServiceRequest::class, ['record' => $record->id])
                    ->fillForm(['subject' => 'Updated subject', 'details' => 'Updated description.', 'admin_remarks' => 'Updated remarks.'])
                    ->call('save', false)->assertHasNoFormErrors();
                $this->assertSame($status, $record->fresh()->status);
                $this->assertSame('Updated subject', $record->fresh()->subject);
                Notification::assertNothingSent();
            }
        }
    }

    public function test_unauthorized_or_inactive_actors_cannot_save_through_the_action(): void
    {
        $record = $this->requestRecord();
        $noPermission = User::factory()->create();
        $noPermission->assignRole('hoa_staff');
        $noPermission->roles()->first()->revokePermissionTo('manage_requests');
        $inactive = User::factory()->create(['account_status' => 'Inactive']);
        $inactive->assignRole('hoa_admin');
        foreach ([$noPermission, $inactive, $this->owner->user] as $actor) {
            try {
                app(UpdateServiceRequest::class)->handle($record, ['subject' => $record->subject, 'details' => $record->details, 'status' => 'Processing'], $actor);
                $this->fail('Existing permissions must remain authoritative.');
            } catch (AuthorizationException) {
                $this->assertSame('Pending', $record->fresh()->status);
            }
        }
    }

    public function test_cohabitation_issuance_still_completes_the_approved_request_once(): void
    {
        $this->syntheticCohabitationSource();
        app(CohabitationCertificateSource::class)->confirm(['reviewed' => true, 'confirmed' => true, 'note' => 'Synthetic test approval only.'], $this->admin);
        $record = $this->requestRecord('Approved');
        $this->login($this->staff, 'staff');
        Livewire::withQueryParams(['request' => $record->id])->test(CreateCertificate::class)
            ->set('data.cohabitant_two_birth_date', '1991-01-01')->set('data.years_together', 5)
            ->call('preparePreview')->assertHasNoErrors()->call('create')->assertHasNoErrors();
        $certificate = Certificate::sole();
        $this->assertSame('Completed', $record->fresh()->status);
        $this->assertSame($certificate->certificate_number, $record->fresh()->document_output);
        $this->assertNotNull($record->fresh()->completed_at);
        Storage::disk('local')->assertExists($certificate->file_path);
    }

    public function test_filipino_workflow_guidance_and_errors_keep_stored_statuses_unchanged(): void
    {
        $record = $this->requestRecord();
        $this->login($this->staff, 'staff');
        app()->setLocale('fil');
        Livewire::test(EditServiceRequest::class, ['record' => $record->id])
            ->assertSee('I-save muna bilang Pinoproseso bago aprubahan.')
            ->assertFormFieldExists('status', fn (Select $field): bool => array_keys($field->getOptions()) === ['Pending', 'Processing', 'Rejected']);
        try {
            app(UpdateServiceRequest::class)->handle($record, ['subject' => $record->subject, 'details' => $record->details, 'status' => 'Approved'], $this->staff);
            $this->fail('Direct approval must still be blocked in Filipino.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Hindi maaaring baguhin ang katayuan', $exception->errors()['status'][0]);
        }
        $this->assertSame('Pending', $record->fresh()->status);
    }
}
