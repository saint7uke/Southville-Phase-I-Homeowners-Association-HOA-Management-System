<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Households\ManageHousehold;
use App\Actions\Households\ManageHouseholdHead;
use App\Exports\HouseholdSummaryExport;
use App\Filament\Pages\HouseholdMembers;
use App\Filament\Pages\Households;
use App\Filament\Schemas\HouseholdHeadReview;
use App\Models\AuditLog;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;
use App\Services\Exports\HouseholdExportData;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Maatwebsite\Excel\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class HouseholdHeadAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Notification::fake();
        Queue::fake();
        $this->admin = User::factory()->create();
        $this->admin->assignRole('hoa_admin');
        Filament::setCurrentPanel('admin');
        $this->actingAs($this->admin, 'admin');
    }

    private function household(): Household
    {
        return Household::create(['identifier' => (string) Str::uuid(), 'address_key' => hash('sha256', (string) Str::uuid()),
            'street' => null, 'block' => '7', 'lot' => (string) (Household::count() + 1), 'phase' => 'Southville Phase I', 'phase_suffix' => 'A']);
    }

    private function member(Household $household, string $name = 'Member One', array $data = []): HouseholdMember
    {
        return $household->members()->create(['full_name' => $name, 'relationship' => 'Other', 'residency_status' => 'Resident', ...$data]);
    }

    private function review(Household $household, HouseholdMember $head): array
    {
        return [...HouseholdHeadReview::data($household->fresh(), $head->id), 'relationships' => $household->members()->whereNull('ended_at')->get()->mapWithKeys(fn ($member) => [$member->id => $member->id === $head->id ? 'Head' : 'Other'])->all(), 'review_confirmed' => true];
    }

    public function test_assignment_replacement_and_same_head_reconciliation_are_atomic_and_audited(): void
    {
        $household = $this->household();
        $one = $this->member($household);
        $two = $this->member($household, 'Member Two');
        app(ManageHouseholdHead::class)->assign($household->id, $this->review($household, $one), $this->admin);
        $this->assertSame($one->id, $household->fresh()->head_member_id);
        $this->assertSame('Head', $one->fresh()->relationship);
        app(ManageHouseholdHead::class)->assign($household->id, $this->review($household, $two), $this->admin);
        $this->assertSame('Other', $one->fresh()->relationship);
        $this->assertSame('Head', $two->fresh()->relationship);
        DB::table('household_members')->where('id', $two->id)->update(['relationship' => 'Relative']);
        app(ManageHouseholdHead::class)->assign($household->id, $this->review($household, $two), $this->admin);
        $this->assertSame('Head', $two->fresh()->relationship);
        $this->assertTrue(AuditLog::where('auditable_type', Household::class)->where('action', 'Household.updated')->exists());
        $this->assertTrue(AuditLog::where('auditable_type', HouseholdMember::class)->where('user_id', $this->admin->id)->exists());
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('dues_obligations', 0);
    }

    public function test_revision_uses_the_supplied_locked_roster_with_consistent_field_order(): void
    {
        $household = $this->household();
        $member = $this->member($household);
        $action = app(ManageHouseholdHead::class);
        $revision = $action->revision($household);
        $members = $household->members()->withTrashed()->get();
        $this->assertSame($revision, $action->revision($household, $members));

        DB::table('household_members')->where('id', $member->id)->update(['relationship' => 'Relative']);
        // An already supplied snapshot is not silently re-queried. Assignment supplies locked rows.
        $this->assertSame($revision, $action->revision($household, $members));
        $this->assertNotSame($revision, $action->revision($household));
    }

    #[DataProvider('badReviews')]
    public function test_incomplete_or_forged_reviews_do_not_change_assignment(string $case): void
    {
        $household = $this->household();
        $one = $this->member($household);
        $two = $this->member($household, 'Member Two');
        $data = $this->review($household, $one);
        match ($case) {
            'missing_member' => $data['relationships'] = [$one->id => 'Head'],
            'extra_member' => $data['relationships'][99999] = 'Other',
            'second_head' => $data['relationships'][$two->id] = 'Head',
            'selected_not_head' => $data['relationships'][$one->id] = 'Other',
            'not_confirmed' => $data['review_confirmed'] = false,
            'unknown_role' => $data['relationships'][$two->id] = 'King',
            'malformed_role' => $data['relationships'][$two->id] = ['Other'],
            'missing_revision' => $data['head_revision'] = '',
        };
        try {
            app(ManageHouseholdHead::class)->assign($household->id, $data, $this->admin);
            $this->fail('Invalid review accepted.');
        } catch (ValidationException) {
            $this->assertNull($household->fresh()->head_member_id);
            $this->assertSame('Other', $one->fresh()->relationship);
        }
    }

    public static function badReviews(): array
    {
        return array_map(fn ($case) => [$case], ['missing_member', 'extra_member', 'second_head', 'selected_not_head', 'not_confirmed', 'unknown_role', 'malformed_role', 'missing_revision']);
    }

    #[DataProvider('ineligibleHeads')]
    public function test_head_must_be_eligible_and_in_the_same_household(string $case): void
    {
        $household = $this->household();
        $one = $this->member($household);
        $head = match ($case) {
            'other_household' => $this->member($this->household(), 'Other Household'),
            default => $this->member($household, 'Ineligible Person'),
        };
        if ($case === 'archived') {
            $head->delete();
        }
        if ($case === 'ended') {
            $head->update(['ended_at' => now()]);
        }
        if ($case === 'moved') {
            $head->update(['residency_status' => 'Moved']);
        }
        $data = $this->review($household, $one);
        $data['head_member_id'] = $head->id;
        $this->expectException(ValidationException::class);
        app(ManageHouseholdHead::class)->assign($household->id, $data, $this->admin);
    }

    public static function ineligibleHeads(): array
    {
        return [['other_household'], ['archived'], ['ended'], ['moved']];
    }

    #[DataProvider('staleChanges')]
    public function test_changed_membership_or_head_rejects_stale_review(string $change): void
    {
        $household = $this->household();
        $one = $this->member($household);
        $data = $this->review($household, $one);
        match ($change) {
            'name' => $one->update(['full_name' => 'Changed Name']),
            'relationship' => $one->update(['relationship' => 'Sibling']),
            'status' => $one->update(['residency_status' => 'Temporarily away']),
            'ended' => $one->update(['ended_at' => now()]),
            'archived' => $one->delete(),
            'new_member' => $this->member($household, 'New Member'),
            'head' => $household->update(['head_member_id' => $one->id]),
        };
        try {
            app(ManageHouseholdHead::class)->assign($household->id, $data, $this->admin);
            $this->fail('Stale review accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('head_revision', $exception->errors());
        }
    }

    public static function staleChanges(): array
    {
        return array_map(fn ($value) => [$value], ['name', 'relationship', 'status', 'ended', 'archived', 'new_member', 'head']);
    }

    public function test_clearing_preserves_history_and_flags_review(): void
    {
        $household = $this->household();
        $head = $this->member($household);
        $past = $this->member($household, 'Former Head', ['relationship' => 'Head', 'ended_at' => now()]);
        app(ManageHouseholdHead::class)->assign($household->id, $this->review($household, $head), $this->admin);
        app(ManageHouseholdHead::class)->clear($household->id, app(ManageHouseholdHead::class)->revision($household->fresh()), $this->admin);
        $this->assertNull($household->fresh()->head_member_id);
        $this->assertTrue($household->fresh()->needs_review);
        $this->assertSame('Other', $head->fresh()->relationship);
        $this->assertSame('Head', $past->fresh()->relationship);
    }

    public function test_ordinary_member_forms_cannot_create_or_independently_demote_a_head(): void
    {
        $household = $this->household();
        try {
            app(ManageHousehold::class)->saveMember($household->id, null, ['full_name' => 'Forged Head', 'relationship' => 'Head', 'residency_status' => 'Resident'], $this->admin);
            $this->fail('Null-ID loophole accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('relationship', $exception->errors());
        }
        $head = $this->member($household);
        app(ManageHouseholdHead::class)->assign($household->id, $this->review($household, $head), $this->admin);
        $this->expectException(ValidationException::class);
        app(ManageHousehold::class)->saveMember($household->id, $head->id, ['full_name' => $head->full_name, 'relationship' => 'Relative', 'residency_status' => 'Resident'], $this->admin);
    }

    public function test_departure_archival_and_restoration_never_reinstate_the_head(): void
    {
        $household = $this->household();
        $head = $this->member($household);
        app(ManageHouseholdHead::class)->assign($household->id, $this->review($household, $head), $this->admin);
        app(ManageHousehold::class)->saveMember($household->id, $head->id, ['full_name' => $head->full_name, 'relationship' => 'Head', 'residency_status' => 'Temporarily away'], $this->admin);
        $this->assertSame($head->id, $household->fresh()->head_member_id);
        app(ManageHousehold::class)->saveMember($household->id, $head->id, ['full_name' => $head->full_name, 'relationship' => 'Head', 'residency_status' => 'Moved'], $this->admin);
        $this->assertNull($household->fresh()->head_member_id);
        $this->assertSame('Head', $head->fresh()->relationship);
        $other = $this->member($household, 'Current Head');
        app(ManageHouseholdHead::class)->assign($household->id, $this->review($household, $other), $this->admin);
        app(ManageHousehold::class)->archive('member', $other->id, false, $this->admin);
        $this->assertNull($household->fresh()->head_member_id);
        app(ManageHousehold::class)->archive('member', $other->id, true, $this->admin);
        $this->assertSame('Other', $other->fresh()->relationship);
        $this->assertNull($household->fresh()->head_member_id);
    }

    public function test_linked_head_departure_and_account_deletion_clear_the_pointer(): void
    {
        foreach (['move', 'delete', 'archive_owner'] as $operation) {
            $user = User::factory()->create();
            $user->assignRole('homeowner');
            $owner = $user->homeowner()->create(['street' => null, 'block' => $operation, 'lot' => '55', 'phase' => 'Southville Phase I', 'phase_suffix' => 'A', 'residency_date' => '2020-01-01', 'ownership_type' => 'Owner', 'status' => 'Active']);
            $member = HouseholdMember::where('current_homeowner_id', $owner->id)->sole();
            $household = $member->household;
            app(ManageHouseholdHead::class)->assign($household->id, $this->review($household, $member), $this->admin);
            match ($operation) {
                'move' => $owner->update(['lot' => '56']),
                'delete' => $user->delete(),
                'archive_owner' => $owner->delete(),
            };
            $this->assertNull($household->fresh()->head_member_id);
            $this->assertNotNull($member->fresh()->ended_at);
            $this->assertSame('Head', $member->fresh()->relationship);
        }
    }

    public function test_staff_can_assign_but_homeowners_cannot_and_archived_households_are_denied(): void
    {
        $household = $this->household();
        $head = $this->member($household);
        $staff = User::factory()->create();
        $staff->assignRole('hoa_staff');
        app(ManageHouseholdHead::class)->assign($household->id, $this->review($household, $head), $staff);
        $resident = User::factory()->create();
        $resident->assignRole('homeowner');
        try {
            app(ManageHouseholdHead::class)->clear($household->id, app(ManageHouseholdHead::class)->revision($household->fresh()), $resident);
            $this->fail('Unauthorized clear accepted.');
        } catch (AuthorizationException) {
            $this->assertSame($head->id, $household->fresh()->head_member_id);
        }
        $household->delete();
        $this->expectException(ModelNotFoundException::class);
        app(ManageHouseholdHead::class)->assign($household->id, $this->review($household, $head), $staff);
    }

    public function test_legacy_conflicts_are_readable_and_ordinary_saves_preserve_review_flags(): void
    {
        $household = $this->household();
        $one = $this->member($household, 'Pointer Person', ['relationship' => 'Relative']);
        $two = $this->member($household, 'Label Person', ['relationship' => 'Head']);
        $household->update(['head_member_id' => $one->id, 'needs_review' => true]);
        $before = $household->fresh()->getAttributes();
        $status = app(ManageHouseholdHead::class)->status($household->fresh());
        $this->assertTrue($status['conflict']);
        $this->assertSame('Pointer Person', $status['head_name']);
        $data = app(HouseholdExportData::class)->prepare($household->fresh(), true);
        $this->assertSame($status['warning'], $data['warning']);
        Livewire::test(Households::class)->callTableAction('editHousehold', $household, data: ['contact_number' => '09171234567'])
            ->assertHasNoTableActionErrors()->assertNotified('Household Information Updated Successfully!');
        $this->assertSame('Relative', $one->fresh()->relationship);
        app(ManageHousehold::class)->save($household->id, [...$household->fresh()->getAttributes(), 'contact_number' => '09171234567'], $this->admin);
        $this->assertTrue($household->fresh()->needs_review);
        $this->assertSame($before['head_member_id'], $household->fresh()->head_member_id);
        $this->assertSame('Head', $two->fresh()->relationship);
        $this->expectException(ValidationException::class);
        app(ManageHousehold::class)->save($household->id, [...$household->fresh()->getAttributes(), 'head_member_id' => $two->id], $this->admin);
    }

    public function test_failure_after_member_updates_rolls_back_every_change(): void
    {
        $household = $this->household();
        $head = $this->member($household);
        try {
            DB::transaction(function () use ($household, $head) {
                app(ManageHouseholdHead::class)->assign($household->id, $this->review($household, $head), $this->admin);
                throw new \RuntimeException('Synthetic rollback');
            });
        } catch (\RuntimeException) {
            $this->assertNull($household->fresh()->head_member_id);
            $this->assertSame('Other', $head->fresh()->relationship);
        }
    }

    public static function panels(): array
    {
        return [['admin'], ['staff']];
    }

    public function test_returning_manual_head_is_other_until_explicit_reassignment(): void
    {
        $household = $this->household();
        $head = $this->member($household, 'Former Head', ['relationship' => 'Head', 'residency_status' => 'Moved', 'ended_at' => now()]);
        // Even a stale historical pointer must not reinstate leadership on return.
        $household->update(['head_member_id' => $head->id]);
        app(ManageHousehold::class)->saveMember($household->id, $head->id,
            ['full_name' => 'Former Head', 'relationship' => 'Head', 'residency_status' => 'Resident'], $this->admin);
        $this->assertNull($head->fresh()->ended_at);
        $this->assertSame('Other', $head->fresh()->relationship);
        $this->assertNull($household->fresh()->head_member_id);
        $this->assertTrue($household->fresh()->needs_review);
    }

    public function test_head_assignment_does_not_regenerate_legacy_property_identity(): void
    {
        $household = $this->household();
        $head = $this->member($household);
        DB::table('households')->where('id', $household->id)->update(['phase_suffix' => 'LEGACY', 'property_key' => 'legacy-key-not-to-rewrite']);
        $before = $household->fresh()->only(['phase', 'phase_suffix', 'property_key', 'address_key']);
        app(ManageHouseholdHead::class)->assign($household->id, $this->review($household, $head), $this->admin);
        $this->assertSame($before, $household->fresh()->only(array_keys($before)));
        app(ManageHouseholdHead::class)->clear($household->id, app(ManageHouseholdHead::class)->revision($household->fresh()), $this->admin);
        $this->assertSame($before, $household->fresh()->only(array_keys($before)));
    }

    public function test_legacy_selected_relationship_and_ended_head_history_are_not_silently_rewritten(): void
    {
        $household = $this->household();
        $head = $this->member($household, 'Legacy Person', ['relationship' => 'Relative']);
        $household->update(['head_member_id' => $head->id]);
        app(ManageHousehold::class)->saveMember($household->id, $head->id, ['full_name' => 'Legacy Person', 'relationship' => 'Relative', 'residency_status' => 'Resident', 'sex' => 'Female'], $this->admin);
        $this->assertSame('Relative', $head->fresh()->relationship);
        $this->assertTrue(app(ManageHouseholdHead::class)->status($household->fresh())['conflict']);
        $ended = $this->member($household, 'Ended Head', ['relationship' => 'Head', 'ended_at' => now(), 'residency_status' => 'Moved']);
        app(ManageHousehold::class)->archive('member', $ended->id, false, $this->admin);
        app(ManageHousehold::class)->archive('member', $ended->id, true, $this->admin);
        $this->assertSame('Head', $ended->fresh()->relationship);
        $this->assertNotNull($ended->fresh()->ended_at);
        $this->assertSame($head->id, $household->fresh()->head_member_id);
    }

    public function test_stale_or_malformed_clear_does_not_remove_a_newer_assignment(): void
    {
        $household = $this->household();
        $head = $this->member($household);
        $revision = app(ManageHouseholdHead::class)->revision($household);
        app(ManageHouseholdHead::class)->assign($household->id, $this->review($household, $head), $this->admin);
        foreach ([$revision, ['forged']] as $value) {
            try {
                app(ManageHouseholdHead::class)->clear($household->id, $value, $this->admin);
                $this->fail('Invalid clear accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('head_revision', $exception->errors());
            }
        }
        $this->assertSame($head->id, $household->fresh()->head_member_id);
    }

    public function test_failure_persisting_pointer_rolls_back_prior_relationship_and_audit_updates(): void
    {
        $household = $this->household();
        $head = $this->member($household);
        $auditCount = AuditLog::count();
        Household::updating(fn () => throw new \RuntimeException('Synthetic pointer failure'));
        try {
            app(ManageHouseholdHead::class)->assign($household->id, $this->review($household, $head), $this->admin);
            $this->fail('Synthetic failure was not raised.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Synthetic pointer failure', $exception->getMessage());
        }
        $this->assertNull($household->fresh()->head_member_id);
        $this->assertSame('Other', $head->fresh()->relationship);
        $this->assertSame($auditCount, AuditLog::count());
    }

    public function test_failed_livewire_review_keeps_assignment_and_sends_no_success(): void
    {
        $household = $this->household();
        $head = $this->member($household);
        Livewire::withQueryParams(['household' => $household->id])->test(HouseholdMembers::class)
            ->callTableAction('setHouseholdHead', $head, data: [...$this->review($household, $head), 'review_confirmed' => false])
            ->assertHasTableActionErrors(['review_confirmed'])
            ->assertNotNotified('Household Head and Relationships Confirmed Successfully!');
        $this->assertNull($household->fresh()->head_member_id);
        Queue::assertNothingPushed();
    }

    public function test_exports_escape_canonical_head_and_warn_about_legacy_conflicts(): void
    {
        $household = $this->household();
        $head = $this->member($household, '<script>unsafe</script>', ['relationship' => 'Relative']);
        $household->update(['head_member_id' => $head->id]);
        $data = app(HouseholdExportData::class)->prepare($household->fresh(), true);
        $html = view('pdf.household', $data)->render();
        $this->assertStringNotContainsString('<script>unsafe</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;unsafe&lt;/script&gt;', $html);
        $this->assertStringContainsString('Household head and relationship records conflict.', $html);
        $bytes = \Maatwebsite\Excel\Facades\Excel::raw(new HouseholdSummaryExport($data, 'Test administrator'), Excel::XLSX);
        Storage::disk('local')->put('head-review.xlsx', $bytes);
        $book = IOFactory::load(Storage::disk('local')->path('head-review.xlsx'));
        $this->assertStringContainsString('Household head: <script>unsafe</script>', $book->getActiveSheet()->getCell('A9')->getValue());
        $this->assertStringContainsString('HOA review is required.', $book->getActiveSheet()->getCell('A9')->getValue());
    }

    public function test_read_only_audit_reports_ids_without_changing_records(): void
    {
        $household = $this->household();
        $head = $this->member($household, 'Private Resident Name', ['relationship' => 'Head']);
        $auditCount = AuditLog::count();
        $this->artisan('hoa:audit-household-heads', ['--json' => true])->expectsOutputToContain('conflicting_assignment')->assertSuccessful();
        $this->assertNull($household->fresh()->head_member_id);
        $this->assertSame('Head', $head->fresh()->relationship);
        $this->assertSame($auditCount, AuditLog::count());
    }

    #[DataProvider('panels')]
    public function test_both_livewire_entry_points_require_review_and_confirm_saved_results(string $panel): void
    {
        if ($panel === 'staff') {
            $staff = User::factory()->create();
            $staff->assignRole('hoa_staff');
            Filament::setCurrentPanel('staff');
            $this->actingAs($staff, 'staff');
        }
        $household = $this->household();
        $one = $this->member($household);
        $two = $this->member($household, 'Member Two');
        Livewire::withQueryParams(['household' => $household->id])->test(HouseholdMembers::class)
            ->callTableAction('setHouseholdHead', $one, data: $this->review($household, $one))
            ->assertHasNoTableActionErrors()->assertNotified('Household Head and Relationships Confirmed Successfully!');
        Livewire::test(Households::class)->callTableAction('editHousehold', $household, data: [...$household->fresh()->getAttributes(), ...$this->review($household, $two), 'review_head' => true])
            ->assertHasNoTableActionErrors()->assertNotified('Household Head and Relationships Confirmed Successfully!');
        $this->assertSame($two->id, $household->fresh()->head_member_id);
    }
}
