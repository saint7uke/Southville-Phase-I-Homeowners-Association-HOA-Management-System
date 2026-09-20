<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

final class EnsurePanelIsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Schema::hasTable('system_settings')) {
            return $next($request);
        }

        $panel = Filament::getCurrentPanel()?->getId();
        $settings = SystemSetting::current();

        abort_if($panel === 'staff' && ! $settings->staff_panel_enabled, 503, 'The staff panel is temporarily unavailable.');
        abort_if($panel === 'homeowner' && ! $settings->homeowner_panel_enabled, 503, 'The homeowner panel is temporarily unavailable.');

        return $next($request);
    }
}
