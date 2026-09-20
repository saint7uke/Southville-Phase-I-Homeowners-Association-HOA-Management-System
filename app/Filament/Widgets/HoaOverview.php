<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Complaint;
use App\Models\DuesObligation;
use App\Models\Homeowner;
use App\Models\Payment;
use App\Models\ServiceRequest;
use App\Models\User;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class HoaOverview extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $totalHomeowners = Homeowner::query()->count();
        $activeHomeowners = Homeowner::query()->where('status', 'Active')->count();
        $outstanding = DuesObligation::query()
            ->whereIn('status', ['Pending', 'Partial', 'Overdue'])
            ->selectRaw('COALESCE(SUM(amount_due + penalty_amount - amount_paid), 0) as aggregate')
            ->value('aggregate');

        return [
            Stat::make('Total homeowners', $totalHomeowners)->description($activeHomeowners.' active')->icon(Heroicon::OutlinedHomeModern)->color('success'),
            Stat::make('Collections this month', 'PHP '.number_format((float) Payment::query()->whereBetween('payment_date', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount_paid'), 2))->description(now()->format('F Y'))->icon(Heroicon::OutlinedBanknotes)->color('info'),
            Stat::make('Outstanding dues', 'PHP '.number_format((float) $outstanding, 2))->description('Pending, partial and overdue')->icon(Heroicon::OutlinedReceiptPercent)->color('warning'),
            Stat::make('Delinquent accounts', Homeowner::query()->where('status', 'Delinquent')->count())->description('Requires follow-up')->icon(Heroicon::OutlinedExclamationCircle)->color('danger'),
            Stat::make('Open concerns', Complaint::query()->whereIn('status', ['Pending', 'Under Review'])->count())->description('Pending or under review')->icon(Heroicon::OutlinedExclamationTriangle)->color('warning'),
            Stat::make('Pending requests', ServiceRequest::query()->whereIn('status', ['Pending', 'Processing'])->count())->description(User::query()->where('account_status', 'Pending')->count().' registrations await review')->icon(Heroicon::OutlinedClipboardDocumentList)->color('primary'),
        ];
    }
}
