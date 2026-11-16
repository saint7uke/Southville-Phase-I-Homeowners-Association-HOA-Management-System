<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Projects\ManageProject;
use App\Filament\Pages\Projects;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ProjectPublicationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
        Notification::fake();
        $this->admin = User::factory()->create(['account_status' => 'Active']);
        $this->admin->assignRole('hoa_admin');
        $this->actingAs($this->admin, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public static function unfinished(): array
    {
        return [['Planned'], ['In Progress']];
    }

    private function data(string $status = 'Completed', bool $publish = false): array
    {
        return ['title' => 'Synthetic project', 'description' => 'Preserved description', 'project_date' => today()->toDateString(), 'completion_status' => $status, 'publish' => $publish];
    }

    #[DataProvider('unfinished')]
    public function test_unfinished_projects_create_and_edit_privately_with_images(string $status): void
    {
        $project = app(ManageProject::class)->save(null, $this->data($status), [UploadedFile::fake()->image('project.png', 150, 150)], $this->admin);
        $image = $project->images()->sole();
        Livewire::test(Projects::class)->callAction(TestAction::make('edit')->table($project), data: [...$this->data($status), 'title' => 'Updated private project'])
            ->assertHasNoActionErrors()->assertNotified('Project Updated Successfully!');
        $this->assertNull($project->fresh()->published_at);
        $this->assertSame($status, $project->fresh()->completion_status);
        $this->assertSame($image->id, $project->images()->sole()->id);
        Storage::disk('local')->assertExists($image->path);
        auth('admin')->logout();
        $this->get('/')->assertDontSee('Updated private project');
        $this->get(route('projects.image', $image))->assertNotFound();
    }

    #[DataProvider('unfinished')]
    public function test_form_creates_unfinished_project_without_a_publish_value(string $status): void
    {
        $data = $this->data($status);
        unset($data['publish']);
        Livewire::test(Projects::class)->callAction('create', data: $data)
            ->assertHasNoActionErrors()->assertNotified('Project Created Successfully!');
        $this->assertNull(Project::sole()->published_at);
    }

    public function test_reactive_status_clears_publication_without_losing_other_fields(): void
    {
        $page = Livewire::test(Projects::class)->mountAction('create');
        $path = $page->instance()->getSchema($page->instance()->getMountedActionSchemaName())->getStatePath();
        $page->set($path.'.title', 'Retained title')->set($path.'.completion_status', 'Completed')->set($path.'.publish', true)
            ->set($path.'.completion_status', 'In Progress')->assertSet($path.'.publish', false)
            ->assertSet($path.'.title', 'Retained title')->set($path.'.completion_status', 'Completed')->assertSet($path.'.publish', false);
    }

    public function test_unpublishing_requires_confirmation_and_cancel_does_not_write(): void
    {
        $project = app(ManageProject::class)->save(null, $this->data('Completed', true), [UploadedFile::fake()->image('project.png', 150, 150)], $this->admin);
        $before = $project->fresh()->getAttributes();
        $page = Livewire::test(Projects::class)->mountAction(TestAction::make('edit')->table($project));
        $path = $page->instance()->getSchema($page->instance()->getMountedActionSchemaName())->getStatePath();
        $page->set($path.'.completion_status', 'Planned')->call('unmountAction');
        $this->assertSame($before, $project->fresh()->getAttributes());
        Livewire::test(Projects::class)->callAction(TestAction::make('edit')->table($project), data: $this->data('Planned'))
            ->assertHasActionErrors(['confirm_unpublish'])->assertNotNotified('Project Updated Successfully!');
        try {
            app(ManageProject::class)->save($project->id, $this->data('Planned'), [], $this->admin);
            $this->fail('Unpublishing needs explicit confirmation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('confirm_unpublish', $exception->errors());
        }
        $this->assertSame($before, $project->fresh()->getAttributes());
        Livewire::test(Projects::class)->callAction(TestAction::make('edit')->table($project), data: [...$this->data('Planned'), 'confirm_unpublish' => true])
            ->assertHasNoActionErrors()->assertNotified('Project Updated Successfully!');
        $this->assertNull($project->fresh()->published_at);
        $this->assertSame('Planned', $project->fresh()->completion_status);
        auth('admin')->logout();
        $this->get('/')->assertDontSee('Synthetic project');
        $this->get(route('projects.image', $project->images()->sole()))->assertNotFound();
    }

    public function test_completed_can_save_privately_then_publish_and_restore_unpublished(): void
    {
        $project = app(ManageProject::class)->save(null, $this->data(), [], $this->admin);
        $this->assertNull($project->published_at);
        app(ManageProject::class)->save($project->id, $this->data('Completed', true), [], $this->admin);
        $this->assertNotNull($project->fresh()->published_at);
        app(ManageProject::class)->archive($project->id, false, $this->admin);
        app(ManageProject::class)->archive($project->id, true, $this->admin);
        $this->assertNull($project->fresh()->published_at);
    }

    public function test_crafted_publication_future_dates_and_bad_images_do_not_write(): void
    {
        foreach ([[$this->data('Planned', true), [], 'publish'], [$this->data('In Progress', true), [], 'publish'],
            [[...$this->data(), 'project_date' => today()->addDay()->toDateString()], [], 'project_date'],
            [$this->data(), [UploadedFile::fake()->create('invalid.php', 1, 'text/plain')], 'images.0']] as [$data, $images, $field]) {
            try {
                app(ManageProject::class)->save(null, $data, $images, $this->admin);
                $this->fail('Invalid input must not save a project.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey($field, $exception->errors());
            }
            $this->assertDatabaseCount('projects', 0);
        }
    }

    public function test_staff_and_homeowners_cannot_manage_projects(): void
    {
        foreach (['hoa_staff', 'homeowner'] as $role) {
            $user = User::factory()->create(['account_status' => 'Active']);
            $user->assignRole($role);
            $this->actingAs($user, 'admin')->get('/admin/projects')->assertForbidden();
            try {
                app(ManageProject::class)->save(null, $this->data('Planned'), [], $user);
                $this->fail('Project management is administrator-only.');
            } catch (AuthorizationException) {
                $this->assertDatabaseCount('projects', 0);
            }
        }
    }

    public function test_each_image_accepts_fifteen_megabytes_and_rejects_larger_files(): void
    {
        $project = app(ManageProject::class)->save(null, $this->data('Planned'), [UploadedFile::fake()->image('large.png', 150, 150)->size(15360)], $this->admin);
        $this->assertSame(1, $project->images()->count());
        try {
            app(ManageProject::class)->save($project->id, [...$this->data('Planned'), 'title' => 'Must roll back'], [UploadedFile::fake()->image('oversize.png', 150, 150)->size(15361)], $this->admin);
            $this->fail('Images larger than 15 MB must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('images.0', $exception->errors());
        }
        $this->assertSame('Synthetic project', $project->fresh()->title);
        $this->assertSame(1, $project->images()->count());
    }

    public function test_future_dates_are_allowed_only_for_planned_projects_and_translations_exist(): void
    {
        $planned = app(ManageProject::class)->save(null, [...$this->data('Planned'), 'project_date' => today()->addDay()->toDateString()], [], $this->admin);
        $this->assertNull($planned->published_at);
        foreach (['In Progress', 'Completed'] as $status) {
            try {
                app(ManageProject::class)->save(null, [...$this->data($status), 'project_date' => today()->addDay()->toDateString()], [], $this->admin);
                $this->fail('Future project dates remain invalid.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('project_date', $exception->errors());
            }
        }
        $this->assertSame('I-save ang Proyekto', __('Save Project', [], 'fil'));
        $this->assertNotSame('Confirm removal from the public website before saving an unfinished project.', __('Confirm removal from the public website before saving an unfinished project.', [], 'fil'));
    }
}
