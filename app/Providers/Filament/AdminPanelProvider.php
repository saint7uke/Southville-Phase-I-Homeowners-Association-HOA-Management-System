<?php

namespace App\Providers\Filament;

use App\Filament\Auth\Pages\Login;
use App\Filament\Auth\Pages\RequestPasswordReset;
use App\Filament\Widgets\ComplaintStatusChart;
use App\Filament\Widgets\DashboardAccount;
use App\Filament\Widgets\HoaOverview;
use App\Filament\Widgets\MonthlyRevenueChart;
use App\Filament\Widgets\OverdueAccounts;
use App\Filament\Widgets\PortalWelcome;
use App\Filament\Widgets\RecentPayments;
use App\Http\Middleware\AuthenticatePanel;
use App\Http\Middleware\MigrateLegacyPanelSession;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->authGuard('admin')
            ->authPasswordBroker('users')
            ->login(Login::class)
            ->passwordReset(requestAction: RequestPasswordReset::class)
            ->darkMode()
            ->themeSwitcher()
            ->viteTheme(['resources/css/filament.css', 'resources/css/filament-auth.css'])
            ->brandName('Southville Phase I HOA')
            ->brandLogo(asset('images/HOA.png'))
            ->brandLogoHeight('2.75rem')
            ->favicon(asset('images/HOA.png'))
            ->colors([
                'primary' => Color::hex('#0879ad'),
                'info' => Color::hex('#267ca4'),
            ])
            ->navigationGroups([
                'Community',
                'Operations',
                'Finance',
                'Communication',
                'Administration',
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                PortalWelcome::class,
                HoaOverview::class,
                MonthlyRevenueChart::class,
                OverdueAccounts::class,
                RecentPayments::class,
                DashboardAccount::class,
                ComplaintStatusChart::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                MigrateLegacyPanelSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                AuthenticatePanel::class,
            ]);
    }
}
