<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\ServiceRequests\CreateServiceRequest;
use App\Jobs\DeliverWorkflowMail;
use App\Models\EmailDelivery;
use App\Models\Homeowner;
use App\Models\ServiceRequest;
use App\Models\SystemSetting;
use App\Models\User;
use App\Notifications\OperationalAlert;
use App\Notifications\WorkflowStatusChanged;
use App\Services\WorkflowMail;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ServiceRequestMailEligibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Notification::fake();
        Queue::fake();
        Http::preventStrayRequests();
        SystemSetting::current()->update(['request_notifications' => true, 'sms_enabled' => false]);
    }

    public static function recipientStates(): array
    {
        return ['active verified' => ['Active', true, false, true],
            'inactive' => ['Inactive', true, false, false],
            'pending' => ['Pending', true, false, false],
            'suspended' => ['Suspended', true, false, false],
            'rejected' => ['Rejected', true, false, false],
            'unverified' => ['Active', false, false, false],
            'deleted after submission' => ['Active', true, true, false]];
    }

    #[DataProvider('recipientStates')]
    public function test_job_rechecks_eligibility_and_delivers_or_suppresses_once(string $status, bool $verified, bool $deleted, bool $expected): void
    {
        $resident = $this->resident($status, $verified);
        $admin = $this->account('hoa_admin');
        $staff = $this->account('hoa_staff');
        $other = $this->resident('Active', true, '99');
        $request = app(CreateServiceRequest::class)->handle($resident, $this->data(), uploader: $admin);
        $row = EmailDelivery::where('user_id', $resident->user_id)->where('type', 'WorkflowStatusChanged')->sole();
        $repeat = app(WorkflowMail::class)->queue($resident->user, new WorkflowStatusChanged('service request', $request->ticket_number, 'Pending'), 'request-created:'.$request->id);
        $this->assertSame($row->id, $repeat->id);
        if ($deleted) {
            $resident->user->delete();
        }
        $job = new DeliverWorkflowMail($row->id);
        $job->handle(app(WorkflowMail::class));
        $job->handle(app(WorkflowMail::class));
        $this->assertSame($expected ? 'Accepted' : 'Skipped', $row->fresh()->status);
        $this->assertSame(1, $row->fresh()->attempts);
        $this->assertSame($expected ? null : 'recipient_or_event_ineligible', $row->fresh()->failure_code);
        if ($expected) {
            Notification::assertSentToTimes($resident->user, WorkflowStatusChanged::class, 1);
        } else {
            Notification::assertNotSentTo($resident->user, WorkflowStatusChanged::class);
        }
        $staffRow = EmailDelivery::where('user_id', $staff->id)->where('type', 'OperationalAlert')->sole();
        (new DeliverWorkflowMail($staffRow->id))->handle(app(WorkflowMail::class));
        (new DeliverWorkflowMail($staffRow->id))->handle(app(WorkflowMail::class));
        Notification::assertSentToTimes($staff, OperationalAlert::class, 1);
        Notification::assertNotSentTo($admin, WorkflowStatusChanged::class);
        Notification::assertNotSentTo($other->user, WorkflowStatusChanged::class);
        $this->assertDatabaseCount('service_requests', 1);
    }

    public function test_dispatch_waits_for_commit_and_rollback_removes_intent(): void
    {
        $resident = $this->resident('Active', true);
        $admin = $this->account('hoa_admin');
        DB::beginTransaction();
        app(CreateServiceRequest::class)->handle($resident, $this->data(), uploader: $admin);
        $row = EmailDelivery::where('user_id', $resident->user_id)->sole();
        Queue::assertNotPushed(DeliverWorkflowMail::class);
        DB::commit();
        Queue::assertPushed(DeliverWorkflowMail::class, fn (DeliverWorkflowMail $job): bool => $job->deliveryId === $row->id);
        Queue::assertPushed(DeliverWorkflowMail::class, 1);
        $this->assertSame('Queued', $row->fresh()->status);
        Notification::assertNothingSent();

        Queue::fake();
        $before = EmailDelivery::count();
        DB::beginTransaction();
        app(CreateServiceRequest::class)->handle($resident, $this->data(), uploader: $admin);
        DB::rollBack();
        $this->assertSame($before, EmailDelivery::count());
        $this->assertSame(1, ServiceRequest::count());
        Queue::assertNotPushed(DeliverWorkflowMail::class);
    }

    private function account(string $role): User
    {
        $user = User::factory()->create(['account_status' => 'Active', 'email_verified_at' => now()]);
        $user->assignRole($role);

        return $user;
    }

    private function resident(string $status, bool $verified, string $lot = '8'): Homeowner
    {
        $user = $this->account('homeowner');
        $user->forceFill(['account_status' => $status, 'email_verified_at' => $verified ? now() : null])->saveQuietly();

        return $user->homeowner()->create(['block' => '7', 'lot' => $lot, 'phase' => 'Southville Phase I', 'phase_suffix' => 'A', 'ownership_type' => 'Owner', 'status' => $status === 'Active' ? 'Active' : 'Inactive', 'residency_date' => '2020-01-01']);
    }

    private function data(): array
    {
        return ['request_type' => 'Certificate of Residency', 'subject' => 'Synthetic request', 'details' => 'Synthetic workflow mail verification only.'];
    }
}
