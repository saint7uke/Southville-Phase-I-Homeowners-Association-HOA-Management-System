<?php

declare(strict_types=1);

namespace Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class GlobalFaviconTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_and_resident_auth_pages_use_the_hoa_favicon(): void
    {
        foreach (['/', '/portal/login', '/portal/register'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertSee('rel="icon" type="image/png"', false)
                ->assertSee(asset('images/HOA.png'), false);
        }
    }

    public function test_every_filament_panel_uses_the_hoa_favicon(): void
    {
        foreach (['admin', 'staff', 'homeowner'] as $panelId) {
            $this->assertSame(asset('images/HOA.png'), Filament::getPanel($panelId)->getFavicon());
        }
    }
}
