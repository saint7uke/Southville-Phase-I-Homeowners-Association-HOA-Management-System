<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
