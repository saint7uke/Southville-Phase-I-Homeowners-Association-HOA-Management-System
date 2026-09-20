<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_mounts_react_app_with_latest_published_announcement(): void
    {
        $admin = User::factory()->create();
        Announcement::query()->create([
            'title' => 'Community clean-up schedule',
            'content' => 'Residents are invited to join this Saturday.',
            'category' => 'Community',
            'status' => 'Published',
            'published_at' => now(),
            'created_by' => $admin->id,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('id="landing-root"', false)
            ->assertSee('Community clean-up schedule');
    }

    public function test_draft_announcement_is_not_exposed_on_landing_page(): void
    {
        $admin = User::factory()->create();
        Announcement::query()->create([
            'title' => 'Private draft notice',
            'content' => 'Not ready for publication.',
            'category' => 'Notice',
            'status' => 'Draft',
            'created_by' => $admin->id,
        ]);

        $this->get('/')->assertOk()->assertDontSee('Private draft notice');
    }

    public function test_resident_only_expired_and_scheduled_announcements_are_not_exposed_publicly(): void
    {
        $admin = User::factory()->create();
        foreach ([
            ['title' => 'Resident only', 'audience' => 'Residents', 'published_at' => now()],
            ['title' => 'Expired notice', 'audience' => 'Public', 'published_at' => now()->subDay(), 'expires_at' => now()->subMinute()],
            ['title' => 'Scheduled notice', 'audience' => 'Public', 'published_at' => now()->addMinute()],
        ] as $attributes) {
            Announcement::query()->create([
                'content' => 'Not for public display yet.',
                'category' => 'Notice',
                'status' => 'Published',
                'created_by' => $admin->id,
                ...$attributes,
            ]);
        }

        $this->get('/')
            ->assertOk()
            ->assertDontSee('Resident only')
            ->assertDontSee('Expired notice')
            ->assertDontSee('Scheduled notice');
    }

    public function test_public_contact_form_records_valid_message_and_rejects_honeypot_submission(): void
    {
        $this->post('/contact', [
            'name' => 'Maria Santos',
            'email' => 'maria@example.test',
            'phone' => '09171234567',
            'subject' => 'Office hours',
            'message' => 'May I confirm the office hours this Friday?',
        ])->assertRedirect('/#contact');

        $this->assertDatabaseHas('contact_messages', [
            'email' => 'maria@example.test',
            'status' => 'New',
        ]);

        $this->post('/contact', [
            'name' => 'Bot',
            'email' => 'bot@example.test',
            'subject' => 'Spam',
            'message' => 'Spam message',
            'website' => 'https://spam.example',
        ])->assertSessionHasErrors('website');
        $this->assertDatabaseCount('contact_messages', 1);
    }

    public function test_contact_api_returns_json_and_uses_the_same_validation_contract(): void
    {
        $this->postJson('/api/contact', [
            'name' => 'Ana Reyes',
            'email' => 'ana@example.test',
            'subject' => 'Document inquiry',
            'message' => 'How can I request a certificate of residency?',
        ])->assertCreated()->assertHeader('Location')->assertJsonStructure(['data' => ['message']]);

        $this->postJson('/api/contact', [
            'name' => 'Bot',
            'email' => 'bot@example.test',
            'subject' => 'Spam',
            'message' => 'Spam',
            'website' => 'https://spam.example',
        ])->assertUnprocessable()->assertJsonValidationErrors('website');
    }

    public function test_public_branding_uses_settings_without_exposing_the_private_storage_path(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('branding/hoa-logo.png', 'fake-png');
        SystemSetting::current()->update([
            'hoa_name' => 'Southville Community Association',
            'contact_email' => 'office@southville.test',
            'address' => 'Community Center, Brgy. Inocencio',
            'logo_path' => 'branding/hoa-logo.png',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Southville Community Association')
            ->assertSee('branding\\/logo', false)
            ->assertDontSee('branding/hoa-logo.png');

        $this->get(route('branding.logo'))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
