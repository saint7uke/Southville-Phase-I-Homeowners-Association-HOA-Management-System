<?php

declare(strict_types=1);

namespace App\Filament\Staff\Widgets;

use App\Models\Complaint;
use App\Models\Homeowner;
use App\Models\Payment;
use App\Models\ServiceRequest;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class StaffOverview extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        return [
            Stat::make('Active homeowners', Homeowner::query()->where('status', 'Active')->count())
                ->description('Verified resident records')
                ->icon(Heroicon::OutlinedHomeModern)
                ->color('success'),
            Stat::make('Collections this month', 'PHP '.number_format((float) Payment::query()
                ->whereBetween('payment_date', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('amount_paid'), 2))
                ->description(now()->format('F Y'))
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('info'),
            Stat::make('Open complaints', Complaint::query()->whereIn('status', ['Pending', 'Under Review'])->count())
                ->description('Pending or under review')
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->color('warning'),
            Stat::make('Pending requests', ServiceRequest::query()->whereIn('status', ['Pending', 'Processing'])->count())
                ->description('Awaiting staff action')
                ->icon(Heroicon::OutlinedClipboardDocumentList)
                ->color('primary'),
        ];
    }
}
