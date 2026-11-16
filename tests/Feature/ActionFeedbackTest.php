<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Announcements\Pages\EditAnnouncement;
use App\Filament\Resources\Announcements\Pages\ListAnnouncements;
use App\Filament\Resources\ContactMessages\Pages\EditContactMessage;
use App\Filament\Resources\Payments\Pages\CreatePayment;
use App\Models\Announcement;
use App\Models\ContactMessage;
use App\Models\Homeowner;
use App\Models\User;
use App\Support\ActionConfirmation;
use App\Support\ActionFeedback;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Facades\Filament;
use Filament\Notifications\Livewire\Notifications;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

final class ActionFeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_catalog_message_is_specific_and_translated(): void
    {
        foreach (ActionFeedback::MESSAGES as $module => $operations) {
            foreach ($operations as $operation => $message) {
                $this->assertSame($message, ActionFeedback::title($module, $operation));
                $this->assertStringEndsWith('Successfully!', $message);
                $this->assertNotSame($message, __($message, [], 'fil'));
            }
        }
        $this->assertSame('Password Reset Successfully!', __('passwords.reset'));
        $this->expectException(\InvalidArgumentException::class);
        ActionFeedback::title('Unknown', 'create');
    }

    public function test_builtin_actions_name_the_module_and_distinguish_archival(): void
    {
        $archive = DeleteAction::make()->model(Homeowner::class);
        $restore = RestoreAction::make()->model(Homeowner::class);
        $permanent = ForceDeleteAction::make()->model(Homeowner::class);
        $this->assertSame('Homeowner Archived Successfully!', $archive->getSuccessNotificationTitle());
        $this->assertSame('Homeowner Restored Successfully!', $restore->getSuccessNotificationTitle());
        $this->assertSame('Homeowner Deleted Successfully!', $permanent->getSuccessNotificationTitle());
    }

    public function test_announcement_save_distinguishes_publication_from_update_and_schedule(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('hoa_admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin, 'admin');
        $announcement = Announcement::create([
            'title' => 'Feedback fixture', 'content' => 'Safe test content', 'category' => 'General',
            'audience' => 'Public', 'status' => 'Draft',
        ]);
        $page = Livewire::test(EditAnnouncement::class, ['record' => $announcement->id]);
        $page->fillForm(['status' => 'Published', 'published_at' => now()->addDay()->format('Y-m-d H:i:s')])
            ->call('save')->assertHasNoFormErrors()->assertNotified('Announcement Updated Successfully!');
        $page->fillForm(['published_at' => now()->subMinute()->format('Y-m-d H:i:s')])
            ->call('save')->assertHasNoFormErrors()->assertNotified('Announcement Published Successfully!');
        $page->fillForm(['title' => 'Edited published announcement'])
            ->call('save')->assertHasNoFormErrors()->assertNotified('Announcement Updated Successfully!');
    }

    public function test_login_and_logout_feedback_follow_backend_outcomes(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create(['password' => Hash::make('LoginFixture!2026')]);
        $admin->assignRole('hoa_admin');
        $this->post('/login', ['email' => $admin->email, 'password' => 'incorrect'])->assertSessionHasErrors('email')
            ->assertSessionMissing('action_feedback');
        $this->post('/login', ['email' => $admin->email, 'password' => 'LoginFixture!2026'])
            ->assertRedirect('/admin')->assertSessionHas('action_feedback.title', 'Login Successful!');
        $this->get('/admin')->assertOk()->assertSee('Login Successful!');
        $this->get('/admin')->assertDontSee('Login Successful!');
        $this->post('/admin/logout')->assertRedirect('/login')->assertSessionHas('action_feedback.title', 'Logged Out Successfully!');
    }

    public function test_bulk_archive_reports_the_module_and_actual_success_count(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('hoa_admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin, 'admin');
        $records = collect([1, 2])->map(fn (int $number) => Announcement::create([
            'title' => 'Bulk fixture '.$number, 'content' => 'Safe test content', 'category' => 'General',
            'audience' => 'Public', 'status' => 'Draft',
        ]));
        Livewire::test(ListAnnouncements::class)
            ->callTableBulkAction('delete', $records)
            ->assertNotified(Notification::make()->success()->title('Announcement Archived Successfully!')->body('Records affected: 2'));
        foreach ($records as $record) {
            $this->assertSoftDeleted($record);
        }
    }

    public function test_failed_operation_emits_no_success_and_propagates_validation(): void
    {
        try {
            ActionFeedback::after('Must not appear', fn () => throw ValidationException::withMessages(['name' => 'Required.']));
            $this->fail('Validation should propagate.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('name', $exception->errors());
        }
        Notification::assertNotNotified();
        $this->assertSame(42, ActionFeedback::after('Homeowner Created Successfully!', fn () => 42));
        Notification::assertNotified('Homeowner Created Successfully!');
    }

    public function test_notification_identity_and_explicit_status_are_preserved(): void
    {
        $notification = Notification::make('fixture-id')->danger()->title('<img src=x onerror=alert(1)>')->body('Validation failed.');
        $data = ActionFeedback::fromNotification($notification);
        $this->assertSame('fixture-id', $data['id']);
        $this->assertSame('danger', $data['level']);
        session()->push('filament.notifications', $notification->toArray());
        $html = Livewire::test(Notifications::class)->html();
        $this->assertStringContainsString('data-hoa-feedback=', $html);
        $this->assertStringNotContainsString('title="<img', $html);
    }

    public function test_submit_confirmation_has_escaped_metadata_and_ordinary_actions_do_not(): void
    {
        $action = Action::make('save')->submit('save')->requiresConfirmation()->modalHeading('<b>Confirm access</b>');
        $attributes = ActionConfirmation::attributes($action);
        $content = json_decode(html_entity_decode($attributes['data-hoa-submit-confirm']), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('Confirm access', $content['title']);
        $document = new \DOMDocument;
        @$document->loadHTML($action->toHtml());
        $button = $document->getElementsByTagName('button')->item(0);
        $rendered = json_decode($button->getAttribute('data-hoa-submit-confirm'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('Confirm access', $rendered['title']);
        $this->assertSame([], ActionConfirmation::attributes(Action::make('download')->url('/fixture')));
        $this->assertSame([], ActionConfirmation::attributes(Action::make('save')->submit('save')));
    }

    public function test_contact_restore_confirms_and_reports_actual_backend_result(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('hoa_admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin, 'admin');
        $message = ContactMessage::create(['name' => 'Fixture', 'email' => 'fixture@example.test', 'subject' => 'Test inquiry', 'message' => 'Test message', 'status' => 'New']);
        $message->delete();
        $page = Livewire::test(EditContactMessage::class, ['record' => $message->getRouteKey()])->mountAction('restore');
        $this->assertTrue($message->fresh()->trashed());
        $this->assertTrue($page->instance()->getMountedAction()->isConfirmationRequired());
        $page->unmountAction()->assertSet('mountedActions', []);
        $this->assertTrue($message->fresh()->trashed());
        $page->mountAction('restore');
        $page->callMountedAction()->assertNotified('Contact Inquiry Restored Successfully!');
        $this->assertFalse($message->fresh()->trashed());
    }

    public function test_public_flash_is_escaped_and_reset_response_stays_generic(): void
    {
        $this->withSession(['success' => '<script>window.evil=true</script>'])->get('/login')
            ->assertOk()->assertSee('data-hoa-feedback', false)->assertDontSee('<script>window.evil=true</script>', false);
        $this->post('/homeowner/forgot-password', ['email' => 'missing@example.test'])
            ->assertSessionHas('status', 'If an eligible account matches that address, a password reset link will be sent shortly.');
    }

    public function test_header_recovery_action_does_not_validate_the_unsaved_payment_form(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('hoa_admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin, 'admin');
        Livewire::test(CreatePayment::class)
            ->callAction('generateCurrentDues')->assertHasNoErrors()->assertNotified('No New Dues Obligations Required');
    }
}
