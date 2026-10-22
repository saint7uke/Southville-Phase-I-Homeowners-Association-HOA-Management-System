<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Homeowner\Pages\MyHousehold;
use App\Filament\Homeowner\Pages\MyProfile;
use App\Filament\Homeowner\Resources as ResidentResources;
use App\Filament\Pages\Households;
use App\Filament\Pages\Projects;
use App\Filament\Pages\Reports;
use App\Filament\Resources as ManagementResources;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

use function Filament\Support\generate_icon_html;

final class DashboardNavigationTest extends TestCase
{
    use RefreshDatabase;

    private const ICONS = [
        Dashboard::class => Heroicon::OutlinedHome,
        Households::class => Heroicon::OutlinedHomeModern,
        Projects::class => Heroicon::OutlinedWrenchScrewdriver,
        ManagementResources\Homeowners\HomeownerResource::class => Heroicon::OutlinedUsers,
        ManagementResources\Complaints\ComplaintResource::class => Heroicon::OutlinedExclamationTriangle,
        ManagementResources\ServiceRequests\ServiceRequestResource::class => Heroicon::OutlinedClipboardDocumentList,
        ManagementResources\DuesSettings\DuesSettingResource::class => Heroicon::OutlinedReceiptPercent,
        ManagementResources\Payments\PaymentResource::class => Heroicon::OutlinedBanknotes,
        ManagementResources\Certificates\CertificateResource::class => Heroicon::OutlinedDocumentCheck,
        ManagementResources\Announcements\AnnouncementResource::class => Heroicon::OutlinedMegaphone,
        ManagementResources\ContactMessages\ContactMessageResource::class => Heroicon::OutlinedEnvelope,
        Reports::class => Heroicon::OutlinedChartBarSquare,
        ManagementResources\AuditLogs\AuditLogResource::class => Heroicon::OutlinedClock,
        ManagementResources\Users\UserResource::class => Heroicon::OutlinedUserGroup,
        ManagementResources\Roles\RoleResource::class => Heroicon::OutlinedShieldCheck,
        ManagementResources\SystemSettings\SystemSettingResource::class => Heroicon::OutlinedCog6Tooth,
        MyProfile::class => Heroicon::OutlinedUserCircle,
        MyHousehold::class => Heroicon::OutlinedHomeModern,
        ResidentResources\Complaints\ComplaintResource::class => Heroicon::OutlinedExclamationTriangle,
        ResidentResources\ServiceRequests\ServiceRequestResource::class => Heroicon::OutlinedClipboardDocumentList,
        ResidentResources\Dues\DuesObligationResource::class => Heroicon::OutlinedReceiptPercent,
        ResidentResources\Payments\PaymentResource::class => Heroicon::OutlinedBanknotes,
        ResidentResources\Certificates\CertificateResource::class => Heroicon::OutlinedDocumentCheck,
        ResidentResources\Announcements\AnnouncementResource::class => Heroicon::OutlinedMegaphone,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public static function panels(): array
    {
        return [
            'administrator' => ['admin', 'hoa_admin', 16, '/admin/households'],
            'staff' => ['staff', 'hoa_staff', 11, '/staff/households'],
            'homeowner' => ['homeowner', 'homeowner', 9, '/homeowner/my-household'],
        ];
    }

    #[DataProvider('panels')]
    public function test_every_visible_menu_item_has_a_distinct_matching_outlined_icon(string $panel, string $role, int $count, string $path): void
    {
        $this->login($panel, $role);
        $items = $this->navigation();
        $this->assertCount($count, $items);

        $icons = [];
        foreach ($items as $item) {
            $this->assertArrayHasKey($item->getKey(), self::ICONS);
            $icon = self::ICONS[$item->getKey()];
            $this->assertSame($icon, $item->getIcon());
            $this->assertSame($icon, $item->getActiveIcon());
            $this->assertNotEmpty($item->getLabel());

            $svg = generate_icon_html($icon)->toHtml();
            $this->assertStringContainsString('<svg', $svg);
            $this->assertStringContainsString('stroke="currentColor"', $svg);
            $this->assertStringContainsString('fill="none"', $svg);
            $this->assertStringContainsString('viewBox="0 0 24 24"', $svg);
            $icons[] = $icon->value;
        }

        $this->assertCount(count($icons), array_unique($icons), 'Visible sidebar icons must be distinct within each panel.');
    }

    #[DataProvider('panels')]
    public function test_sidebars_render_without_changing_panel_access(string $panel, string $role, int $count, string $path): void
    {
        $this->login($panel, $role);
        if (getenv('HOA_BROWSER_EXPORT_NAVIGATION_FIXTURES') === 'true') {
            URL::forceRootUrl('http://dashboard-navigation.test');
        }

        $response = $this->get($path)->assertOk()->assertSee('fi-sidebar-item-icon', false);
        $this->assertCount($count, $this->navigation());

        if (getenv('HOA_BROWSER_EXPORT_NAVIGATION_FIXTURES') !== 'true') {
            return;
        }
        if (! app()->environment('testing') || config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Only synthetic in-memory SQLite responses may be exported.');
        }

        $directory = storage_path('framework/testing/dashboard-navigation');
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        file_put_contents($directory.'/'.$panel.'.html', $response->getContent(), LOCK_EX);
    }

    public function test_staff_navigation_does_not_gain_administrator_only_pages(): void
    {
        $this->login('staff', 'hoa_staff');
        $keys = $this->navigation()->map(fn (NavigationItem $item): string => $item->getKey())->all();

        foreach ([Projects::class, ManagementResources\AuditLogs\AuditLogResource::class, ManagementResources\Users\UserResource::class,
            ManagementResources\Roles\RoleResource::class, ManagementResources\SystemSettings\SystemSettingResource::class] as $page) {
            $this->assertNotContains($page, $keys);
            $this->assertFalse($page::canAccess());
        }
    }

    public function test_homeowner_navigation_does_not_gain_management_resources(): void
    {
        $this->login('homeowner', 'homeowner');

        foreach ($this->navigation() as $item) {
            $this->assertTrue($item->getKey() === Dashboard::class || str_starts_with($item->getKey(), 'App\\Filament\\Homeowner\\'));
        }
    }

    private function login(string $panel, string $role): void
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        if ($role === 'homeowner') {
            $user->homeowner()->create(['phase' => 'Southville Phase I', 'phase_suffix' => 'A', 'block' => '1', 'lot' => '2',
                'street' => 'Synthetic Street', 'ownership_type' => 'Owner', 'residency_date' => '2020-01-01', 'status' => 'Active']);
        }

        $this->actingAs($user, $panel);
        Filament::setCurrentPanel($panel);
    }

    /** @return Collection<int, NavigationItem> */
    private function navigation(): Collection
    {
        return collect(Filament::getNavigation())->flatMap(fn ($group) => $group->getItems())->values();
    }
}
