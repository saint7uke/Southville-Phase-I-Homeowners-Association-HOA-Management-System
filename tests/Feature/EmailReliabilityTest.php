<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Announcements\SendAnnouncementBlast;
use App\Jobs\SendAnnouncementEmail;
use App\Models\Announcement;
use App\Models\AnnouncementEmailDelivery;
use App\Models\Homeowner;
use App\Models\SystemSetting;
use App\Models\User;
use App\Notifications\AccountPasswordReset;
use App\Notifications\AnnouncementPublished;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class EmailReliabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        SystemSetting::current()->forceFill(['announcement_notifications' => true])->save();
    }

    public function test_homeowner_forgot_password_is_generic_and_synchronous(): void
    {
        Notification::fake();
        Queue::fake();
        $resident = $this->homeowner();

        $known = $this->post('/homeowner/forgot-password', ['email' => $resident->user->email]);
        $unknown = $this->post('/homeowner/forgot-password', ['email' => 'missing@example.test']);

        $known->assertSessionHas('status', 'If an eligible account matches that address, a password reset link will be sent shortly.');
        $unknown->assertSessionHas('status', 'If an eligible account matches that address, a password reset link will be sent shortly.');
        Notification::assertSentTo($resident->user, AccountPasswordReset::class, function (AccountPasswordReset $notification, array $channels) use ($resident): bool {
            $message = $notification->toMail($resident->user);

            return $channels === ['mail']
                && str_starts_with((string) $message->actionUrl, 'http')
                && str_contains((string) $message->actionUrl, '/homeowner/password-reset/reset');
        });
        Queue::assertNothingPushed();
    }

    public function test_role_aware_reset_links_are_not_queued(): void
    {
        Notification::fake();
        Queue::fake();

        foreach (['hoa_admin' => 'admin', 'hoa_staff' => 'staff', 'homeowner' => 'homeowner'] as $role => $panel) {
            $user = User::factory()->create(['account_status' => 'Active']);
            $user->assignRole($role);
            $user->sendPasswordResetNotification('synthetic-token');

            Notification::assertSentTo($user, AccountPasswordReset::class, function (AccountPasswordReset $notification) use ($user, $panel): bool {
                $url = (string) $notification->toMail($user)->actionUrl;

                return str_contains($url, '/'.$panel.'/password-reset/reset')
                    && str_contains($url, 'signature=');
            });
        }

        Queue::assertNothingPushed();
    }

    public function test_announcement_blast_snapshots_only_eligible_recipients_and_cannot_repeat(): void
    {
        Queue::fake();
        $admin = $this->user('hoa_admin');
        $eligible = $this->homeowner();
        $pending = $this->homeowner('Active', 'Pending');
        $inactiveResident = $this->homeowner('Inactive');
        $announcement = $this->announcement($admin);

        $this->assertSame(1, app(SendAnnouncementBlast::class)->handle($announcement, $admin));
        $this->assertDatabaseHas('announcement_email_deliveries', [
            'announcement_id' => $announcement->id,
            'user_id' => $eligible->user_id,
            'status' => 'Queued',
        ]);
        $this->assertDatabaseMissing('announcement_email_deliveries', ['user_id' => $pending->user_id]);
        $this->assertDatabaseMissing('announcement_email_deliveries', ['user_id' => $inactiveResident->user_id]);
        Queue::assertPushed(SendAnnouncementEmail::class, 1);

        $this->expectException(ValidationException::class);
        app(SendAnnouncementBlast::class)->handle($announcement, $admin);
    }

    public function test_announcement_delivery_records_success_and_failed_only_retry(): void
    {
        Notification::fake();
        Queue::fake();
        $admin = $this->user('hoa_admin');
        $eligible = $this->homeowner();
        $announcement = $this->announcement($admin);
        app(SendAnnouncementBlast::class)->handle($announcement, $admin);
        $delivery = AnnouncementEmailDelivery::query()->sole();

        (new SendAnnouncementEmail($delivery->id))->handle();

        $this->assertSame('Sent', $delivery->refresh()->status);
        Notification::assertSentTo($eligible->user, AnnouncementPublished::class, function (AnnouncementPublished $notification) use ($eligible, $announcement): bool {
            $message = $notification->toMail($eligible->user);

            return str_contains((string) $message->subject, $announcement->title)
                && str_contains((string) $message->actionUrl, '/homeowner/announcements/'.$announcement->id);
        });

        $delivery->update(['status' => 'Failed', 'failed_at' => now(), 'failure_code' => 'transport_failure']);
        Queue::fake();
        $this->assertSame(1, app(SendAnnouncementBlast::class)->retryFailed($announcement, $admin));
        $this->assertSame('Queued', $delivery->refresh()->status);
        Queue::assertPushed(SendAnnouncementEmail::class, 1);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['account_status' => 'Active']);
        $user->assignRole($role);

        return $user;
    }

    private function homeowner(string $residentStatus = 'Active', string $accountStatus = 'Active'): Homeowner
    {
        $user = User::factory()->create(['account_status' => $accountStatus]);
        $user->assignRole('homeowner');

        return $user->homeowner()->create([
            'house_number' => (string) fake()->unique()->numberBetween(1, 9999),
            'street' => 'Email Test Street',
            'block' => '1',
            'lot' => (string) fake()->unique()->numberBetween(1, 9999),
            'phase' => 'Southville Phase I',
            'residency_date' => '2021-01-01',
            'ownership_type' => 'Owner',
            'emergency_contact_name' => 'Contact',
            'emergency_contact_number' => '09123456789',
            'status' => $residentStatus,
        ]);
    }

    private function announcement(User $admin): Announcement
    {
        return Announcement::query()->create([
            'title' => 'Controlled email test',
            'content' => '<p>Safe announcement content for the delivery test.</p>',
            'category' => 'General',
            'audience' => 'Residents',
            'status' => 'Published',
            'published_at' => now(),
            'created_by' => $admin->id,
        ]);
    }
}
