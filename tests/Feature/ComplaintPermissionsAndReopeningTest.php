<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Complaints\ReopenComplaint;
use App\Filament\Resources\Complaints\Pages\EditComplaint;
use App\Filament\Resources\Complaints\Pages\ViewComplaint;
use App\Models\Complaint;
use App\Models\Homeowner;
use App\Models\User;
use App\Notifications\WorkflowStatusChanged;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

final class ComplaintPermissionsAndReopeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_only_admins_and_assigned_staff_can_update_open_complaints(): void
    {
        $admin = $this->userWithRole('hoa_admin');
        $assigned = $this->userWithRole('hoa_staff');
        $otherStaff = $this->userWithRole('hoa_staff');

        foreach (['Pending', 'Under Review'] as $status) {
            $complaint = $this->complaint($assigned, $status);

            $this->assertTrue(Gate::forUser($admin)->allows('update', $complaint));
            $this->assertTrue(Gate::forUser($assigned)->allows('update', $complaint));
            $this->assertFalse(Gate::forUser($otherStaff)->allows('update', $complaint));
        }

        $deleted = $this->complaint($assigned, 'Pending');
        $deleted->delete();

        $this->assertFalse(Gate::forUser($admin)->allows('update', $deleted));
        $this->assertFalse(Gate::forUser($assigned)->allows('update', $deleted));
    }

    public function test_finalized_complaints_are_read_only_for_staff_and_admins(): void
    {
        $admin = $this->userWithRole('hoa_admin');
        $assigned = $this->userWithRole('hoa_staff');

        foreach (['Resolved', 'Rejected', 'Closed', 'Dismissed'] as $status) {
            $complaint = $this->complaint($assigned, $status);

            $this->assertFalse(Gate::forUser($admin)->allows('update', $complaint));
            $this->assertFalse(Gate::forUser($assigned)->allows('update', $complaint));
            $this->assertFalse(Gate::forUser($admin)->allows('delete', $complaint));

            try {
                $complaint->update(['priority' => 'Urgent']);
                $this->fail("{$status} complaints must reject ordinary updates.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('status', $exception->errors());
                $this->assertSame('High', $complaint->refresh()->priority);
            }

            try {
                $complaint->delete();
                $this->fail("{$status} complaints must reject deletion.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('status', $exception->errors());
                $this->assertNull($complaint->refresh()->deleted_at);
            }
        }
    }

    public function test_assigned_staff_can_reopen_a_resolved_complaint_with_an_audited_reason(): void
    {
        $assigned = $this->userWithRole('hoa_staff');
        $complaint = $this->complaint($assigned, 'Resolved');
        $originalRemarks = $complaint->admin_remarks;

        $this->actingAs($assigned);
        $result = app(ReopenComplaint::class)->handle($complaint, $assigned, 'Additional drainage inspection is required.');

        $this->assertSame('Under Review', $result->status);
        $this->assertNull($result->resolved_at);
        $this->assertSame($assigned->id, $result->handled_by);
        $this->assertSame($originalRemarks, $result->admin_remarks);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $assigned->id,
            'action' => 'Complaint.reopened',
            'auditable_type' => Complaint::class,
            'auditable_id' => $complaint->id,
        ]);

        $audit = $complaint->auditLogs()->where('action', 'Complaint.reopened')->firstOrFail();
        $this->assertSame('Resolved', $audit->old_values['status']);
        $this->assertSame('Under Review', $audit->new_values['status']);
        $this->assertSame('Additional drainage inspection is required.', $audit->new_values['reason']);
        Notification::assertSentTo($complaint->homeowner->user, WorkflowStatusChanged::class);
    }

    public function test_admin_can_reopen_a_resolved_complaint_but_unassigned_staff_cannot(): void
    {
        $admin = $this->userWithRole('hoa_admin');
        $assigned = $this->userWithRole('hoa_staff');
        $otherStaff = $this->userWithRole('hoa_staff');
        $complaint = $this->complaint($assigned, 'Resolved');

        $this->assertTrue(Gate::forUser($admin)->allows('reopen', $complaint));
        $this->assertTrue(Gate::forUser($assigned)->allows('reopen', $complaint));
        $this->assertFalse(Gate::forUser($otherStaff)->allows('reopen', $complaint));

        $this->actingAs($otherStaff);
        $this->expectException(AuthorizationException::class);
        app(ReopenComplaint::class)->handle($complaint, $otherStaff, 'Trying to bypass the assigned staff restriction.');
    }

    public function test_only_resolved_complaints_can_be_reopened(): void
    {
        $admin = $this->userWithRole('hoa_admin');

        foreach (['Pending', 'Under Review', 'Rejected', 'Closed', 'Dismissed'] as $status) {
            $complaint = $this->complaint(null, $status);
            $this->assertFalse(Gate::forUser($admin)->allows('reopen', $complaint));
        }
    }

    public function test_reopening_requires_a_meaningful_reason(): void
    {
        $admin = $this->userWithRole('hoa_admin');
        $complaint = $this->complaint(null, 'Resolved');

        $this->actingAs($admin);

        try {
            app(ReopenComplaint::class)->handle($complaint, $admin, 'Too short');
            $this->fail('A meaningful reopen reason is required.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reason', $exception->errors());
            $this->assertSame('Resolved', $complaint->refresh()->status);
        }
    }

    public function test_a_stale_second_reopen_attempt_is_rejected_without_duplicate_audit_entries(): void
    {
        $admin = $this->userWithRole('hoa_admin');
        $complaint = $this->complaint(null, 'Resolved');
        $staleComplaint = clone $complaint;
        $this->actingAs($admin);

        app(ReopenComplaint::class)->handle($complaint, $admin, 'The resident supplied new evidence for review.');

        try {
            app(ReopenComplaint::class)->handle($staleComplaint, $admin, 'A duplicate reopen request should not succeed.');
            $this->fail('A stale complaint must not be reopened twice.');
        } catch (AuthorizationException) {
            $this->assertSame('Under Review', $complaint->refresh()->status);
            $this->assertSame(1, $complaint->auditLogs()->where('action', 'Complaint.reopened')->count());
        }
    }

    public function test_resolved_complaints_use_the_read_only_page_and_reject_the_edit_route(): void
    {
        $admin = $this->userWithRole('hoa_admin');
        $assigned = $this->userWithRole('hoa_staff');
        $complaint = $this->complaint($assigned, 'Resolved');

        $this->actingAs($admin, 'admin')
            ->get("/admin/complaints/{$complaint->id}")
            ->assertOk();
        $this->actingAs($admin, 'admin')
            ->get("/admin/complaints/{$complaint->id}/edit")
            ->assertForbidden();

        $this->actingAs($assigned, 'staff')
            ->get("/staff/complaints/{$complaint->id}")
            ->assertOk();
        $this->actingAs($assigned, 'staff')
            ->get("/staff/complaints/{$complaint->id}/edit")
            ->assertForbidden();
    }

    public function test_staff_edit_form_accepts_operational_updates_and_rejects_forged_fields(): void
    {
        $assigned = $this->userWithRole('hoa_staff');
        $complaint = $this->complaint($assigned, 'Pending');
        Filament::setCurrentPanel('staff');
        $this->actingAs($assigned, 'staff');

        Livewire::test(EditComplaint::class, ['record' => $complaint->getRouteKey()])
            ->fillForm([
                'priority' => 'Urgent',
                'status' => 'Under Review',
                'admin_remarks' => 'Initial inspection is now underway.',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('complaints', [
            'id' => $complaint->id,
            'priority' => 'Urgent',
            'status' => 'Under Review',
        ]);

        Livewire::test(EditComplaint::class, ['record' => $complaint->getRouteKey()])
            ->set('data.subject', 'A forged replacement subject')
            ->call('save')
            ->assertHasFormErrors(['subject']);

        Livewire::test(EditComplaint::class, ['record' => $complaint->getRouteKey()])
            ->set('data.status', 'Rejected')
            ->call('save')
            ->assertHasFormErrors(['status']);

        $this->assertSame('Drainage inspection', $complaint->refresh()->subject);
        $this->assertSame('Under Review', $complaint->status);
    }

    public function test_reopen_action_is_visible_and_works_for_the_assignee(): void
    {
        $assigned = $this->userWithRole('hoa_staff');
        $complaint = $this->complaint($assigned, 'Resolved');
        Filament::setCurrentPanel('staff');
        $this->actingAs($assigned, 'staff');

        Livewire::test(ViewComplaint::class, ['record' => $complaint->getRouteKey()])
            ->assertActionVisible('reopen')
            ->callAction('reopen', data: ['reason' => 'A follow-up site inspection is required.'])
            ->assertHasNoActionErrors();

        $this->assertSame('Under Review', $complaint->refresh()->status);
    }

    public function test_reopen_action_is_hidden_from_unassigned_staff(): void
    {
        $assigned = $this->userWithRole('hoa_staff');
        $otherStaff = $this->userWithRole('hoa_staff');
        $complaint = $this->complaint($assigned, 'Resolved');
        Filament::setCurrentPanel('staff');
        $this->actingAs($otherStaff, 'staff');

        Livewire::test(ViewComplaint::class, ['record' => $complaint->getRouteKey()])
            ->assertActionHidden('reopen');
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['account_status' => 'Active']);
        $user->assignRole($role);

        return $user;
    }

    private function complaint(?User $handler, string $status): Complaint
    {
        $terminal = in_array($status, ['Resolved', 'Rejected', 'Closed', 'Dismissed'], true);

        return Complaint::query()->create([
            'homeowner_id' => $this->homeowner()->id,
            'handled_by' => $handler?->id,
            'subject' => 'Drainage inspection',
            'description' => 'Water remains in the drainage channel after rainfall.',
            'category' => 'Common Area',
            'priority' => 'High',
            'status' => $status,
            'admin_remarks' => $terminal ? 'The reported work was completed and inspected.' : null,
            'resolved_at' => $terminal ? now() : null,
        ]);
    }

    private function homeowner(): Homeowner
    {
        $user = $this->userWithRole('homeowner');

        return $user->homeowner()->create([
            'house_number' => (string) $user->id,
            'street' => 'Workflow Street',
            'block' => (string) $user->id,
            'lot' => (string) $user->id,
            'phase' => 'Southville Phase I',
            'residency_date' => '2021-01-01',
            'ownership_type' => 'Owner',
            'emergency_contact_name' => 'Contact',
            'emergency_contact_number' => '09123456789',
            'status' => 'Active',
        ]);
    }
}
