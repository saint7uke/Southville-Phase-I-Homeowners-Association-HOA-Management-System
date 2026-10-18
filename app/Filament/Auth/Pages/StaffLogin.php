<?php

declare(strict_types=1);

namespace App\Filament\Auth\Pages;

final class StaffLogin extends Login
{
    protected static string $portalTitle = 'Staff Login';

    protected static string $portalSubtitle = 'Sign in to access the staff portal.';

    protected static string $portalLabel = 'Staff portal';

    protected static string $portalAction = 'Staff Login';

    protected static string $portalWelcome = 'Keep daily HOA operations moving with confidence.';

    protected static string $portalSupport = 'Use your staff credentials to continue.';

    /** @return array<int, array{label: string, url: string}> */
    public function getPortalLinks(): array
    {
        return [
            ['label' => 'Homeowner Login', 'url' => route('homeowner.login')],
            ['label' => 'Admin Login', 'url' => route('filament.admin.auth.login')],
        ];
    }
}
