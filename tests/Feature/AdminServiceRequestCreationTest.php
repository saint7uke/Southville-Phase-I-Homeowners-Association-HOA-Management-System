<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\ServiceRequests\CreateServiceRequest as CreateServiceRequestAction;
use App\Filament\Resources\ServiceRequests\Pages\CreateServiceRequest;
use App\Models\Homeowner;
use App\Models\ServiceRequest;
use App\Models\SystemSetting;
use App\Models\User;
use App\Notifications\OperationalAlert;
use App\Notifications\WorkflowStatusChanged;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

final class AdminServiceRequestCreationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
        Notification::fake();
    }

    public function test_admin_form_creates_a_pending_assigned_request_with_private_attachments(): void
    {
        $admin = $this->userWithRole('hoa_admin');
        $staff = $this->userWithRole('hoa_staff');
        $homeowner = $this->homeowner('Inactive');
        $this->actingAs($admin, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(CreateServiceRequest::class)
            ->fillForm([
                'homeowner_id' => $homeowner->id,
                'request_type' => 'Certificate of Residency',
                'subject' => '  Residency certificate request  ',
                'details' => '  The resident needs this certificate for an official transaction.  ',
                'handled_by' => $staff->id,
                'attachments' => [UploadedFile::fake()->create('supporting-document.pdf', 120, 'application/pdf')],
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified('Service request created successfully');

        $request = ServiceRequest::query()->sole();
        $this->assertSame($homeowner->id, $request->homeowner_id);
        $this->assertSame('Certificate of Residency', $request->request_type);
        $this->assertSame('Residency certificate request', $request->subject);
        $this->assertSame('The resident needs this certificate for an official transaction.', $request->details);
        $this->assertSame('Pending', $request->status);
        $this->assertSame($staff->id, $request->handled_by);
        $this->assertMatchesRegularExpression('/^REQ-\d{4}-[A-Z0-9]{8}$/', $request->ticket_number);

        $attachment = $request->caseAttachments()->sole();
        Storage::disk('local')->assertExists($attachment->path);
        $this->assertSame('supporting-document.pdf', $attachment->original_name);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'ServiceRequest.created',
            'auditable_id' => $request->id,
        ]);
        Notification::assertSentTo($homeowner->user, WorkflowStatusChanged::class);
        Notification::assertSentTo($staff, OperationalAlert::class);
    }

    public function test_admin_form_shows_required_field_errors(): void
    {
        $admin = $this->userWithRole('hoa_admin');
        $this->actingAs($admin, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(CreateServiceRequest::class)
            ->fillForm([
                'homeowner_id' => null,
                'request_type' => null,
                'subject' => '',
                'details' => '',
            ])
            ->call('create')
            ->assertHasFormErrors([
                'homeowner_id' => 'required',
                'request_type' => 'required',
                'subject' => 'required',
                'details' => 'required',
            ]);

        $this->assertDatabaseCount('service_requests', 0);
    }

    public function test_admin_can_create_for_each_current_resident_account_status(): void
    {
        $admin = $this->userWithRole('hoa_admin');

        foreach (['Pending', 'Active', 'Suspended', 'Inactive', 'Rejected'] as $status) {
            app(CreateServiceRequestAction::class)->handle($this->homeowner($status), [
                'request_type' => 'Repair/Maintenance',
                'subject' => "Request for {$status} resident",
                'details' => "This request was recorded for a current {$status} resident account.",
            ], uploader: $admin);
        }

        $this->assertDatabaseCount('service_requests', 5);
        $this->assertSame(['Pending'], ServiceRequest::query()->distinct()->pluck('status')->all());
    }

    public function test_action_normalizes_request_type_and_rejects_forged_fields(): void
    {
        $admin = $this->userWithRole('hoa_admin');
        $homeowner = $this->homeowner('Active');

        $request = app(CreateServiceRequestAction::class)->handle($homeowner, [
            ...$this->validRequestData(),
            'request_type' => '  certificate OF residency  ',
        ], uploader: $admin);
        $this->assertSame('Certificate of Residency', $request->request_type);

        try {
            app(CreateServiceRequestAction::class)->handle($homeowner, [
                ...$this->validRequestData(),
                'request_type' => 'Forged request type',
                'status' => 'Completed',
                'admin_remarks' => 'Forged response.',
                'document_output' => 'private/certificate.pdf',
                'completed_at' => now(),
                'ticket_number' => 'FORGED',
                'homeowner_id' => $homeowner->id,
            ], uploader: $admin);
            $this->fail('Forged request and workflow fields must be rejected.');
        } catch (ValidationException $exception) {
            foreach (['request_type', 'status', 'admin_remarks', 'document_output', 'completed_at', 'ticket_number', 'homeowner_id'] as $field) {
                $this->assertArrayHasKey($field, $exception->errors());
            }
        }

        $this->assertDatabaseCount('service_requests', 1);
    }

    public function test_action_rejects_invalid_assignees_and_deleted_residents(): void
    {
        $admin = $this->userWithRole('hoa_admin');
        $homeowner = $this->homeowner('Active');
        $nonStaff = User::factory()->create(['account_status' => 'Active']);
        $inactiveStaff = $this->userWithRole('hoa_staff');
        $inactiveStaff->forceFill(['account_status' => 'Inactive'])->save();

        foreach ([$nonStaff, $inactiveStaff] as $invalidAssignee) {
            try {
                app(CreateServiceRequestAction::class)->handle($homeowner, [
                    ...$this->validRequestData(),
                    'handled_by' => $invalidAssignee->id,
                ], uploader: $admin);
                $this->fail('Only active HOA staff may be assigned.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('handled_by', $exception->errors());
            }
        }

        $homeowner->user->delete();
        try {
            app(CreateServiceRequestAction::class)->handle($homeowner, $this->validRequestData(), uploader: $admin);
            $this->fail('A deleted resident must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('homeowner_id', $exception->errors());
        }

        $deletedHomeowner = $this->homeowner('Active');
        $deletedHomeowner->delete();
        try {
            app(CreateServiceRequestAction::class)->handle($deletedHomeowner, $this->validRequestData(), uploader: $admin);
            $this->fail('A deleted homeowner record must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('homeowner_id', $exception->errors());
        }

        $this->assertDatabaseCount('service_requests', 0);
    }

    public function test_action_rejects_invalid_lengths_excessive_attachments_and_staff_creation(): void
    {
        $admin = $this->userWithRole('hoa_admin');
        $staff = $this->userWithRole('hoa_staff');
        $homeowner = $this->homeowner('Active');

        try {
            app(CreateServiceRequestAction::class)->handle($homeowner, [
                'request_type' => 'Repair/Maintenance',
                'subject' => str_repeat('S', 161),
                'details' => str_repeat('D', 3001),
            ], uploader: $admin);
            $this->fail('Overlong request fields must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('subject', $exception->errors());
            $this->assertArrayHasKey('details', $exception->errors());
        }

        $files = collect(range(1, 4))
            ->map(fn (int $number): UploadedFile => UploadedFile::fake()->create("file-{$number}.pdf", 10, 'application/pdf'))
            ->all();
        try {
            app(CreateServiceRequestAction::class)->handle($homeowner, $this->validRequestData(), $files, $admin);
            $this->fail('More than three attachments must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('attachments', $exception->errors());
        }

        $this->expectException(AuthorizationException::class);
        app(CreateServiceRequestAction::class)->handle($homeowner, $this->validRequestData(), uploader: $staff);
    }

    public function test_attachment_storage_failure_rolls_back_request_and_files(): void
    {
        $admin = $this->userWithRole('hoa_admin');
        $homeowner = $this->homeowner('Active');
        $disk = Mockery::mock();
        $disk->shouldReceive('putFileAs')->once()->andReturn(false);
        $disk->shouldReceive('delete')->twice()->andReturnTrue();
        Storage::shouldReceive('disk')->with('local')->times(3)->andReturn($disk);

        try {
            app(CreateServiceRequestAction::class)->handle(
                $homeowner,
                $this->validRequestData(),
                [UploadedFile::fake()->create('evidence.pdf', 100, 'application/pdf')],
                $admin,
            );
            $this->fail('A failed attachment write must reject service request creation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('attachments', $exception->errors());
        }

        $this->assertDatabaseCount('service_requests', 0);
        $this->assertDatabaseCount('case_attachments', 0);
    }

    public function test_request_notifications_can_be_disabled(): void
    {
        $admin = $this->userWithRole('hoa_admin');
        $staff = $this->userWithRole('hoa_staff');
        $homeowner = $this->homeowner('Active');
        SystemSetting::current()->update(['request_notifications' => false]);

        app(CreateServiceRequestAction::class)->handle($homeowner, $this->validRequestData(), uploader: $admin);

        Notification::assertNothingSent();
        $this->assertDatabaseCount('service_requests', 1);
        $this->assertNotNull($staff->id);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['account_status' => 'Active']);
        $user->assignRole($role);

        return $user;
    }

    private function homeowner(string $status): Homeowner
    {
        $index = User::query()->count() + 1;
        $user = User::factory()->create([
            'email' => "request-resident-{$index}@example.test",
            'account_status' => $status,
        ]);
        $user->assignRole('homeowner');

        return $user->homeowner()->create([
            'house_number' => (string) $index,
            'street' => 'Request Street',
            'block' => 'R',
            'lot' => (string) $index,
            'phase' => 'Southville Phase I',
            'residency_date' => '2021-01-01',
            'ownership_type' => 'Owner',
            'status' => $status,
        ]);
    }

    /** @return array<string, string> */
    private function validRequestData(): array
    {
        return [
            'request_type' => 'Repair/Maintenance',
            'subject' => 'Streetlight maintenance request',
            'details' => 'Please inspect and repair the streetlight near the property.',
        ];
    }
}
