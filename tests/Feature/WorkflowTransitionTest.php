<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\Homeowner;
use App\Models\ServiceRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    private function homeowner(): Homeowner
    {
        $user = User::factory()->create(['account_status' => 'Active']);
        $user->assignRole('homeowner');

        return $user->homeowner()->create(['house_number' => (string) $user->id, 'street' => 'Workflow Street', 'block' => (string) $user->id, 'lot' => (string) $user->id, 'phase' => 'Southville Phase I', 'residency_date' => '2021-01-01', 'ownership_type' => 'Owner', 'emergency_contact_name' => 'Contact', 'emergency_contact_number' => '09123456789', 'status' => 'Active']);
    }
}
