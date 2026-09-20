<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CaseAttachment;
use App\Models\Homeowner;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class CaseAttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Notification::fake();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_homeowner_can_submit_up_to_three_private_complaint_attachments(): void
    {
        $homeowner = $this->homeowner();
        $response = $this->actingAs($homeowner->user, 'web')->post(route('portal.complaints.store'), [
            'subject' => 'Drainage attachments',
            'description' => 'These documents show the drainage concern clearly.',
            'category' => 'Common Area',
            'priority' => 'High',
            'attachments' => [
                UploadedFile::fake()->create('first-proof.pdf', 100, 'application/pdf'),
                UploadedFile::fake()->create('second-proof.pdf', 100, 'application/pdf'),
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('case_attachments', 2);
        $attachment = CaseAttachment::query()->firstOrFail();
        $this->assertStringNotContainsString('first-proof', $attachment->path);
        Storage::disk('local')->assertExists($attachment->path);
        $this->actingAs($homeowner->user, 'web')->get(route('case-attachments.download', $attachment))->assertOk();
        $this->actingAs($this->homeowner()->user, 'web')->get(route('case-attachments.download', $attachment))->assertNotFound();
    }

    public function test_more_than_three_request_attachments_are_rejected_before_storage(): void
    {
        $homeowner = $this->homeowner();
        $files = collect(range(1, 4))->map(fn (int $number): UploadedFile => UploadedFile::fake()->create("file-{$number}.pdf", 10, 'application/pdf'))->all();

        $this->actingAs($homeowner->user, 'web')->post(route('portal.requests.store'), [
            'request_type' => 'Gate Pass',
            'details' => 'Visitor access request.',
            'attachments' => $files,
        ])->assertSessionHasErrors('attachments');

        $this->assertDatabaseCount('service_requests', 0);
        $this->assertDatabaseCount('case_attachments', 0);
    }

    private function homeowner(): Homeowner
    {
        $index = User::query()->count() + 1;
        $user = User::factory()->create(['email' => "case{$index}@example.test", 'account_status' => 'Active']);
        $user->assignRole('homeowner');

        return $user->homeowner()->create(['house_number' => (string) $index, 'street' => 'Case Street', 'block' => (string) $index, 'lot' => (string) $index, 'phase' => 'Southville Phase I', 'residency_date' => '2021-01-01', 'ownership_type' => 'Owner', 'emergency_contact_name' => 'Contact', 'emergency_contact_number' => '09123456789', 'status' => 'Active']);
    }
}
