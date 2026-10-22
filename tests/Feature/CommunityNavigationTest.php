<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\Households;
use App\Filament\Pages\Projects;
use App\Filament\Resources\Homeowners\HomeownerResource;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;
use Filament\Support\Icons\Heroicon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

use function Filament\Support\generate_icon_html;

final class CommunityNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_community_icons_render_as_outlined_svgs_in_both_navigation_states(): void
    {
        foreach ([
            Households::class => Heroicon::OutlinedHomeModern,
            Projects::class => Heroicon::OutlinedWrenchScrewdriver,
            HomeownerResource::class => Heroicon::OutlinedUsers,
        ] as $page => $icon) {
            $this->assertSame('Community', $page::getNavigationGroup());
            $this->assertSame($icon, $page::getNavigationIcon());
            $this->assertSame($icon, $page::getActiveNavigationIcon());

            $svg = generate_icon_html($page::getNavigationIcon())->toHtml();

            $this->assertStringContainsString('<svg', $svg);
            $this->assertStringContainsString('stroke="currentColor"', $svg);
            $this->assertStringContainsString('fill="none"', $svg);
            $this->assertStringContainsString('viewBox="0 0 24 24"', $svg);
        }
    }

    public function test_admin_navigation_contains_all_three_community_icons(): void
    {
        $items = $this->communityNavigation('admin', 'hoa_admin');

        $this->assertSame(Heroicon::OutlinedHomeModern, $items['Households & Provisional RBI']->getIcon());
        $this->assertSame(Heroicon::OutlinedWrenchScrewdriver, $items['Projects']->getIcon());
        $this->assertSame(Heroicon::OutlinedUsers, $items['Homeowners']->getIcon());
        $this->assertTrue(Projects::canAccess());
    }

    public function test_staff_navigation_shares_household_and_homeowner_icons_but_excludes_projects(): void
    {
        $items = $this->communityNavigation('staff', 'hoa_staff');

        $this->assertSame(Heroicon::OutlinedHomeModern, $items['Households & Provisional RBI']->getIcon());
        $this->assertSame(Heroicon::OutlinedUsers, $items['Homeowners']->getIcon());
        $this->assertArrayNotHasKey('Projects', $items->all());
        $this->assertFalse(Projects::canAccess());
        $this->assertNotContains(Projects::class, Filament::getCurrentPanel()->getPages());
    }

    public function test_homeowner_permissions_do_not_expose_management_navigation(): void
    {
        $items = $this->communityNavigation('staff', 'homeowner');

        $this->assertCount(0, $items);
        $this->assertFalse(Households::canAccess());
        $this->assertFalse(Projects::canAccess());
        $this->assertFalse(HomeownerResource::canAccess());
    }

    /** @return Collection<string, NavigationItem> */
    private function communityNavigation(string $panel, string $role): Collection
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user, $panel);
        Filament::setCurrentPanel($panel);

        return collect(Filament::getNavigation())
            ->filter(fn ($group): bool => $group->getLabel() === 'Community')
            ->flatMap(fn ($group) => $group->getItems())
            ->keyBy(fn (NavigationItem $item): string => $item->getLabel());
    }
}
