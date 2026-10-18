<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\Homeowner;
use App\Models\ServiceRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class WorkflowTransitionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_complaint_transition_order_and_terminal_notes_are_enforced(): void
    {
        $complaint = Complaint::query()->create(['homeowner_id' => $this->homeowner()->id, 'subject' => 'Drainage concern', 'description' => 'Water remains in the drainage channel after rainfall.', 'category' => 'Common Area', 'priority' => 'High', 'status' => 'Pending']);

        try {
            $complaint->update(['status' => 'Resolved', 'admin_remarks' => 'Fixed.']);
            $this->fail('A complaint cannot skip review.');
        } catch (ValidationException) {
            $this->assertSame('Pending', $complaint->refresh()->status);
        }

        $complaint->update(['status' => 'Under Review']);
        try {
            $complaint->update(['status' => 'Resolved']);
            $this->fail('Resolution notes are required.');
        } catch (ValidationException) {
            $this->assertSame('Under Review', $complaint->refresh()->status);
        }

        $complaint->update(['status' => 'Resolved', 'admin_remarks' => 'Drain cleared and inspected.']);
        $this->assertNotNull($complaint->refresh()->resolved_at);
    }

    public function test_request_rejection_requires_response_notes(): void
    {
        $request = ServiceRequest::query()->create(['homeowner_id' => $this->homeowner()->id, 'request_type' => 'Gate Pass', 'details' => 'Visitor request.', 'status' => 'Pending']);

        try {
            $request->update(['status' => 'Rejected']);
            $this->fail('Rejection notes are required.');
        } catch (ValidationException) {
            $this->assertSame('Pending', $request->refresh()->status);
        }

        $request->update(['status' => 'Rejected', 'admin_remarks' => 'Please provide the visitor dates.']);
        $this->assertSame('Rejected', $request->refresh()->status);
    }

    public function test_assigned_staff_can_progress_complaints_through_the_workflow(): void
    {
        $staff = User::factory()->create(['account_status' => 'Active']);
        $staff->assignRole('hoa_staff');
        $complaint = Complaint::query()->create([
            'homeowner_id' => $this->homeowner()->id,
            'handled_by' => $staff->id,
            'subject' => 'Streetlight concern',
            'description' => 'The streetlight near the entrance is not working.',
            'category' => 'Common Area',
            'priority' => 'High',
            'status' => 'Pending',
        ]);

        $this->assertTrue(Gate::forUser($staff)->allows('update', $complaint));

        $this->actingAs($staff);
        $complaint->update(['status' => 'Under Review']);
        $complaint->update(['status' => 'Resolved', 'admin_remarks' => 'The bulb was replaced.']);

        $this->assertSame('Resolved', $complaint->refresh()->status);
        $this->assertSame($staff->id, $complaint->handled_by);
        $this->assertNotNull($complaint->resolved_at);
    }

    public function test_only_assigned_staff_or_admin_can_update_a_complaint(): void
    {
        $assigned = User::factory()->create(['account_status' => 'Active']);
        $assigned->assignRole('hoa_staff');
        $otherStaff = User::factory()->create(['account_status' => 'Active']);
        $otherStaff->assignRole('hoa_staff');
        $complaint = Complaint::query()->create([
            'homeowner_id' => $this->homeowner()->id,
            'handled_by' => $assigned->id,
            'subject' => 'Assigned complaint',
            'description' => 'This complaint is assigned to one staff member.',
            'category' => 'Security',
            'priority' => 'Medium',
            'status' => 'Under Review',
        ]);

        $this->assertTrue(Gate::forUser($assigned)->allows('update', $complaint));
        $this->assertFalse(Gate::forUser($otherStaff)->allows('update', $complaint));
    }

    private function homeowner(): Homeowner
    {
        $user = User::factory()->create(['account_status' => 'Active']);
        $user->assignRole('homeowner');

        return $user->homeowner()->create(['house_number' => (string) $user->id, 'street' => 'Workflow Street', 'block' => (string) $user->id, 'lot' => (string) $user->id, 'phase' => 'Southville Phase I', 'residency_date' => '2021-01-01', 'ownership_type' => 'Owner', 'emergency_contact_name' => 'Contact', 'emergency_contact_number' => '09123456789', 'status' => 'Active']);
    }
}
