<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Complaints\CreateComplaint as CreateComplaintAction;
use App\Filament\Resources\Complaints\Pages\CreateComplaint;
use App\Models\Complaint;
use App\Models\Homeowner;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification as LaravelNotification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

final class AdminComplaintCreationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
        LaravelNotification::fake();
    }

    public function test_admin_form_creates_a_pending_assigned_complaint_with_private_attachments(): void
    {
        $admin = $this->userWithRole('hoa_admin');
        $staff = $this->userWithRole('hoa_staff');
        $homeowner = $this->homeowner('Inactive');
        $this->actingAs($admin, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(CreateComplaint::class)
            ->fillForm([
                'homeowner_id' => $homeowner->id,
                'subject' => '  Street drainage concern  ',
                'category' => 'Common Area',
                'priority' => 'High',
                'description' => '  Water remains in the drainage channel after every rainfall.  ',
                'handled_by' => $staff->id,
                'attachments' => [UploadedFile::fake()->create('evidence.pdf', 120, 'application/pdf')],
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified('Complaint created successfully');

        $complaint = Complaint::query()->sole();
        $this->assertSame($homeowner->id, $complaint->homeowner_id);
        $this->assertSame('Street drainage concern', $complaint->subject);
        $this->assertSame('Water remains in the drainage channel after every rainfall.', $complaint->description);
        $this->assertSame('Pending', $complaint->status);
        $this->assertSame($staff->id, $complaint->handled_by);
        $this->assertMatchesRegularExpression('/^CMP-\d{4}-[A-Z0-9]{8}$/', $complaint->ticket_number);

        $attachment = $complaint->caseAttachments()->sole();
        Storage::disk('local')->assertExists($attachment->path);
        $this->assertSame('evidence.pdf', $attachment->original_name);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'Complaint.created',
            'auditable_id' => $complaint->id,
        ]);
    }

    public function test_admin_form_shows_readable_required_field_errors(): void
    {
        $admin = $this->userWithRole('hoa_admin');
        $this->actingAs($admin, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(CreateComplaint::class)
            ->fillForm([
                'homeowner_id' => null,
                'subject' => '',
                'category' => null,
                'priority' => null,
                'description' => 'Too short',
            ])
            ->call('create')
            ->assertHasFormErrors([
                'homeowner_id' => 'required',
                'subject' => 'required',
                'category' => 'required',
                'priority' => 'required',
                'description' => 'min',
            ]);

        $this->assertDatabaseCount('complaints', 0);
    }

    public function test_admin_can_create_for_each_current_resident_account_status(): void
    {
        $admin = $this->userWithRole('hoa_admin');

        foreach (['Pending', 'Active', 'Suspended', 'Inactive', 'Rejected'] as $index => $status) {
            $homeowner = $this->homeowner($status);
            app(CreateComplaintAction::class)->handle($homeowner, [
                'subject' => "Complaint for {$status} resident",
                'category' => 'Others',
                'priority' => 'Medium',
                'description' => "This complaint was recorded for a current {$status} resident account.",
            ], uploader: $admin);
        }

        $this->assertDatabaseCount('complaints', 5);
        $this->assertSame(['Pending'], Complaint::query()->distinct()->pluck('status')->all());
    }

    public function test_action_rejects_forged_workflow_fields_invalid_assignees_and_deleted_residents(): void
    {
        $admin = $this->userWithRole('hoa_admin');
        $homeowner = $this->homeowner('Active');
        $nonStaff = User::factory()->create(['account_status' => 'Active']);
        $inactiveStaff = $this->userWithRole('hoa_staff');
        $inactiveStaff->forceFill(['account_status' => 'Inactive'])->save();

        try {
            app(CreateComplaintAction::class)->handle($homeowner, [
                ...$this->validComplaintData(),
                'category' => 'Forged category',
                'priority' => 'Critical',
                'handled_by' => $nonStaff->id,
                'status' => 'Resolved',
                'admin_remarks' => 'Forged resolution.',
            ], uploader: $admin);
            $this->fail('Forged workflow fields and a non-staff assignee must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('handled_by', $exception->errors());
            $this->assertArrayHasKey('category', $exception->errors());
            $this->assertArrayHasKey('priority', $exception->errors());
            $this->assertArrayHasKey('status', $exception->errors());
            $this->assertArrayHasKey('admin_remarks', $exception->errors());
        }

        try {
            app(CreateComplaintAction::class)->handle($homeowner, [
                ...$this->validComplaintData(),
                'handled_by' => $inactiveStaff->id,
            ], uploader: $admin);
            $this->fail('An inactive staff member must not be assignable.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('handled_by', $exception->errors());
        }

        $homeowner->user->delete();
        try {
            app(CreateComplaintAction::class)->handle($homeowner, $this->validComplaintData(), uploader: $admin);
            $this->fail('A deleted resident must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('homeowner_id', $exception->errors());
        }

        $this->assertDatabaseCount('complaints', 0);
    }

    public function test_attachment_storage_failure_rolls_back_the_complaint_and_returns_a_field_error(): void
    {
        $admin = $this->userWithRole('hoa_admin');
        $homeowner = $this->homeowner('Active');
        $disk = Mockery::mock();
        $disk->shouldReceive('putFileAs')->once()->andReturn(false);
        $disk->shouldReceive('delete')->twice()->andReturnTrue();
        Storage::shouldReceive('disk')->with('local')->times(3)->andReturn($disk);

        try {
            app(CreateComplaintAction::class)->handle(
                $homeowner,
                $this->validComplaintData(),
                [UploadedFile::fake()->create('evidence.pdf', 100, 'application/pdf')],
                $admin,
            );
            $this->fail('A failed attachment write must reject complaint creation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('attachments', $exception->errors());
        }

        $this->assertDatabaseCount('complaints', 0);
        $this->assertDatabaseCount('case_attachments', 0);
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
            'email' => "complaint-resident-{$index}@example.test",
            'account_status' => $status,
        ]);
        $user->assignRole('homeowner');

        return $user->homeowner()->create([
            'house_number' => (string) $index,
            'street' => 'Complaint Street',
            'block' => 'C',
            'lot' => (string) $index,
            'phase' => 'Southville Phase I',
            'residency_date' => '2021-01-01',
            'ownership_type' => 'Owner',
            'status' => $status,
        ]);
    }

    /** @return array<string, string> */
    private function validComplaintData(): array
    {
        return [
            'subject' => 'Street drainage concern',
            'category' => 'Common Area',
            'priority' => 'High',
            'description' => 'Water remains in the drainage channel after every rainfall.',
        ];
    }
}
