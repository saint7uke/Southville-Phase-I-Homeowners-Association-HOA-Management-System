<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Complaints\CreateComplaint;
use App\Models\Complaint;
use App\Models\Homeowner;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class AdminComplaintCreationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Notification::fake();
        Storage::fake('local');
    }

    private function resident(): Homeowner
    {
        $user = User::factory()->create();
        $user->assignRole('homeowner');

        return $user->homeowner()->create(['house_number' => (string) $user->id, 'street' => 'Complaint Street', 'block' => '1', 'lot' => '1', 'phase' => 'Southville Phase I', 'residency_date' => '2020-01-01', 'ownership_type' => 'Owner', 'status' => 'Active']);
    }

    private function data(): array
    {
        return ['subject' => '  Street drainage concern  ', 'category' => 'Common Area', 'priority' => 'High', 'description' => '  Water remains in the drainage channel after every rainfall.  '];
    }

    public function test_administrators_and_staff_including_multirole_accounts_cannot_submit(): void
    {
        $resident = $this->resident();
        foreach (['hoa_admin' => 'admin', 'hoa_staff' => 'staff'] as $role => $guard) {
            $actor = User::factory()->create();
            $actor->assignRole([$role, 'homeowner']);
            $this->actingAs($actor, $guard)->get('/'.$guard.'/complaints/create')->assertForbidden();
            $this->assertFalse($actor->can('create', Complaint::class));
            try {
                app(CreateComplaint::class)->handle($resident, $this->data(), [], $actor);
                $this->fail('Management roles must not create complaints.');
            } catch (AuthorizationException) {
                $this->assertDatabaseCount('complaints', 0);
            }
        }
    }

    public function test_homeowner_submission_preserves_private_attachments_and_pending_status(): void
    {
        $resident = $this->resident();
        $this->actingAs($resident->user, 'homeowner');
        $complaint = app(CreateComplaint::class)->handle($resident, $this->data(), [UploadedFile::fake()->create('evidence.pdf', 120, 'application/pdf')], $resident->user);
        $this->assertSame('Pending', $complaint->status);
        $this->assertSame('Street drainage concern', $complaint->subject);
        $this->assertNull($complaint->handled_by);
        Storage::disk('local')->assertExists($complaint->caseAttachments()->sole()->path);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Complaint.created', 'auditable_id' => $complaint->id]);
    }

    public function test_direct_submission_cannot_forge_resident_status_assignment_or_category(): void
    {
        $resident = $this->resident();
        foreach ([['status' => 'Resolved'], ['handled_by' => $resident->user_id], ['homeowner_id' => 999], ['category' => 'Invalid'], ['description' => 'Too short']] as $forged) {
            try {
                app(CreateComplaint::class)->handle($resident, [...$this->data(), ...$forged], [], $resident->user);
                $this->fail('Forged complaint must fail.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('complaints', 0);
            }
        }
    }

    public function test_homeowner_cannot_submit_for_another_resident(): void
    {
        $one = $this->resident();
        $other = $this->resident();
        $this->expectException(ValidationException::class);
        app(CreateComplaint::class)->handle($other, $this->data(), [], $one->user);
    }

    public function test_attachment_storage_failure_rolls_back_submission(): void
    {
        $resident = $this->resident();
        $disk = \Mockery::mock();
        $disk->shouldReceive('putFileAs')->once()->andReturn(false);
        $disk->shouldReceive('delete')->twice()->andReturnTrue();
        Storage::shouldReceive('disk')->with('local')->times(3)->andReturn($disk);
        try {
            app(CreateComplaint::class)->handle($resident, $this->data(), [UploadedFile::fake()->create('evidence.pdf', 100, 'application/pdf')], $resident->user);
            $this->fail('Failed storage must reject creation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('attachments', $exception->errors());
        }
        $this->assertDatabaseCount('complaints', 0);
        $this->assertDatabaseCount('case_attachments', 0);
    }
}
