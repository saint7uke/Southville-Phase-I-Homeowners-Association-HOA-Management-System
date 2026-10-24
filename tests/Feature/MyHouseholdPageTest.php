<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Homeowner\Pages\MyHousehold;
use App\Models\Household;
use App\Models\User;
use App\Services\OwnHouseholdSummary;
use Barryvdh\DomPDF\Facade\Pdf;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class MyHouseholdPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->seed(RolesAndPermissionsSeeder::class);
        Filament::setCurrentPanel('homeowner');
    }

    private function resident(int $count = 1, string $lot = '1'): array
    {
        $user = User::factory()->create(['first_name' => 'AAA', 'middle_name' => null, 'last_name' => 'Registered Member', 'suffix' => null]);
        $user->assignRole('homeowner');
        $homeowner = $user->homeowner()->create(['street' => 'Private Test Street', 'block' => '1', 'lot' => $lot,
            'phase' => 'Southville Phase I', 'phase_suffix' => 'A', 'ownership_type' => 'Owner', 'residency_date' => '2020-01-01', 'status' => 'Active']);
        $household = Household::query()->where('property_key', $homeowner->property_key)->firstOrFail();
        for ($index = 1; $index < $count; $index++) {
            $household->members()->create(['full_name' => sprintf('Member %02d', $index), 'relationship' => 'Child',
                'residency_status' => 'Resident', 'date_of_birth' => '1999-02-03', 'sex' => 'Female']);
        }

        return [$user, $household, $household->members()->orderBy('full_name')->orderBy('id')->get()];
    }

    public static function memberCounts(): array
    {
        return [[1, false], [10, false], [11, true], [25, true]];
    }

    #[DataProvider('memberCounts')]
    public function test_page_counts_and_paginates_current_members_only(int $count, bool $paginated): void
    {
        [$user, $household, $members] = $this->resident($count);
        $this->actingAs($user, 'homeowner');
        $page = Livewire::test(MyHousehold::class)->assertSuccessful()->assertSee('Household overview')
            ->assertSee($household->identifier)->assertSee('Download household summary')
            ->assertViewHas('summary', fn ($summary): bool => $summary['members_count'] === $count && ! isset($summary['members']))
            ->assertCanSeeTableRecords($members->take(10))->assertCountTableRecords($count);
        $this->assertSame($paginated, $page->instance()->getTable()->isPaginated());
        $this->assertCount(min($count, 10), $page->instance()->getTableRecords());
        if ($paginated) {
            $page->assertCanNotSeeTableRecords($members->slice(10))->call('gotoPage', 2)
                ->assertCanSeeTableRecords($members->slice(10, 10))->assertCanNotSeeTableRecords($members->take(10));
        } else {
            $page->assertDontSeeHtml('class="fi-pagination');
        }
        $page->assertTableActionDoesNotExist('edit')->assertTableActionDoesNotExist('delete');
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('dues_obligations', 0);
    }

    public function test_review_notice_explains_confirmation_without_blocking_download(): void
    {
        [$user, $household] = $this->resident();
        $this->actingAs($user, 'homeowner');

        Livewire::test(MyHousehold::class)
            ->assertSee(__('Awaiting HOA confirmation'))
            ->assertSee(__("The HOA has not yet confirmed this household's membership details. You may still view and download this provisional summary. Contact the office if any information is incorrect."))
            ->assertSee(route('households.pdf', $household));

        $household->update(['needs_review' => false]);

        Livewire::test(MyHousehold::class)
            ->assertDontSee(__('Awaiting HOA confirmation'))
            ->assertSee(__('Download household summary'));
    }

    public function test_members_are_bounded_and_private_even_with_forged_page_size(): void
    {
        [$user, $household, $members] = $this->resident(25);
        [$stranger, $other] = $this->resident(1, '2');
        $other->members()->first()->update(['full_name' => 'Unrelated secret member']);
        $household->update(['contact_number' => '09123456789']);
        $this->actingAs($user, 'homeowner');
        DB::enableQueryLog();
        $page = Livewire::test(MyHousehold::class)->set('tableRecordsPerPage', 'all')
            ->assertDontSee('1999-02-03')->assertDontSee('09123456789')->assertDontSee('Unrelated secret member')
            ->assertDontSee('Female')->assertCanNotSeeTableRecords($members->slice(10));
        $this->assertCount(10, $page->instance()->getTableRecords());
        $this->assertNull($page->instance()->getTableRecord((string) $other->members()->first()->id));
        $memberQueries = collect(DB::getQueryLog())->filter(fn ($query): bool => str_contains($query['query'], '"full_name", "relationship", "residency_status"'));
        $this->assertNotEmpty($memberQueries);
        $this->assertTrue($memberQueries->contains(fn ($query): bool => str_contains($query['query'], 'limit 10')));
        foreach ($page->instance()->getTableRecords() as $record) {
            $this->assertSame(['id', 'household_id', 'full_name', 'relationship', 'residency_status'], array_keys($record->getAttributes()));
        }
        DB::disableQueryLog();
    }

    public function test_deleted_and_ended_members_are_excluded_and_duplicate_names_sort_by_id(): void
    {
        [$user, $household] = $this->resident();
        $first = $household->members()->create(['full_name' => 'Same Name', 'relationship' => 'Other', 'residency_status' => 'Resident']);
        $second = $household->members()->create(['full_name' => 'Same Name', 'relationship' => 'Other', 'residency_status' => 'Resident']);
        $ended = $household->members()->create(['full_name' => 'Ended Member', 'relationship' => 'Other', 'residency_status' => 'Moved', 'ended_at' => now()]);
        $deleted = $household->members()->create(['full_name' => 'Archived Member', 'relationship' => 'Other', 'residency_status' => 'Resident']);
        $deleted->delete();
        $this->actingAs($user, 'homeowner');
        $page = Livewire::test(MyHousehold::class)->assertCountTableRecords(3)->assertCanNotSeeTableRecords([$ended, $deleted]);
        $this->assertSame([$first->id, $second->id], $page->instance()->getTableRecords()->where('full_name', 'Same Name')->pluck('id')->all());
    }

    public function test_out_of_range_pages_are_clamped_after_membership_changes(): void
    {
        [$user, $household, $members] = $this->resident(25);
        $this->actingAs($user, 'homeowner');
        $page = Livewire::test(MyHousehold::class)->call('gotoPage', 999)->assertSet('paginators.page', 3)
            ->assertCanSeeTableRecords($members->slice(20));
        $household->members()->whereIn('id', $members->slice(15)->pluck('id'))->update(['ended_at' => now()]);
        $page->call('$refresh')->assertSet('paginators.page', 2)->assertCanSeeTableRecords($members->slice(10, 5))
            ->assertViewHas('summary', fn ($summary): bool => $summary['members_count'] === 15);
        $household->members()->whereIn('id', $members->slice(8)->pluck('id'))->update(['ended_at' => now()]);
        $page->call('$refresh')->assertSet('paginators.page', 1)->assertCanSeeTableRecords($members->take(8))
            ->assertDontSeeHtml('class="fi-pagination');
    }

    public function test_property_move_resets_page_and_never_retains_old_household_data(): void
    {
        [$user, $old, $members] = $this->resident(25);
        $this->actingAs($user, 'homeowner');
        $page = Livewire::test(MyHousehold::class)->call('gotoPage', 2);
        $user->homeowner->update(['lot' => '3']);
        $page->call('$refresh')->assertSet('paginators.page', 1)->assertDontSee($old->identifier)
            ->assertCanNotSeeTableRecords($members->slice(1))
            ->assertViewHas('summary', fn ($summary): bool => $summary['id'] !== $old->id && $summary['members_count'] === 1);
    }

    public function test_expired_or_archived_membership_displays_guidance_without_member_data(): void
    {
        [$user, $household, $members] = $this->resident(11);
        $this->actingAs($user, 'homeowner');
        $page = Livewire::test(MyHousehold::class)->call('gotoPage', 2);
        $members->first()->update(['ended_at' => now()]);
        $page->call('$refresh')->assertViewHas('summary', null)->assertSee('Household information unavailable')
            ->assertDontSee($household->identifier)->assertDontSee('Member 01')->assertDontSee('Download household summary');
        $this->assertNull(app(OwnHouseholdSummary::class)->forUser($user));
    }

    public function test_archived_households_and_changed_account_access_are_rechecked(): void
    {
        [$user, $household] = $this->resident();
        $this->actingAs($user, 'homeowner');
        $page = Livewire::test(MyHousehold::class);
        $household->delete();
        $this->assertNull(app(OwnHouseholdSummary::class)->forUser($user));
        $page->call('$refresh')->assertViewHas('summary', null)->assertDontSee($household->identifier);
        $household->restore();
        $page->call('$refresh')->assertSee($household->identifier);
        DB::table('users')->where('id', $user->id)->update(['account_status' => 'Inactive']);
        $this->assertNull(app(OwnHouseholdSummary::class)->forUser($user));
        $page->call('$refresh')->assertViewHas('summary', null)->assertDontSee($household->identifier);
        DB::table('users')->where('id', $user->id)->update(['account_status' => 'Active', 'deleted_at' => now()]);
        $this->assertNull(app(OwnHouseholdSummary::class)->forUser($user));
    }

    public function test_empty_current_member_list_is_distinct_from_unavailable_membership(): void
    {
        [$user, $household] = $this->resident();
        $household->members()->update(['ended_at' => now()]);
        // Simulate the member list becoming empty after the authorized household was resolved.
        $service = Mockery::mock(new OwnHouseholdSummary);
        $service->shouldReceive('householdFor')->andReturn($household);
        app()->instance(OwnHouseholdSummary::class, $service);
        $this->actingAs($user, 'homeowner');
        Livewire::test(MyHousehold::class)->assertSee('No current household members')
            ->assertDontSee('Household information unavailable')->assertCountTableRecords(0);
    }

    public function test_shared_summary_contract_and_pdf_remain_complete_and_restricted(): void
    {
        [$user, $household] = $this->resident(25);
        [$stranger] = $this->resident(1, '2');
        $summary = app(OwnHouseholdSummary::class)->forUser($user);
        $this->assertCount(25, $summary['members']);
        $this->assertSame(['full_name', 'relationship', 'residency_status'], array_keys($summary['members'][0]));
        $this->actingAs($stranger, 'homeowner')->get(route('households.pdf', $household))->assertNotFound();
        Pdf::shouldReceive('loadView')->once()->with('pdf.household', Mockery::on(function ($data): bool {
            $this->assertFalse($data['manager']);
            $this->assertCount(25, $data['members']);
            $this->assertSame(['full_name', 'relationship', 'residency_status'], array_keys($data['members']->last()->getAttributes()));
            $this->assertStringStartsWith('data:image/png;base64,', $data['letterhead']);

            return true;
        }))->andReturnSelf();
        Pdf::shouldReceive('setPaper')->once()->with('a4')->andReturnSelf();
        Pdf::shouldReceive('setOption')->once()->andReturnSelf();
        Pdf::shouldReceive('download')->once()->andReturn(response('synthetic-pdf', 200, ['Content-Type' => 'application/pdf']));
        $this->actingAs($user, 'homeowner')->get(route('households.pdf', $household).'?page=2')->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_missing_letterhead_fails_safely_for_homeowner_download(): void
    {
        [$user, $household] = $this->resident();
        config(['exports.letterhead_directory' => storage_path('framework/testing/nonexistent-letterhead')]);

        $response = $this->actingAs($user, 'homeowner')->get(route('households.pdf', $household))
            ->assertStatus(503)
            ->assertSee(__('The export letterhead is unavailable. Please contact the administrator.'))
            ->assertSee('/homeowner/my-household');
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_filipino_labels_and_escaped_member_content_render_without_edit_actions(): void
    {
        [$user, $household] = $this->resident();
        $household->members()->create(['full_name' => '<script>Not executed</script>', 'relationship' => 'Other', 'residency_status' => 'Temporarily away']);
        $this->actingAs($user, 'homeowner');
        app()->setLocale('fil');
        Livewire::test(MyHousehold::class)->assertSee('Buod ng sambahayan')->assertSee('Pansamantalang wala')
            ->assertSeeHtml('&lt;script&gt;Not executed&lt;/script&gt;')->assertDontSeeHtml('<script>Not executed</script>')
            ->assertTableActionDoesNotExist('edit');
    }

    public function test_email_verification_role_and_resident_archival_are_rechecked(): void
    {
        [$user, $household] = $this->resident();
        $this->actingAs($user, 'homeowner');
        $page = Livewire::test(MyHousehold::class);
        DB::table('users')->where('id', $user->id)->update(['email_verified_at' => null]);
        $page->call('$refresh')->assertViewHas('summary', null)->assertDontSee($household->identifier);
        DB::table('users')->where('id', $user->id)->update(['email_verified_at' => now()]);
        $user->removeRole('homeowner');
        $this->assertNull(app(OwnHouseholdSummary::class)->forUser($user));
        $page->call('$refresh')->assertForbidden();
        $user->assignRole('homeowner');
        $page = Livewire::test(MyHousehold::class);
        $user->homeowner->delete();
        $this->assertNull(app(OwnHouseholdSummary::class)->forUser($user));
        $page->call('$refresh')->assertForbidden();
    }

    public function test_household_context_cannot_be_changed_to_another_residents_household(): void
    {
        [$user] = $this->resident();
        [, $other] = $this->resident(1, '2');
        $this->actingAs($user, 'homeowner');
        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::test(MyHousehold::class)->set('householdContextId', $other->id);
    }

    public function test_guest_and_staff_cannot_access_the_homeowner_page(): void
    {
        $this->get('/homeowner/my-household')->assertRedirect(route('filament.homeowner.auth.login'));
        $staff = User::factory()->create();
        $staff->assignRole('hoa_staff');
        $this->actingAs($staff, 'staff')->get('/homeowner/my-household')->assertForbidden();
    }
}
