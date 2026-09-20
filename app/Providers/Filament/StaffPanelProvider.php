<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Auth\Pages\RequestPasswordReset;
use App\Filament\Pages\Reports;
use App\Filament\Resources\Announcements\AnnouncementResource;
use App\Filament\Resources\Certificates\CertificateResource;
use App\Filament\Resources\Complaints\ComplaintResource;
use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Filament\Resources\DuesSettings\DuesSettingResource;
use App\Filament\Resources\Homeowners\HomeownerResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\ServiceRequests\ServiceRequestResource;
use App\Filament\Staff\Widgets\StaffOverview;
use App\Filament\Staff\Widgets\StaffRecentActivity;
use App\Filament\Staff\Widgets\StaffRecentPayments;
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

final class StaffPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('staff')
            ->path('staff')
            ->authGuard('staff')
            ->authPasswordBroker('users')
            ->login()
            ->passwordReset(requestAction: RequestPasswordReset::class)
            ->brandName('Southville Phase I HOA Staff')
            ->colors([
                'primary' => Color::hex('#176b57'),
                'info' => Color::hex('#267ca4'),
            ])
            ->resources([
                HomeownerResource::class,
                PaymentResource::class,
                ComplaintResource::class,
                ServiceRequestResource::class,
                AnnouncementResource::class,
                CertificateResource::class,
                ContactMessageResource::class,
                DuesSettingResource::class,
            ])
            ->pages([
                Dashboard::class,
                Reports::class,
            ])
            ->widgets([
                StaffOverview::class,
                StaffRecentActivity::class,
                StaffRecentPayments::class,
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
