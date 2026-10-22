<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

final class LanguageSwitcherTest extends TestCase
{
    use RefreshDatabase;

    private function assertOneSwitcher(string $html, string $locale = 'en'): void
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        $xpath = new DOMXPath($document);
        $this->assertSame(1, $xpath->query('//*[@data-locale-switcher]')->length);
        $this->assertSame(1, $xpath->query('//select[@id="hoa-locale"]')->length);
        $this->assertSame(1, $xpath->query('//label[@for="hoa-locale" and contains(@class,"hoa-locale-sr-only")]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="hoa-locale-status" and @role="status" and @aria-live="polite"]')->length);
        $this->assertSame(1, $xpath->query('//select[@id="hoa-locale"]/option[@value="'.$locale.'" and @selected]')->length);
        $this->assertSame(0, $xpath->query('//*[@data-locale-switcher]/@style')->length);
    }

    public function test_component_has_one_accessible_compact_control_and_canonical_options(): void
    {
        foreach (['en', 'fil'] as $locale) {
            app()->setLocale($locale);
            $html = Blade::render('<x-locale-switcher appearance="light" />');
            $this->assertOneSwitcher($html, $locale);
            $this->assertStringContainsString('data-locale-theme="light"', $html);
            $this->assertStringContainsString('>English</option>', $html);
            $this->assertStringContainsString('>Filipino</option>', $html);
        }
    }

    public function test_guest_authentication_screens_have_a_single_switcher_and_csrf_token(): void
    {
        foreach (['login' => '/login', 'registration' => '/homeowner/register', 'forgot-password' => '/homeowner/forgot-password',
            'reset-password' => '/homeowner/reset-password/synthetic-token?email=resident%40example.test', 'filament-password' => '/admin/password-reset/request'] as $name => $url) {
            foreach (['en', 'fil'] as $locale) {
                $response = $this->withSession(['locale' => $locale])->get($url)->assertOk();
                $response->assertSee('lang="'.$locale.'"', false)->assertSee('name="csrf-token"', false);
                $this->assertOneSwitcher($response->getContent(), $locale);
                if ($locale === 'en') {
                    $this->exportFixture($name, $response->getContent());
                }
            }
        }
    }

    public function test_homepage_does_not_duplicate_the_react_navigation_control(): void
    {
        $response = $this->get('/')->assertOk()->assertSee('id="landing-root"', false)->assertDontSee('id="hoa-locale"', false);
        $this->exportFixture('homepage', $response->getContent());
    }

    public function test_status_cards_and_legacy_portal_have_one_shared_switcher(): void
    {
        $submitted = view('portal.auth.submitted', ['statusTitle' => 'Synthetic application status', 'statusLead' => 'Synthetic review only.',
            'status' => 'pending', 'statusLabel' => 'Pending', 'registeredEmail' => null, 'approvedAt' => null, 'feedback' => null,
            'statusUrl' => route('home')])->render();
        $expired = view('portal.auth.status-link-expired', ['loginUrl' => route('login')])->render();
        $portal = view('layouts.portal')->render();
        foreach (['submitted' => $submitted, 'expired' => $expired, 'legacy-portal' => $portal] as $name => $html) {
            $this->assertOneSwitcher($html);
            $this->assertStringContainsString('name="csrf-token"', $html);
            $this->exportFixture($name, $html);
        }
    }

    public function test_dashboard_headers_have_one_switcher_before_the_user_menu(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        foreach (['admin' => 'hoa_admin', 'staff' => 'hoa_staff', 'homeowner' => 'homeowner'] as $guard => $role) {
            $user = User::factory()->create(['preferred_locale' => 'fil']);
            $user->assignRole($role);
            if ($guard === 'homeowner') {
                $user->homeowner()->create(['street' => 'Test Street', 'block' => '1', 'lot' => '2', 'phase' => 'Southville Phase I',
                    'phase_suffix' => 'A', 'ownership_type' => 'Owner', 'residency_date' => '2020-01-01', 'status' => 'Active']);
            }
            $html = $this->actingAs($user, $guard)->get('/'.$guard)->assertOk()->getContent();
            $this->assertOneSwitcher($html, 'fil');
            $this->assertStringContainsString('fi-user-menu-trigger', $html);
            $this->assertLessThan(strpos($html, 'fi-user-menu-trigger'), strpos($html, 'data-locale-switcher'));
            $this->exportFixture($guard, $html);
            foreach (['admin', 'staff', 'homeowner', 'web'] as $name) {
                auth()->guard($name)->logout();
            }
        }
    }

    public function test_guest_and_each_panel_keep_the_existing_locale_persistence_contract(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->postJson('/locale', ['locale' => 'fil'])->assertOk()->assertExactJson(['locale' => 'fil'])->assertSessionHas('locale', 'fil');
        $this->get('/login')->assertSee('lang="fil"', false);
        foreach (['admin' => 'hoa_admin', 'staff' => 'hoa_staff', 'homeowner' => 'homeowner'] as $guard => $role) {
            $user = User::factory()->create();
            $user->assignRole($role);
            $this->actingAs($user, $guard)->postJson('/locale', ['locale' => 'fil'])->assertOk()->assertExactJson(['locale' => 'fil']);
            $this->assertSame('fil', $user->fresh()->preferred_locale);
            $this->postJson('/locale', ['locale' => 'en'])->assertOk()->assertExactJson(['locale' => 'en']);
            $this->assertSame('en', $user->fresh()->preferred_locale);
            foreach (['admin', 'staff', 'homeowner', 'web'] as $name) {
                auth()->guard($name)->logout();
            }
        }
    }

    public function test_invalid_locale_and_inactive_account_cannot_modify_preferences(): void
    {
        $this->postJson('/locale', ['locale' => 'tl'])->assertUnprocessable()->assertJsonValidationErrors('locale');
        $user = User::factory()->create(['account_status' => 'Suspended', 'preferred_locale' => 'en']);
        $this->actingAs($user, 'admin')->postJson('/locale', ['locale' => 'fil'])->assertForbidden();
        $this->assertSame('en', $user->fresh()->preferred_locale);
        Notification::assertNothingSent();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        if (getenv('HOA_BROWSER_EXPORT_LOCALE_FIXTURES') === 'true') {
            URL::forceRootUrl('http://locale-switcher.test');
        }
    }

    private function exportFixture(string $name, string $html): void
    {
        if (getenv('HOA_BROWSER_EXPORT_LOCALE_FIXTURES') !== 'true') {
            return;
        }
        if (! app()->environment('testing') || config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Only synthetic in-memory SQLite test responses may be exported.');
        }
        $directory = storage_path('framework/testing/global-locale-static');
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        file_put_contents($directory.'/'.$name.'.html', $html, LOCK_EX);
    }
}
