<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Households\ManageHousehold;
use App\Actions\Projects\ManageProject;
use App\Filament\Pages\Households;
use App\Filament\Pages\Projects;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;
use App\Services\OwnHouseholdSummary;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

final class PhaseThreeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Notification::fake();
        Storage::fake('local');
    }

    private function resident(string $number = '7', string $ownership = 'Co-owner'): User
    {
        $user = User::factory()->create();
        $user->assignRole('homeowner');
        $user->homeowner()->create(['house_number' => $number, 'street' => 'Sample Street', 'block' => '1', 'lot' => '2', 'phase' => 'Southville Phase I', 'ownership_type' => $ownership, 'residency_date' => '2020-01-01', 'status' => 'Active']);

        return $user->refresh();
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('hoa_admin');

        return $user;
    }

    public function test_shared_property_has_one_household_and_address_move_only_moves_one_membership(): void
    {
        $one = $this->resident();
        $two = $this->resident();
        $this->assertDatabaseCount('households', 1);
        $this->assertDatabaseCount('household_members', 2);
        $old = Household::first();
        $oldMember = HouseholdMember::where('current_homeowner_id', $one->homeowner->id)->first();
        $old->update(['head_member_id' => $oldMember->id]);
        $one->homeowner->update(['house_number' => '8']);
        $this->assertDatabaseCount('households', 2);
        $this->assertDatabaseCount('household_members', 3);
        $this->assertNull($oldMember->fresh()->current_homeowner_id);
        $this->assertNotNull($oldMember->fresh()->ended_at);
        $this->assertTrue($old->fresh()->needs_review);
        $this->assertSame($old->id, HouseholdMember::where('current_homeowner_id', $two->homeowner->id)->first()->household_id);
        $this->assertSame('7', $two->homeowner->fresh()->house_number);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('dues_obligations', 0);
    }

    public function test_resident_summary_excludes_sensitive_fields_and_unrelated_households(): void
    {
        $one = $this->resident();
        $two = $this->resident();
        $stranger = $this->resident('99');
        $household = Household::where('address_key', $one->homeowner->address_key)->first();
        HouseholdMember::where('current_homeowner_id', $two->homeowner->id)->update(['date_of_birth' => '1990-01-01', 'sex' => 'Female']);
        $summary = app(OwnHouseholdSummary::class)->forUser($one);
        $this->assertCount(2, $summary['members']);
        $this->assertSame(['full_name', 'relationship', 'residency_status'], array_keys($summary['members'][0]));
        $this->actingAs($stranger, 'homeowner')->get(route('households.pdf', $household))->assertNotFound();
        $this->actingAs($one, 'homeowner')->get(route('households.pdf', $household))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_backfill_dry_run_is_read_only_and_apply_is_idempotent(): void
    {
        $user = $this->resident();
        DB::table('household_members')->delete();
        DB::table('households')->delete();
        $this->artisan('hoa:backfill-households')->assertSuccessful();
        $this->assertDatabaseCount('households', 0);
        $this->artisan('hoa:backfill-households', ['--apply' => true])->assertSuccessful();
        $this->artisan('hoa:backfill-households', ['--apply' => true])->assertSuccessful();
        $this->assertDatabaseCount('households', 1);
        $this->assertDatabaseCount('household_members', 1);
        $user->homeowner->update(['phase' => null]);
        $this->artisan('hoa:backfill-households')->expectsOutputToContain('Incomplete address')->assertSuccessful();
    }

    public function test_staff_cannot_archive_households_and_current_members_block_archival(): void
    {
        $this->resident();
        $household = Household::first();
        $staff = User::factory()->create();
        $staff->assignRole('hoa_staff');
        try {
            app(ManageHousehold::class)->archive('household', $household->id, false, $staff);
            $this->fail('Staff must not archive.');
        } catch (AuthorizationException) {
            $this->assertFalse($household->fresh()->trashed());
        }
        $this->expectException(ValidationException::class);
        app(ManageHousehold::class)->archive('household', $household->id, false, $this->admin());
    }

    public function test_projects_are_private_until_completed_and_published_and_restoration_unpublishes(): void
    {
        $admin = $this->admin();
        $data = ['title' => 'Actual test project', 'description' => 'Verified test description', 'project_date' => today()->toDateString(), 'completion_status' => 'Completed', 'publish' => false];
        $project = app(ManageProject::class)->save(null, $data, [UploadedFile::fake()->image('photo.jpg', 200, 200)], $admin);
        $image = $project->images()->first();
        Storage::disk('local')->assertExists($image->path);
        $this->assertSame('image/png', $image->mime_type);
        $this->get(route('projects.image', $image))->assertNotFound();
        $this->get('/')->assertDontSee('Actual test project');
        app(ManageProject::class)->save($project->id, [...$data, 'publish' => true], [], $admin);
        $this->get(route('projects.image', $image))->assertOk();
        $this->get('/')->assertSee('Actual test project');
        app(ManageProject::class)->archive($project->id, false, $admin);
        $this->get(route('projects.image', $image))->assertNotFound();
        app(ManageProject::class)->archive($project->id, true, $admin);
        $this->assertNull($project->fresh()->published_at);
        $this->get(route('projects.image', $image))->assertNotFound();
    }

    public function test_invalid_project_upload_rolls_back_records(): void
    {
        $this->expectException(ValidationException::class);
        try {
            app(ManageProject::class)->save(null, ['title' => 'Project', 'description' => 'Description', 'project_date' => today()->toDateString(), 'completion_status' => 'Completed', 'publish' => true], [UploadedFile::fake()->create('bad.php', 1, 'text/plain')], $this->admin());
        } finally {
            $this->assertDatabaseCount('projects', 0);
            $this->assertDatabaseCount('project_images', 0);
            $this->assertSame([], Storage::disk('local')->allFiles('projects'));
        }
    }

    public function test_failed_project_storage_cleans_files_and_rolls_back_database(): void
    {
        $admin = $this->admin();
        $disk = \Mockery::mock();
        $disk->shouldReceive('put')->once()->andReturn(false);
        $disk->shouldReceive('delete')->once()->andReturnTrue();
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);
        try {
            app(ManageProject::class)->save(null, ['title' => 'Project', 'description' => 'Description', 'project_date' => today()->toDateString(), 'completion_status' => 'Completed', 'publish' => true], [UploadedFile::fake()->image('photo.jpg', 200, 200)], $admin);
            $this->fail('Failed storage must reject saving.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('images', $exception->errors());
        }
        $this->assertDatabaseCount('projects', 0);
        $this->assertDatabaseCount('project_images', 0);
    }

    public function test_locale_is_allowlisted_persisted_and_used_for_notifications(): void
    {
        $this->postJson('/locale', ['locale' => 'xx'])->assertUnprocessable();
        $this->postJson('/locale', ['locale' => 'fil'])->assertOk()->assertSessionHas('locale', 'fil');
        $this->get('/login')->assertOk()->assertSee('lang="fil"', false)->assertSee('Mag-sign in');
        $user = $this->resident();
        $this->actingAs($user, 'homeowner')->postJson('/locale', ['locale' => 'fil'])->assertOk();
        $this->assertSame('fil', $user->fresh()->preferredLocale());
        $this->assertSame('fil', $user->fresh()->preferred_locale);
        $this->actingAs($user, 'homeowner')->postJson('/locale', ['locale' => 'en'])->assertOk();
        $this->assertSame('en', $user->fresh()->preferredLocale());
    }

    public function test_new_management_pages_render_with_existing_panel_authorization(): void
    {
        $admin = $this->admin();
        foreach (['/admin/households', '/admin/projects', '/admin/certificates'] as $url) {
            $this->actingAs($admin, 'admin')->get($url)->assertOk();
        }
        $resident = $this->resident();
        $this->actingAs($resident, 'homeowner')->get('/homeowner/my-household')->assertOk();
    }

    public function test_project_business_validation_is_announced_in_the_management_modal(): void
    {
        $this->actingAs($this->admin(), 'admin');
        Filament::setCurrentPanel('admin');
        Livewire::test(Projects::class)->callAction('create', data: [
            'title' => 'Unfinished project', 'description' => 'Not ready for publication.', 'project_date' => today()->toDateString(), 'completion_status' => 'Planned', 'publish' => true,
        ])->assertNotified('Please review the form');
        $this->assertDatabaseCount('projects', 0);
    }

    public function test_household_conflicts_remain_visible_in_the_modal(): void
    {
        $resident = $this->resident();
        $household = Household::first();
        $this->actingAs($this->admin(), 'admin');
        Filament::setCurrentPanel('admin');
        Livewire::test(Households::class)->callAction('createHousehold', data: $household->only(['house_number', 'street', 'block', 'lot', 'phase']))
            ->assertNotified('Please review the form')->assertHasActionErrors(['house_number']);
        $this->assertDatabaseCount('households', 1);
    }
}
