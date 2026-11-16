<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Complaints\CreateComplaint;
use App\Actions\Homeowners\RegisterHomeowner;
use App\Actions\Households\ManageHousehold;
use App\Actions\Users\ChangeAccountStatus;
use App\Enums\UserAccountStatus;
use App\Events\UserAccountStatusChanged;
use App\Filament\Homeowner\Resources\Complaints\Pages\CreateComplaint as ComplaintPage;
use App\Filament\Resources\Homeowners\Pages\CreateHomeowner;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Jobs\DeliverWorkflowMail;
use App\Listeners\SendAccountStatusChangedNotification;
use App\Models\EmailDelivery;
use App\Models\Homeowner;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;
use App\Notifications\HomeownerAccountStatusChanged;
use App\Notifications\HomeownerDelinquencyNotice;
use App\Notifications\NewHomeownerRegistration;
use App\Notifications\OverdueDuesNotice;
use App\Notifications\RegistrationReceived;
use App\Services\WorkflowMail;
use App\Support\PropertySnapshot;
use App\Validation\HomeownerProfileValidator;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;
use Tests\TestCase;

final class UiReliabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Notification::fake();
        Storage::fake('local');
        Queue::fake();
    }

    private function account(string $role = 'hoa_admin'): User
    {
        $user = User::factory()->create(['account_status' => 'Active']);
        $user->assignRole($role);

        return $user;
    }

    private function resident(): Homeowner
    {
        return $this->account('homeowner')->homeowner()->create([
            'house_number' => '12', 'street' => 'Preserved Legacy Street', 'block' => '6', 'lot' => '2',
            'phase' => 'Southville Phase I', 'phase_suffix' => 'A', 'ownership_type' => 'Owner',
            'residency_date' => '2020-01-01', 'status' => 'Active',
        ]);
    }

    private function registration(): array
    {
        return ['verification_document' => UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'), 'first_name' => 'New', 'last_name' => 'Applicant', 'sex' => 'Female', 'date_of_birth' => '1990-01-01',
            'contact_number' => '09171234567', 'email' => 'applicant@example.test', 'password' => 'SafePassword!2026',
            'phase_suffix' => 'EXPANSION', 'block' => '90', 'lot' => '91', 'ownership_type' => 'Owner', 'residency_date' => '2020-01-01'];
    }

    public function test_others_input_is_conditional_required_and_cleared(): void
    {
        $owner = $this->resident();
        Filament::setCurrentPanel(Filament::getPanel('homeowner'));
        $this->actingAs($owner->user, 'homeowner');
        Livewire::test(ComplaintPage::class)->fillForm(['category' => 'Noise'])
            ->assertFormFieldIsHidden('category_other')->fillForm(['category' => 'Others'])
            ->assertFormFieldIsVisible('category_other')->fillForm(['subject' => 'Test complaint', 'priority' => 'Medium', 'description' => str_repeat('A concern. ', 4)])
            ->call('create')->assertHasFormErrors(['category_other' => 'required']);
        $page = Livewire::test(ComplaintPage::class)->fillForm(['category' => 'Others', 'category_other' => 'Other issue']);
        $page->set('data.category', 'Noise')->assertSet('data.category_other', null);
    }

    public function test_complaint_specification_is_saved_trimmed_and_validated(): void
    {
        $owner = $this->resident();
        $data = ['subject' => 'Other concern', 'category' => 'Others', 'category_other' => '  Drain cover  ', 'priority' => 'Medium', 'description' => str_repeat('Details ', 6)];
        $complaint = app(CreateComplaint::class)->handle($owner, $data, [], $owner->user);
        $this->assertSame('Drain cover', $complaint->category_other);
        $this->assertSame('Others', $complaint->category);
        $this->expectException(ValidationException::class);
        app(CreateComplaint::class)->handle($owner, [...$data, 'category_other' => ['malformed']], [], $owner->user);
    }

    public function test_registration_without_street_records_applicant_and_eligible_admin_mail_once(): void
    {
        $admin = $this->account();
        $inactive = $this->account();
        $inactive->forceFill(['account_status' => 'Inactive'])->save();
        $user = app(RegisterHomeowner::class)->handle([...$this->registration(), 'street' => 'forged']);
        $this->assertNull($user->homeowner->street);
        $this->assertSame('EXPANSION', $user->homeowner->phase_suffix);
        $this->assertSame('Pending', $user->fresh()->account_status);
        $this->assertDatabaseCount('email_deliveries', 2);
        $this->assertDatabaseMissing('email_deliveries', ['user_id' => $inactive->id]);
        $delivery = EmailDelivery::where('user_id', $user->id)->sole();
        $same = app(WorkflowMail::class)->queue($user, new RegistrationReceived, 'registration:'.$user->id);
        $this->assertSame($delivery->id, $same->id);
        foreach (EmailDelivery::all() as $row) {
            (new DeliverWorkflowMail($row->id))->handle(app(WorkflowMail::class));
            (new DeliverWorkflowMail($row->id))->handle(app(WorkflowMail::class));
        }
        Notification::assertSentToTimes($user, RegistrationReceived::class, 1);
        Notification::assertSentToTimes($admin, NewHomeownerRegistration::class, 1);
        $this->assertSame('Accepted', $delivery->fresh()->status);
        $this->assertStringNotContainsString('RegistrationReceived', DB::table('email_deliveries')->where('id', $delivery->id)->value('payload'));
        $adminPayload = EmailDelivery::where('user_id', $admin->id)->sole()->payload;
        $this->assertStringNotContainsString($user->password, $adminPayload);
        $this->assertStringNotContainsString('App\\Models\\User', $adminPayload);
    }

    public function test_registration_rejects_arbitrary_phase_and_rolls_back_mail(): void
    {
        try {
            app(RegisterHomeowner::class)->handle([...$this->registration(), 'phase_suffix' => 'C']);
            $this->fail('An unknown phase must fail.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('phase_suffix', $e->errors());
        }
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('email_deliveries', 0);
    }

    public function test_rolled_back_workflow_has_no_delivery_record(): void
    {
        $user = $this->account('homeowner');
        DB::beginTransaction();
        app(WorkflowMail::class)->queue($user, new RegistrationReceived, 'rolled-back');
        DB::rollBack();
        $this->assertDatabaseCount('email_deliveries', 0);
        Queue::assertNothingPushed();
    }

    public function test_uncertain_send_is_not_retried_or_logged_with_private_details(): void
    {
        Log::spy();
        $user = $this->account('homeowner');
        $row = app(WorkflowMail::class)->queue($user, new RegistrationReceived, 'uncertain');
        Notification::shouldReceive('sendNow')->once()->andThrow(new \RuntimeException('private body token email'));
        (new DeliverWorkflowMail($row->id))->handle(app(WorkflowMail::class));
        $this->assertSame('Uncertain', $row->fresh()->status);
        (new DeliverWorkflowMail($row->id))->handle(app(WorkflowMail::class));
        $this->assertSame(1, $row->fresh()->attempts);
        Log::shouldHaveReceived('warning')->once()->with('Workflow email requires delivery review.', [
            'delivery_id' => $row->id, 'failure_code' => 'delivery_result_uncertain',
        ]);
        $this->artisan('hoa:recover-workflow-mail', ['--retry-failed' => $row->id])->assertFailed();
    }

    public function test_queue_outage_leaves_registration_and_delivery_recoverable(): void
    {
        $user = app(RegisterHomeowner::class)->handle($this->registration());
        $row = EmailDelivery::where('user_id', $user->id)->sole();
        Bus::shouldReceive('dispatch')->once()->andThrow(new \RuntimeException('synthetic queue outage'));
        app(WorkflowMail::class)->dispatch($row->id);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'account_status' => 'Pending']);
        $this->assertSame('Queued', $row->fresh()->status);
        $this->assertSame(0, $row->fresh()->attempts);
        Notification::assertNothingSent();
    }

    public function test_delivery_rechecks_recipient_role(): void
    {
        $admin = $this->account();
        $row = app(WorkflowMail::class)->queue($admin, new NewHomeownerRegistration($this->account('homeowner')), 'new-app');
        $admin->syncRoles('hoa_staff');
        (new DeliverWorkflowMail($row->id))->handle(app(WorkflowMail::class));
        $this->assertSame('Skipped', $row->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_reactivation_does_not_claim_new_registration_approval(): void
    {
        $user = $this->account('homeowner');
        $notice = new HomeownerAccountStatusChanged('Active', null, 'Suspended');
        $this->assertContains('Your resident account has been activated.', $notice->toMail($user)->introLines);
        $this->assertNotContains('Your resident application has been approved.', $notice->toMail($user)->introLines);
    }

    public function test_current_address_omits_street_but_old_snapshot_display_remains(): void
    {
        $owner = $this->resident();
        $membership = $owner->getAttributes();
        $owner->update(['emergency_contact_name' => 'Updated Contact']);
        $this->assertSame('Preserved Legacy Street', $owner->street);
        $this->assertSame($membership['property_key'], $owner->property_key);
        $this->assertStringNotContainsString('Street', $owner->full_address);
        $new = PropertySnapshot::from($owner);
        $this->assertArrayNotHasKey('street', $new);
        $this->assertSame('Original Street, original address', PropertySnapshot::display(['display_address' => 'Original Street, original address']));
    }

    public function test_columns_persist_per_admin_and_mandatory_columns_cannot_be_hidden(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $admin = $this->account();
        $this->actingAs($admin, 'admin');
        $page = Livewire::test(ListUsers::class);
        $state = $page->get('tableColumns');
        foreach ($state as &$column) {
            $column['isToggled'] = false;
        }
        unset($column);
        $state[] = ['name' => 'password', 'isToggled' => true];
        $page->call('applyTableColumnManager', $state);
        $this->assertFalse(collect($page->get('tableColumns'))->firstWhere('name', 'email')['isToggled']);
        $this->assertTrue(collect($page->get('tableColumns'))->firstWhere('name', 'full_name')['isToggled']);
        $this->assertNull(collect($page->get('tableColumns'))->firstWhere('name', 'password'));
        $again = Livewire::test(ListUsers::class);
        $this->assertFalse(collect($again->get('tableColumns'))->firstWhere('name', 'email')['isToggled']);
        $other = $this->account();
        $this->actingAs($other, 'admin');
        $this->assertTrue(collect(Livewire::test(ListUsers::class)->get('tableColumns'))->firstWhere('name', 'email')['isToggled']);
        $this->actingAs($admin, 'admin');
        Livewire::test(ListUsers::class)->call('resetTableColumnManager');
        $this->assertDatabaseMissing('user_table_preferences', ['user_id' => $admin->id]);
    }

    public function test_role_status_search_and_pagination_filters_compose(): void
    {
        $admin = $this->account();
        $active = $this->account('hoa_staff');
        $pending = $this->account('hoa_staff');
        $pending->forceFill(['account_status' => 'Pending'])->save();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin, 'admin');
        Livewire::test(ListUsers::class)->filterTable('role', 'hoa_staff')->filterTable('account_status', 'Active')
            ->searchTable($active->email)->assertCanSeeTableRecords([$active])->assertCanNotSeeTableRecords([$pending, $admin]);
    }

    public function test_branded_mail_has_html_and_text_and_escapes_user_content(): void
    {
        config(['app.url' => 'https://southvillehoams.com']);
        $user = $this->account('homeowner');
        $user->first_name = '<script>alert(1)</script>';
        $message = (new RegistrationReceived)->toMail($user);
        $html = (string) app(Markdown::class)->render('notifications::email', $message->data());
        $plain = (string) app(Markdown::class)->renderText('notifications::email', $message->data());
        $this->assertStringContainsString('https://southvillehoams.com/images/hoa-email-logo.png', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('Southville Phase I HOA', $html);
        $this->assertStringContainsString('Pending HOA review', $plain);
    }

    public function test_transport_rejection_is_failed_and_retry_has_a_bound(): void
    {
        $user = $this->account('homeowner');
        $row = app(WorkflowMail::class)->queue($user, new RegistrationReceived, 'rejected-transport');
        Notification::shouldReceive('sendNow')->once()->andThrow(new UnexpectedResponseException('synthetic SMTP rejection'));
        (new DeliverWorkflowMail($row->id))->handle(app(WorkflowMail::class));
        $this->assertSame('Failed', $row->fresh()->status);
        $row->update(['attempts' => 3]);
        $this->artisan('hoa:recover-workflow-mail', ['--retry-failed' => $row->id])->assertFailed();
    }

    public function test_account_transition_event_replay_does_not_duplicate_mail(): void
    {
        $admin = $this->account();
        $owner = $this->resident();
        $owner->user->forceFill(['account_status' => 'Pending'])->save();
        app(ChangeAccountStatus::class)->handle($owner->user, UserAccountStatus::Active, $admin);
        $this->assertSame(1, EmailDelivery::where('type', 'HomeownerAccountStatusChanged')->count());
        $this->assertSame('Active', $owner->user->fresh()->account_status);
        $this->assertNotNull($owner->user->fresh()->email_verified_at);
        $event = new UserAccountStatusChanged($owner->user->fresh(), UserAccountStatus::Active, UserAccountStatus::Suspended, 'Synthetic reason');
        $listener = app(SendAccountStatusChangedNotification::class);
        $listener->handle($event);
        $listener->handle($event);
        $this->assertSame(2, EmailDelivery::where('type', 'HomeownerAccountStatusChanged')->count());
    }

    public function test_removed_street_is_ignored_without_changing_the_profile_address(): void
    {
        $owner = $this->resident();
        $validator = app(HomeownerProfileValidator::class);
        $input = [...$owner->user->getAttributes(), ...$owner->getAttributes(),
            'street' => ['crafted'], 'emergency_contact_name' => 'Contact Person', 'emergency_contact_number' => '09171234567'];
        $validated = $validator->validate($owner->user, $owner, $input);
        $this->assertSame($owner->street, $validated['street']);
        $this->assertSame('Preserved Legacy Street', $owner->fresh()->street);
    }

    public function test_existing_phase_and_street_survive_an_unrelated_household_edit(): void
    {
        $owner = $this->resident();
        $household = Household::sole();
        $before = $household->only(['street', 'phase_suffix', 'property_key', 'address_key']);
        app(ManageHousehold::class)->save($household->id,
            [...$household->getAttributes(), 'street' => ['crafted'], 'phase_suffix' => 'B', 'contact_number' => '09171234567'], $this->account());
        $this->assertSame($before, $household->fresh()->only(array_keys($before)));
        $this->assertSame($household->id, HouseholdMember::where('current_homeowner_id', $owner->id)->sole()->household_id);
    }

    public function test_due_notice_uses_the_registered_homeowner_route(): void
    {
        $owner = $this->resident();
        foreach ([new OverdueDuesNotice('Monthly dues', 'January 1'), new HomeownerDelinquencyNotice(3)] as $notice) {
            $this->assertSame(route('filament.homeowner.resources.dues.dues-obligations.index'), $notice->toMail($owner->user)->actionUrl);
        }
    }

    public function test_new_tenant_form_can_select_a_confirmed_landlord_without_street(): void
    {
        $landlord = $this->resident();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->account(), 'admin');
        Livewire::test(CreateHomeowner::class)
            ->fillForm(['ownership_type' => 'Tenant', 'phase_suffix' => 'A', 'block' => $landlord->block, 'lot' => $landlord->lot])
            ->assertFormFieldExists('landlord_homeowner_id', fn (Select $field): bool => array_key_exists($landlord->id, $field->getOptions()));
    }

    public function test_new_household_synchronization_preserves_null_street(): void
    {
        $owner = $this->account('homeowner')->homeowner()->create([
            'street' => null, 'block' => '77', 'lot' => '88', 'phase' => 'Southville Phase I', 'phase_suffix' => 'A',
            'ownership_type' => 'Owner', 'residency_date' => '2020-01-01', 'status' => 'Active',
        ]);
        $member = HouseholdMember::where('current_homeowner_id', $owner->id)->sole();
        $this->assertNull($member->household->street);
        $this->assertSame($owner->property_key, $member->household->property_key);
    }

    public function test_pre_release_account_notification_payload_reuses_its_job_identity(): void
    {
        $owner = $this->resident();
        $reflection = new \ReflectionClass(UserAccountStatusChanged::class);
        $event = $reflection->newInstanceWithoutConstructor();
        foreach (['user' => $owner->user, 'from' => UserAccountStatus::Pending, 'to' => UserAccountStatus::Active, 'reason' => null] as $name => $value) {
            $reflection->getProperty($name)->setValue($event, $value);
        }
        $job = \Mockery::mock(Job::class);
        $job->shouldReceive('uuid')->andReturn('synthetic-old-job-uuid');
        $listener = app(SendAccountStatusChangedNotification::class);
        $listener->setJob($job);
        $listener->handle($event);
        $listener->handle($event);
        $this->assertSame(1, EmailDelivery::where('type', 'HomeownerAccountStatusChanged')->count());
    }
}
