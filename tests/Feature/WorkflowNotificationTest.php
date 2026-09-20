<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Announcements\SendAnnouncementBlast;
use App\Actions\Certificates\IssueCertificate;
use App\Models\Announcement;
use App\Models\Complaint;
use App\Models\ContactMessage;
use App\Models\Homeowner;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\AnnouncementPublished;
use App\Notifications\CertificateIssued;
use App\Notifications\OperationalAlert;
use App\Notifications\WorkflowStatusChanged;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class WorkflowNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_new_operational_items_notify_staff_and_status_changes_notify_owner(): void
    {
        $staff = $this->user('hoa_staff');
        $homeowner = $this->homeowner();
        $complaint = Complaint::query()->create(['homeowner_id' => $homeowner->id, 'subject' => 'Street light concern', 'description' => 'The street light has been out for several evenings.', 'category' => 'Common Area', 'priority' => 'Medium', 'status' => 'Pending']);
        $request = ServiceRequest::query()->create(['homeowner_id' => $homeowner->id, 'request_type' => 'Gate Pass', 'details' => 'Visitor access request.', 'status' => 'Pending']);
        ContactMessage::query()->create(['name' => 'Public Sender', 'email' => 'sender@example.test', 'subject' => 'Office hours', 'message' => 'What are the HOA office hours?', 'status' => 'New']);

        Notification::assertSentToTimes($staff, OperationalAlert::class, 3);

        $complaint->update(['status' => 'Under Review']);
        $request->update(['status' => 'Processing']);
        Notification::assertSentToTimes($homeowner->user, WorkflowStatusChanged::class, 2);
    }

    public function test_certificate_and_explicit_announcement_blast_are_queued_for_homeowner(): void
    {
        $admin = $this->user('hoa_admin');
        $homeowner = $this->homeowner();
        app(IssueCertificate::class)->handle(['homeowner_id' => $homeowner->id, 'type' => 'Certificate of Residency'], $admin);
        Notification::assertSentTo($homeowner->user, CertificateIssued::class);

        $announcement = Announcement::query()->create(['title' => 'Community assembly', 'content' => 'Please attend the monthly assembly.', 'category' => 'Event', 'audience' => 'Residents', 'status' => 'Published', 'published_at' => now(), 'created_by' => $admin->id]);
        $this->assertSame(1, app(SendAnnouncementBlast::class)->handle($announcement, $admin));
        Notification::assertSentTo($homeowner->user, AnnouncementPublished::class);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $admin->id, 'action' => 'Announcement.email_blast', 'auditable_id' => $announcement->id]);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['account_status' => 'Active']);
        $user->assignRole($role);

        return $user;
    }

    private function homeowner(): Homeowner
    {
        $user = $this->user('homeowner');

        return $user->homeowner()->create(['house_number' => '10', 'street' => 'Notify Street', 'block' => '10', 'lot' => '10', 'phase' => 'Southville Phase I', 'residency_date' => '2021-01-01', 'ownership_type' => 'Owner', 'emergency_contact_name' => 'Contact', 'emergency_contact_number' => '09123456789', 'status' => 'Active']);
    }
}
