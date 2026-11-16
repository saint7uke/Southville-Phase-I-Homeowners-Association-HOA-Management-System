<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CaseAttachment;
use App\Models\Complaint;
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

    public function test_private_jpeg_and_png_previews_are_inline_and_not_cacheable(): void
    {
        $owner = $this->homeowner();
        $complaint = Complaint::create(['homeowner_id' => $owner->id, 'subject' => 'Pictures', 'description' => 'Synthetic image evidence.', 'category' => 'Others', 'status' => 'Pending', 'priority' => 'Medium']);
        foreach (['jpg' => 'image/jpeg', 'png' => 'image/png'] as $extension => $mime) {
            $file = UploadedFile::fake()->image('proof.'.$extension, 40, 80);
            Storage::disk('local')->put('proof.'.$extension, file_get_contents($file->getRealPath()));
            $attachment = $complaint->caseAttachments()->create(['disk' => 'local', 'path' => 'proof.'.$extension, 'original_name' => 'proof.'.$extension, 'mime_type' => $mime, 'size_bytes' => $file->getSize()]);
            $before = $attachment->fresh()->getAttributes();
            $response = $this->actingAs($owner->user, 'homeowner')->get(route('case-attachments.preview', $attachment));
            $response->assertOk()->assertHeader('Content-Type', $mime)->assertHeader('Content-Disposition', 'inline')->assertHeader('X-Content-Type-Options', 'nosniff');
            $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
            $this->assertSame(file_get_contents($file->getRealPath()), $response->streamedContent());
            $this->assertSame($before, $attachment->fresh()->getAttributes());
            $this->actingAs($this->homeowner()->user, 'homeowner')->get(route('case-attachments.preview', $attachment))->assertNotFound();
        }
        Notification::assertNothingSent();
    }

    public function test_image_preview_rejects_non_images_false_mime_missing_and_archived_records(): void
    {
        $owner = $this->homeowner();
        $this->actingAs($owner->user, 'homeowner');
        $complaint = Complaint::create(['homeowner_id' => $owner->id, 'subject' => 'Pictures', 'description' => 'Synthetic evidence.', 'category' => 'Others', 'status' => 'Pending', 'priority' => 'Medium']);
        foreach (['application/pdf', 'application/msword', 'image/jpeg', 'image/png', 'image/svg+xml'] as $mime) {
            $path = 'not-an-image-'.md5($mime);
            Storage::disk('local')->put($path, '<html>Not an image</html>');
            $attachment = $complaint->caseAttachments()->create(['disk' => 'local', 'path' => $path, 'original_name' => 'fake.jpg', 'mime_type' => $mime, 'size_bytes' => 24]);
            $this->get(route('case-attachments.preview', $attachment))->assertNotFound();
        }
        $file = UploadedFile::fake()->image('real.png');
        Storage::disk('local')->put('real.png', file_get_contents($file->getRealPath()));
        $attachment->update(['path' => 'real.png', 'mime_type' => 'image/jpeg']);
        $this->get(route('case-attachments.preview', $attachment))->assertNotFound();
        $attachment->update(['path' => 'missing.png', 'mime_type' => 'image/png']);
        $this->get(route('case-attachments.preview', $attachment))->assertNotFound();
        $attachment->update(['path' => 'real.png']);
        $this->get(route('case-attachments.preview', $attachment))->assertOk();
        $complaint->delete();
        $this->get(route('case-attachments.preview', $attachment))->assertNotFound();
        $this->get('/case-attachments/999999/preview')->assertNotFound();
    }

    public function test_guests_are_not_served_private_image_previews(): void
    {
        $owner = $this->homeowner();
        $complaint = Complaint::create(['homeowner_id' => $owner->id, 'subject' => 'Private', 'description' => 'Private evidence.', 'category' => 'Others', 'status' => 'Pending', 'priority' => 'Medium']);
        $attachment = $complaint->caseAttachments()->create(['disk' => 'local', 'path' => 'private.jpg', 'original_name' => 'private.jpg', 'mime_type' => 'image/jpeg', 'size_bytes' => 100]);
        $this->get(route('case-attachments.preview', $attachment))->assertRedirect();
    }

    private function homeowner(): Homeowner
    {
        $index = User::query()->count() + 1;
        $user = User::factory()->create(['email' => "case{$index}@example.test", 'account_status' => 'Active']);
        $user->assignRole('homeowner');

        return $user->homeowner()->create(['house_number' => (string) $index, 'street' => 'Case Street', 'block' => (string) $index, 'lot' => (string) $index, 'phase' => 'Southville Phase I', 'residency_date' => '2021-01-01', 'ownership_type' => 'Owner', 'emergency_contact_name' => 'Contact', 'emergency_contact_number' => '09123456789', 'status' => 'Active']);
    }
}
