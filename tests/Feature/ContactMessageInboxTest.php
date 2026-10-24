<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\ContactMessages\AddContactMessageNote;
use App\Actions\ContactMessages\ChangeContactMessageArchiveState;
use App\Actions\ContactMessages\ReopenContactMessage;
use App\Actions\ContactMessages\SendContactMessageReply;
use App\Actions\ContactMessages\UpdateContactMessageStatus;
use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Filament\Resources\ContactMessages\Pages\EditContactMessage;
use App\Jobs\SendContactMessageReply as SendContactMessageReplyJob;
use App\Mail\ContactMessageReplyMail;
use App\Models\AuditLog;
use App\Models\ContactMessage;
use App\Models\ContactMessageActivity;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

final class ContactMessageInboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_public_submission_returns_an_opaque_unique_reference(): void
    {
        $payload = ['name' => 'Public Sender', 'email' => 'sender@example.test', 'subject' => 'Office hours', 'message' => 'When is the office open?', 'website' => ''];

        $first = $this->postJson('/api/contact', $payload)->assertCreated()->json('data.reference');
        $second = $this->postJson('/api/contact', [...$payload, 'subject' => 'Second inquiry'])->assertCreated()->json('data.reference');

        $this->assertMatchesRegularExpression('/^INQ-[A-Z0-9]{12}$/', $first);
        $this->assertNotSame($first, $second);
        $this->assertDatabaseHas('contact_messages', ['reference' => $first, 'status' => 'New']);
    }

    public function test_detail_sections_span_the_full_parent_grid(): void
    {
        $sections = ContactMessageResource::form(Schema::make())->getComponents();

        $this->assertCount(2, $sections);
        $this->assertSame('full', $sections[0]->getColumnSpan('default'));
        $this->assertSame('full', $sections[1]->getColumnSpan('default'));
    }

    public function test_staff_can_open_inquiry_details_with_a_formatted_received_date(): void
    {
        $message = $this->message();
        $staff = $this->user('hoa_staff');

        $this->actingAs($staff)
            ->get("/staff/contact-messages/{$message->id}/edit")
            ->assertOk()
            ->assertSee(__('Inquiry').' '.$message->reference)
            ->assertSee($message->created_at->format('M d, Y g:i A'))
            ->assertSee($message->reference)
            ->assertSee('Public Sender')
            ->assertSee('sender@example.test')
            ->assertSee('Office hours')
            ->assertSee('When is the office open?')
            ->assertSee(__('No internal activity yet.'));
    }

    public function test_admin_can_open_inquiry_details_with_a_formatted_received_date(): void
    {
        $message = $this->message();
        $admin = $this->user('hoa_admin');

        $this->actingAs($admin)
            ->get("/admin/contact-messages/{$message->id}/edit")
            ->assertOk()
            ->assertSee(__('Inquiry').' '.$message->reference)
            ->assertSee($message->created_at->format('M d, Y g:i A'))
            ->assertSee($message->reference)
            ->assertSee('When is the office open?')
            ->assertSee(__('No internal activity yet.'));
    }

    public function test_activity_timeline_distinguishes_notes_reopens_and_delivery_states(): void
    {
        $message = $this->message();
        $admin = $this->user('hoa_admin');
        $message->activities()->create(['author_id' => $admin->id, 'type' => 'internal_note', 'body' => 'Called the resident for clarification.']);
        $message->activities()->create(['author_id' => $admin->id, 'type' => 'reopen_reason', 'body' => 'New information requires another review.']);
        $message->activities()->create(['author_id' => $admin->id, 'type' => 'outbound_reply', 'recipient_email' => $message->email, 'subject' => 'Queued response', 'body' => 'This response is queued.', 'delivery_status' => 'Queued']);
        $message->activities()->create(['author_id' => $admin->id, 'type' => 'outbound_reply', 'recipient_email' => $message->email, 'subject' => 'Delivered response', 'body' => 'This response was delivered.', 'delivery_status' => 'Sent']);
        $message->activities()->create(['author_id' => $admin->id, 'type' => 'outbound_reply', 'recipient_email' => $message->email, 'subject' => 'Failed response', 'body' => 'This response failed safely.', 'delivery_status' => 'Failed']);

        $this->actingAs($admin)->get("/admin/contact-messages/{$message->id}/edit")
            ->assertOk()
            ->assertSee(__('Internal note'))
            ->assertSee(__('Reopen reason'))
            ->assertSee(__('Email reply'))
            ->assertSee(__('Queued'))
            ->assertSee(__('Sent'))
            ->assertSee(__('Failed'))
            ->assertSee('Called the resident for clarification.')
            ->assertSee('This response failed safely.');
    }

    public function test_staff_sees_only_active_inquiry_actions(): void
    {
        $message = $this->message();
        $staff = $this->user('hoa_staff');
        Filament::setCurrentPanel(Filament::getPanel('staff'));
        $this->actingAs($staff, 'staff');

        Livewire::test(EditContactMessage::class, ['record' => $message->getRouteKey()])
            ->assertActionVisible('sendReply')
            ->assertActionVisible('addNote')
            ->assertActionVisible('changeStatus')
            ->assertActionHidden('reopen')
            ->assertDontSee(__('Record actions'));
    }

    public function test_admin_closed_and_archived_inquiries_show_only_contextual_actions(): void
    {
        $admin = $this->user('hoa_admin');
        $closed = $this->message(['status' => 'Closed', 'closed_at' => now()]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin, 'admin');

        Livewire::test(EditContactMessage::class, ['record' => $closed->getRouteKey()])
            ->assertActionHidden('sendReply')
            ->assertActionHidden('addNote')
            ->assertActionHidden('changeStatus')
            ->assertActionVisible('reopen')
            ->assertSee(__('Record actions'));

        $closed->delete();

        Livewire::test(EditContactMessage::class, ['record' => $closed->getRouteKey()])
            ->assertActionHidden('sendReply')
            ->assertActionHidden('reopen')
            ->assertSee(__('Restore'));
    }

    public function test_staff_can_follow_the_workflow_but_cannot_change_a_closed_inquiry(): void
    {
        $staff = $this->user('hoa_staff');
        $message = $this->message();
        $action = app(UpdateContactMessageStatus::class);

        $action->handle($message, 'In Progress', $staff, $message->updated_at?->toISOString());
        app(AddContactMessageNote::class)->handle($message, 'Resident called and supplied the missing details.', $staff);
        $action->handle($message->refresh(), 'Awaiting Resident', $staff);
        $closed = $action->handle($message->refresh(), 'Closed', $staff);

        $this->assertNotNull($closed->first_handled_at);
        $this->assertNotNull($closed->closed_at);
        $this->assertDatabaseHas('contact_message_activities', ['contact_message_id' => $message->id, 'type' => 'internal_note']);
        $this->expectException(ValidationException::class);
        $action->handle($closed, 'In Progress', $staff);
    }

    public function test_first_reply_is_idempotent_and_moves_new_inquiry_to_in_progress(): void
    {
        Queue::fake();
        $staff = $this->user('hoa_staff');
        $message = $this->message();
        $token = (string) Str::uuid();
        $data = ['subject' => 'Re: Office hours', 'body' => 'The office opens at 8 AM.', 'idempotency_token' => $token];

        $first = app(SendContactMessageReply::class)->handle($message, $data, $staff);
        $second = app(SendContactMessageReply::class)->handle($message->refresh(), $data, $staff);

        $this->assertTrue($first->is($second));
        $this->assertSame('In Progress', $message->refresh()->status);
        $this->assertSame(1, $message->activities()->where('type', 'outbound_reply')->count());
        Queue::assertPushed(SendContactMessageReplyJob::class, 1);
    }

    public function test_reply_job_uses_the_recipient_snapshot_and_records_delivery_without_transport_details(): void
    {
        Queue::fake();
        Mail::fake();
        $activity = app(SendContactMessageReply::class)->handle($this->message(), [
            'subject' => 'Re: Office hours', 'body' => 'The office opens at 8 AM.', 'idempotency_token' => (string) Str::uuid(),
        ], $this->user('hoa_staff'));

        (new SendContactMessageReplyJob($activity->id))->handle();

        Mail::assertSent(ContactMessageReplyMail::class, fn ($mail): bool => $mail->hasTo('sender@example.test'));
        $this->assertDatabaseHas('contact_message_activities', ['id' => $activity->id, 'delivery_status' => 'Sent', 'failure_code' => null]);
    }

    public function test_only_admin_can_reopen_archive_and_restore(): void
    {
        $staff = $this->user('hoa_staff');
        $admin = $this->user('hoa_admin');
        $message = $this->message(['status' => 'Closed', 'closed_at' => now()]);

        try {
            app(ReopenContactMessage::class)->handle($message, 'Resident supplied new information.', $staff);
            $this->fail('Staff unexpectedly reopened a closed inquiry.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        $reopened = app(ReopenContactMessage::class)->handle($message, 'Resident supplied new information.', $admin);
        $this->assertSame('In Progress', $reopened->status);

        $archive = app(ChangeContactMessageArchiveState::class);
        $archive->archive($reopened, $admin);
        $this->assertSoftDeleted($reopened);
        $archive->restore($reopened, $admin);
        $this->assertNotSoftDeleted($reopened);
    }

    public function test_generic_audits_exclude_inquiry_and_reply_content(): void
    {
        Queue::fake();
        $message = $this->message();
        $activity = app(SendContactMessageReply::class)->handle($message, [
            'subject' => 'Private reply subject', 'body' => 'Private reply body', 'idempotency_token' => (string) Str::uuid(),
        ], $this->user('hoa_staff'));

        $payload = json_encode(AuditLog::query()
            ->where(function ($query) use ($message, $activity): void {
                $query->where(['auditable_type' => ContactMessage::class, 'auditable_id' => $message->id])
                    ->orWhere(['auditable_type' => ContactMessageActivity::class, 'auditable_id' => $activity->id]);
            })->get(['old_values', 'new_values'])->toArray());

        $this->assertStringNotContainsString('sender@example.test', $payload);
        $this->assertStringNotContainsString('09123456789', $payload);
        $this->assertStringNotContainsString('When is the office open?', $payload);
        $this->assertStringNotContainsString('Private reply subject', $payload);
        $this->assertStringNotContainsString('Private reply body', $payload);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['account_status' => 'Active']);
        $user->assignRole($role);

        return $user;
    }

    private function message(array $attributes = []): ContactMessage
    {
        return ContactMessage::query()->create([...[
            'name' => 'Public Sender', 'email' => 'sender@example.test', 'phone' => '09123456789',
            'subject' => 'Office hours', 'message' => 'When is the office open?', 'status' => 'New', 'ip_address' => '127.0.0.1',
        ], ...$attributes]);
    }
}
