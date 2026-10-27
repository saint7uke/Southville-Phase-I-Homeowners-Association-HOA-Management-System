<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Complaints\ReopenComplaint;
use App\Actions\Complaints\UpdateComplaint;
use App\Actions\Dues\CloseDuesSetting;
use App\Actions\Dues\GenerateSettingObligations;
use App\Actions\Dues\SaveDuesSetting;
use App\Actions\Roles\UpdateRolePermissions;
use App\Filament\Resources\Complaints\Pages\EditComplaint;
use App\Filament\Resources\DuesSettings\Pages\ListDuesSettings;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ContactMessage;
use App\Models\DuesObligation;
use App\Models\DuesSetting;
use App\Models\Homeowner;
use App\Models\Role;
use App\Models\ServiceRequest;
use App\Models\SystemSetting;
use App\Models\User;
use App\Notifications\OperationalAlert;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

final class AuditRemediationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-22 12:00:00', 'Asia/Manila'));
        $this->seed(RolesAndPermissionsSeeder::class);
        Notification::fake();
        Bus::fake();
    }

    public function test_stale_complaint_edits_cannot_overwrite_final_remarks_for_either_actor(): void
    {
        $staff = $this->actor('hoa_staff');
        $admin = $this->actor('hoa_admin');
        $complaint = $this->complaint($staff);
        $stale = $complaint->fresh();
        $action = app(UpdateComplaint::class);
        $action->handle($complaint, ['status' => 'Under Review', 'priority' => 'High'], $staff);
        $action->handle($complaint, ['status' => 'Resolved', 'priority' => 'High', 'admin_remarks' => 'Final inspected resolution.'], $staff);
        $audits = $complaint->auditLogs()->count();
        foreach ([$staff, $admin] as $actor) {
            $this->assertValidation('status', fn () => $action->handle($stale, ['status' => 'Pending', 'priority' => 'Low', 'admin_remarks' => 'Stale overwrite'], $actor));
        }
        $this->assertSame('Final inspected resolution.', $complaint->fresh()->admin_remarks);
        $this->assertSame('Resolved', $complaint->fresh()->status);
        $this->assertSame($audits, $complaint->auditLogs()->count());

        $reopened = app(ReopenComplaint::class)->handle($complaint->fresh(), $staff, 'New evidence requires another inspection.');
        $this->assertNull($reopened->resolved_at);
        $this->assertSame('Final inspected resolution.', $reopened->admin_remarks);
        $this->assertSame('Follow-up inspection', $action->handle($stale, ['status' => 'Under Review', 'priority' => 'High', 'admin_remarks' => 'Follow-up inspection'], $staff)->admin_remarks);
    }

    public function test_complaint_action_rechecks_assignment_and_staff_field_allowlist(): void
    {
        $staff = $this->actor('hoa_staff');
        $complaint = $this->complaint($staff);
        $action = app(UpdateComplaint::class);
        foreach (['subject' => 'Forged subject', 'homeowner_id' => 999999, 'handled_by' => null, 'description' => 'Forged original', 'category' => 'Others', 'resolved_at' => today()->toDateString()] as $field => $value) {
            $this->assertValidation($field, fn () => $action->handle($complaint, ['priority' => 'High', 'status' => 'Pending', $field => $value], $staff));
        }
        $this->assertValidation('status', fn () => $action->handle($complaint, ['priority' => 'High', 'status' => 'Resolved', 'admin_remarks' => 'Cannot skip review.'], $staff));
        $action->handle($complaint, ['priority' => 'High', 'status' => 'Under Review'], $staff);
        $this->assertValidation('admin_remarks', fn () => $action->handle($complaint, ['priority' => 'High', 'status' => 'Resolved', 'admin_remarks' => '   '], $staff));
        DB::table('complaints')->where('id', $complaint->id)->update(['handled_by' => $this->actor('hoa_staff')->id]);
        $this->expectException(AuthorizationException::class);
        $action->handle($complaint, ['priority' => 'Low', 'status' => 'Under Review'], $staff);
    }

    public function test_direct_stale_eloquent_save_also_cannot_overwrite_a_finalized_complaint(): void
    {
        $complaint = $this->complaint($this->actor('hoa_staff'));
        $stale = $complaint->fresh();
        $complaint->update(['status' => 'Under Review']);
        $complaint->update(['status' => 'Resolved', 'admin_remarks' => 'Final resolution remains immutable.']);
        $this->assertValidation('status', fn () => $stale->update(['admin_remarks' => 'Stale overwrite']));
        $this->assertSame('Final resolution remains immutable.', $complaint->fresh()->admin_remarks);
        $this->assertSame('Resolved', $complaint->fresh()->status);
    }

    public function test_admin_can_preserve_an_unchanged_legacy_assignee_but_cannot_assign_a_non_staff_user(): void
    {
        $admin = $this->actor('hoa_admin');
        $complaint = $this->complaint($admin);
        $data = $complaint->only(['homeowner_id', 'subject', 'category', 'description', 'priority', 'handled_by']);
        $data['status'] = 'Under Review';
        $saved = app(UpdateComplaint::class)->handle($complaint, $data, $admin);
        $this->assertSame($admin->id, $saved->handled_by);
        $data['handled_by'] = $this->actor('homeowner')->id;
        $this->assertValidation('handled_by', fn () => app(UpdateComplaint::class)->handle($saved, $data, $admin));
        $this->assertSame($admin->id, $complaint->fresh()->handled_by);
    }

    public function test_complaint_domain_errors_are_mapped_to_the_form_and_keep_input(): void
    {
        $staff = $this->actor('hoa_staff');
        $complaint = $this->complaint($staff);
        Filament::setCurrentPanel('staff');
        $this->actingAs($staff, 'staff');
        $component = Livewire::test(EditComplaint::class, ['record' => $complaint->getRouteKey()])
            ->fillForm(['priority' => 'Urgent', 'status' => 'Pending', 'admin_remarks' => 'Unsaved inspection notes.']);
        $complaint->update(['subject' => 'A corrected original subject']);
        $component->call('save')->assertHasFormErrors(['subject'])->assertNotified('Please review the form')
            ->assertSet('data.admin_remarks', 'Unsaved inspection notes.');
        $this->assertSame('High', $complaint->fresh()->priority);
    }

    public function test_generation_uses_current_schedule_values_not_the_supplied_snapshot(): void
    {
        $owner = $this->resident();
        $staff = $this->actor('hoa_staff');
        $setting = $this->setting();
        $stale = $setting->fresh();
        app(SaveDuesSetting::class)->update($setting, [...$setting->only(['name', 'frequency', 'starts_on', 'ends_on', 'description', 'is_active']), 'amount' => '750.25', 'due_day' => 31], $staff);
        $generator = app(GenerateSettingObligations::class);
        $this->assertSame(1, $generator->handle($stale, CarbonImmutable::parse('2026-10-01')));
        $this->assertDatabaseHas('dues_obligations', ['homeowner_id' => $owner->id, 'amount_due' => '750.25', 'due_date' => '2026-10-31']);
        $this->assertSame(0, $generator->handle($stale, CarbonImmutable::parse('2026-10-01')));
        $this->assertSame('500.00', $stale->amount);
    }

    public function test_stale_schedule_cannot_generate_after_closure_or_outside_updated_applicability(): void
    {
        $this->resident();
        $setting = $this->setting();
        $stale = $setting->fresh();
        app(CloseDuesSetting::class)->handle($setting, $this->actor('hoa_admin'));
        $this->assertSame(0, app(GenerateSettingObligations::class)->handle($stale, CarbonImmutable::parse('2026-11-01')));
        $this->assertDatabaseCount('dues_obligations', 0);
        $future = $this->setting(['name' => 'Future assessment']);
        $future->fresh()->update(['starts_on' => '2026-12-01']);
        $this->assertSame(0, app(GenerateSettingObligations::class)->handle($future, CarbonImmutable::parse('2026-10-01')));
        $inactive = $this->setting(['name' => 'Activated assessment', 'is_active' => false]);
        $inactive->fresh()->update(['is_active' => true]);
        $this->assertSame(1, app(GenerateSettingObligations::class)->handle($inactive, CarbonImmutable::parse('2026-10-01')));
    }

    public function test_closing_a_bounded_schedule_never_extends_into_its_replacement(): void
    {
        $old = $this->setting(['ends_on' => '2026-03-31']);
        $replacement = $this->setting(['starts_on' => '2026-04-01']);
        $closed = app(CloseDuesSetting::class)->handle($old, $this->actor('hoa_admin'));
        $this->assertFalse($closed->is_active);
        $this->assertSame('2026-03-31', $closed->ends_on->toDateString());
        $this->assertNull($replacement->fresh()->ends_on);
        $this->assertDatabaseCount('dues_obligations', 0);
        $this->assertSame(0, $this->diagnostic()['review_observations']['overlapping_equivalent_dues_schedules']);
    }

    public function test_closure_reports_out_of_range_assessments_without_rewriting_them(): void
    {
        $owner = $this->resident();
        $setting = $this->setting(['ends_on' => '2026-03-31']);
        $due = $this->obligation($owner, $setting, 10);
        $this->assertValidation('ends_on', fn () => app(CloseDuesSetting::class)->handle($setting, $this->actor('hoa_admin')));
        $this->assertTrue($setting->fresh()->is_active);
        $this->assertSame('2026-03-31', $setting->fresh()->ends_on->toDateString());
        $this->assertSame('500.00', $due->fresh()->amount_due);
    }

    public function test_closure_checks_equivalent_name_overlaps_and_rolls_back(): void
    {
        $setting = $this->setting();
        $this->setting(['starts_on' => '2026-04-01']);
        $this->assertValidation('name', fn () => app(CloseDuesSetting::class)->handle($setting, $this->actor('hoa_admin')));
        $this->assertTrue($setting->fresh()->is_active);
        $this->assertNull($setting->fresh()->ends_on);
    }

    public function test_closure_review_errors_are_visible_in_the_table_confirmation(): void
    {
        $admin = $this->actor('hoa_admin');
        $setting = $this->setting(['ends_on' => '2026-03-31']);
        $this->obligation($this->resident(), $setting, 10);
        Filament::setCurrentPanel('admin');
        $this->actingAs($admin, 'admin');
        Livewire::test(ListDuesSettings::class)->callTableAction('deactivate', $setting)
            ->assertNotified('Please review the form');
        $this->assertTrue($setting->fresh()->is_active);
        $this->assertSame('2026-03-31', $setting->fresh()->ends_on->toDateString());
    }

    public function test_role_permission_changes_roll_back_when_audit_creation_fails(): void
    {
        $admin = $this->actor('hoa_admin');
        $staff = $this->actor('hoa_staff');
        $role = Role::query()->where('name', 'hoa_staff')->firstOrFail();
        $before = $role->permissions()->pluck('name')->sort()->values()->all();
        $this->assertTrue($staff->can('manage_complaints')); // Warm relations and cache.
        AuditLog::creating(function (AuditLog $audit): void {
            if ($audit->action === 'Role.permissions_changed') {
                throw new \RuntimeException('Synthetic audit failure');
            }
        });
        try {
            app(UpdateRolePermissions::class)->handle($role, array_values(array_diff($before, ['manage_complaints'])), $admin);
            $this->fail('Audit failure must reject the entire permission change.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Synthetic audit failure', $exception->getMessage());
        }
        $this->assertSame($before, $role->fresh()->permissions()->pluck('name')->sort()->values()->all());
        $this->assertTrue($staff->fresh()->can('manage_complaints'));
        $this->assertDatabaseMissing('audit_logs', ['action' => 'Role.permissions_changed']);
    }

    public function test_outer_transaction_rollback_invalidates_permissions_cached_after_a_successful_action(): void
    {
        $admin = $this->actor('hoa_admin');
        $staff = $this->actor('hoa_staff');
        $role = Role::query()->where('name', 'hoa_staff')->firstOrFail();
        $permissions = $role->permissions()->pluck('name')->reject(fn ($name) => $name === 'manage_complaints')->values()->all();
        DB::beginTransaction();
        try {
            DB::beginTransaction();
            app(UpdateRolePermissions::class)->handle($role, $permissions, $admin);
            DB::commit();
            $this->assertFalse($staff->fresh()->can('manage_complaints'));
        } finally {
            DB::rollBack();
        }
        $this->assertTrue($staff->fresh()->can('manage_complaints'));
        $this->assertDatabaseMissing('audit_logs', ['action' => 'Role.permissions_changed']);
    }

    public function test_operational_alert_selection_respects_each_module_permission(): void
    {
        $staff = $this->actor('hoa_staff');
        $owner = $this->resident();
        $role = Role::query()->where('name', 'hoa_staff')->firstOrFail();
        $role->revokePermissionTo(['manage_complaints', 'manage_requests', 'manage_contact_messages']);
        $this->complaint($staff);
        ServiceRequest::query()->create(['homeowner_id' => $owner->id, 'request_type' => 'Certificate of Residency', 'subject' => 'Private request', 'details' => 'Synthetic private details', 'status' => 'Pending']);
        ContactMessage::query()->create(['name' => 'Synthetic sender', 'email' => 'sender@example.test', 'subject' => 'Private inquiry', 'message' => 'Synthetic private message', 'status' => 'New']);
        Notification::assertNotSentTo($staff, OperationalAlert::class);
        $staff->givePermissionTo('manage_contact_messages');
        ContactMessage::query()->create(['name' => 'Synthetic sender', 'email' => 'sender@example.test', 'subject' => 'Permitted inquiry', 'message' => 'Synthetic private message', 'status' => 'New']);
        Notification::assertSentToTimes($staff, OperationalAlert::class, 1);
        SystemSetting::current()->update(['contact_notifications' => false]);
        ContactMessage::query()->create(['name' => 'Synthetic sender', 'email' => 'sender@example.test', 'subject' => 'Disabled inquiry', 'message' => 'Synthetic private message', 'status' => 'New']);
        Notification::assertSentToTimes($staff, OperationalAlert::class, 1);
    }

    public function test_queued_alert_rechecks_permission_role_status_and_deletion_at_delivery(): void
    {
        $staff = $this->actor('hoa_staff');
        $alert = unserialize(serialize(new OperationalAlert('New homeowner complaint', 'Restricted subject', url('/staff/complaints'), [], 'manage_complaints')));
        $this->assertTrue($alert->shouldSend($staff, 'mail'));
        $role = Role::query()->where('name', 'hoa_staff')->firstOrFail();
        $role->revokePermissionTo('manage_complaints');
        $this->assertFalse($alert->shouldSend($staff, 'mail'));
        $this->assertSame([], $alert->via($staff));
        $role->givePermissionTo('manage_complaints');
        $staff->forceFill(['account_status' => 'Inactive'])->save();
        $this->assertFalse($alert->shouldSend($staff, 'mail'));
        $staff->forceFill(['account_status' => 'Active'])->save();
        $staff->removeRole('hoa_staff');
        $this->assertFalse($alert->shouldSend($staff, 'mail'));
        $staff->assignRole('hoa_staff');
        $this->assertTrue($alert->shouldSend($staff, 'mail'));
        $staff->delete();
        $this->assertFalse($alert->shouldSend($staff, 'mail'));
    }

    public function test_older_queued_alerts_infer_only_known_module_permissions(): void
    {
        $staff = $this->actor('hoa_staff');
        $reflection = new \ReflectionClass(OperationalAlert::class);
        $legacy = $reflection->newInstanceWithoutConstructor();
        foreach (['subject' => 'New homeowner complaint', 'message' => 'Private subject', 'url' => url('/staff/complaints')] as $field => $value) {
            $reflection->getProperty($field)->setValue($legacy, $value);
        }
        $legacy = unserialize(serialize($legacy));
        $this->assertTrue($legacy->shouldSend($staff, 'mail'));
        Role::query()->where('name', 'hoa_staff')->firstOrFail()->revokePermissionTo('manage_complaints');
        $this->assertFalse($legacy->shouldSend($staff, 'mail'));
        $this->assertFalse((new OperationalAlert('Unknown alert', 'Private message', url('/staff/unknown')))->shouldSend($staff, 'mail'));
    }

    public function test_real_queued_notification_pipeline_does_not_invoke_mail_after_permission_revocation(): void
    {
        $staff = $this->actor('hoa_staff');
        $alert = new OperationalAlert('New homeowner complaint', 'Restricted metadata', url('/staff/complaints'), [], 'manage_complaints');
        $job = new SendQueuedNotifications($staff, $alert, ['mail']);
        Role::query()->where('name', 'hoa_staff')->firstOrFail()->revokePermissionTo('manage_complaints');
        $manager = \Mockery::mock(ChannelManager::class, [app()])->makePartial();
        $manager->shouldNotReceive('driver');
        $job->handle($manager);
        $this->assertFalse($alert->shouldSend($staff, 'mail'));
    }

    public function test_integrity_audit_resolves_confirmed_properties_independently_of_street_text(): void
    {
        $owner = $this->resident(['block' => '1', 'lot' => '2', 'street' => 'Registered Street']);
        $tenant = $this->resident(['block' => '1', 'lot' => '2', 'street' => 'Different address spelling', 'ownership_type' => 'Tenant']);
        DB::table('homeowners')->where('id', $tenant->id)->update(['landlord_homeowner_id' => $owner->id]);
        $coowner = $this->resident(['block' => '1', 'lot' => '2', 'street' => 'Another spelling', 'ownership_type' => 'Co-owner']);
        $distinct = $this->resident(['block' => '1', 'lot' => '2', 'phase_suffix' => 'B']);
        $setting = $this->setting();
        $this->obligation($owner, $setting, 10);
        $this->obligation($coowner, $setting, 10);
        $this->obligation($distinct, $setting, 10);
        $counts = [DB::table('audit_logs')->count(), DB::table('homeowners')->count(), DB::table('dues_obligations')->count()];
        $report = $this->diagnostic();
        $this->assertSame(0, $report['checks']['invalid_current_landlord_links']);
        $this->assertSame(1, $report['review_observations']['duplicate_property_billing_groups']);
        $this->assertSame(0, $report['review_observations']['unresolved_current_landlord_links']);
        $this->assertSame(1, $report['review_observations']['confirmed_properties_with_conflicting_streets']);
        $this->assertSame($counts, [DB::table('audit_logs')->count(), DB::table('homeowners')->count(), DB::table('dues_obligations')->count()]);
        DB::table('homeowners')->where('id', $tenant->id)->update(['phase_suffix' => 'B', 'property_key' => $distinct->property_key]);
        $this->assertSame(1, $this->diagnostic()['checks']['invalid_current_landlord_links']);
    }

    public function test_integrity_audit_marks_unknown_legacy_suffixes_for_review_without_guessing(): void
    {
        $owner = $this->resident(['block' => '1', 'lot' => '2']);
        $tenant = $this->resident(['block' => '1', 'lot' => '2', 'ownership_type' => 'Tenant', 'landlord_homeowner_id' => $owner->id]);
        DB::table('homeowners')->where('id', $tenant->id)->update(['phase_suffix' => null, 'property_key' => null, 'street' => 'Legacy spelling']);
        $report = $this->diagnostic();
        $this->assertSame(0, $report['checks']['invalid_current_landlord_links']);
        $this->assertSame(1, $report['review_observations']['unresolved_current_landlord_links']);
        $this->assertNull($tenant->fresh()->phase_suffix);
    }

    private function actor(string $role): User
    {
        $user = User::factory()->create(['account_status' => 'Active']);
        $user->assignRole($role);

        return $user;
    }

    private function resident(array $overrides = []): Homeowner
    {
        $user = $this->actor('homeowner');

        return $user->homeowner()->create([...['phase' => 'Southville Phase I', 'phase_suffix' => 'A', 'block' => (string) $user->id, 'lot' => '2', 'street' => 'Synthetic Street', 'residency_date' => '2020-01-01', 'ownership_type' => 'Owner', 'status' => 'Active'], ...$overrides]);
    }

    private function complaint(User $staff): Complaint
    {
        return Complaint::query()->create(['homeowner_id' => $this->resident()->id, 'subject' => 'Original complaint', 'description' => 'A synthetic drainage concern requiring inspection.', 'category' => 'Common Area', 'priority' => 'High', 'status' => 'Pending', 'handled_by' => $staff->id]);
    }

    private function setting(array $overrides = []): DuesSetting
    {
        return DuesSetting::query()->create([...['name' => 'Synthetic audit dues', 'amount' => '500.00', 'frequency' => 'Monthly', 'due_day' => 10, 'starts_on' => '2026-01-01', 'is_active' => true], ...$overrides]);
    }

    private function obligation(Homeowner $owner, DuesSetting $setting, int $month): DuesObligation
    {
        return DuesObligation::query()->create(['homeowner_id' => $owner->id, 'dues_setting_id' => $setting->id, 'billing_year' => 2026, 'billing_month' => $month, 'due_date' => "2026-{$month}-28", 'amount_due' => '500.00', 'penalty_amount' => '0.00', 'amount_paid' => '0.00', 'status' => 'Pending']);
    }

    private function assertValidation(string $field, callable $callback): void
    {
        try {
            $callback();
            $this->fail("Expected a validation error for {$field}.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    private function diagnostic(): array
    {
        Artisan::call('hoa:audit-data', ['--json' => true]);

        return json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
    }
}
