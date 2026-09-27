<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Auth\Pages\RequestPasswordReset;
use App\Filament\Homeowner\Pages\MyProfile;
use App\Filament\Homeowner\Resources\Announcements\AnnouncementResource;
use App\Filament\Homeowner\Resources\Certificates\CertificateResource;
use App\Filament\Homeowner\Resources\Complaints\ComplaintResource;
use App\Filament\Homeowner\Resources\Dues\DuesObligationResource;
use App\Filament\Homeowner\Resources\Payments\PaymentResource;
use App\Filament\Homeowner\Resources\ServiceRequests\ServiceRequestResource;
use App\Filament\Homeowner\Widgets\HomeownerOverview;
use App\Filament\Homeowner\Widgets\HomeownerWelcome;
use App\Filament\Homeowner\Widgets\LatestAnnouncements;
use App\Filament\Homeowner\Widgets\RecentPayments;
use App\Http\Middleware\AuthenticatePanel;
use App\Http\Middleware\EnsurePanelIsEnabled;
use App\Http\Middleware\MigrateLegacyPanelSession;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

final class HomeownerPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('homeowner')
            ->path('homeowner')
            ->authGuard('homeowner')
            ->authPasswordBroker('users')
            ->login()
            ->passwordReset(requestAction: RequestPasswordReset::class)
            ->emailVerification()
            ->brandName('Southville Phase I HOA Homeowner')
            ->favicon(asset('images/HOA.png'))
            ->colors([
                'primary' => Color::hex('#d90b46'),
                'info' => Color::hex('#267ca4'),
            ])
            ->pages([
                Dashboard::class,
                MyProfile::class,
            ])
            ->resources([
                DuesObligationResource::class,
                PaymentResource::class,
                ComplaintResource::class,
                ServiceRequestResource::class,
                CertificateResource::class,
                AnnouncementResource::class,
            ])
            ->widgets([
                HomeownerWelcome::class,
                HomeownerOverview::class,
                RecentPayments::class,
                LatestAnnouncements::class,
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                EnsurePanelIsEnabled::class,
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
