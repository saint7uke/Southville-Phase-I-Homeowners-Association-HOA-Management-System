<?php

declare(strict_types=1);

namespace App\Filament\Homeowner\Widgets;

use App\Models\Announcement;
use App\Models\DuesObligation;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class HomeownerOverview extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $user = Filament::auth()->user();
        $homeowner = $user instanceof User ? $user->homeowner()->first() : null;

        if ($homeowner === null) {
            return [Stat::make('Profile', 'Not linked')->description('Contact HOA staff')->color('danger')];
        }

        $outstanding = DuesObligation::query()
            ->where('homeowner_id', $homeowner->id)
            ->whereIn('status', ['Pending', 'Partial', 'Overdue'])
            ->selectRaw('COALESCE(SUM(amount_due + penalty_amount - amount_paid), 0) as aggregate')
            ->value('aggregate');
        $unread = Announcement::query()->visibleToResidents()->whereDoesntHave('readers', fn ($query) => $query->where('users.id', $user->id))->count();

        return [
            Stat::make('Outstanding balance', 'PHP '.number_format((float) $outstanding, 2))
                ->description((float) $outstanding > 0 ? 'Payment action required' : 'Your account is current')
                ->icon(Heroicon::OutlinedReceiptPercent)
                ->color((float) $outstanding > 0 ? 'danger' : 'success')
                ->url('/homeowner/dues/dues-obligations'),
            Stat::make('Open complaints', $homeowner->complaints()->whereIn('status', ['Pending', 'Under Review'])->count())
                ->description('Pending or under review')
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->color('warning')
                ->url('/homeowner/complaints'),
            Stat::make('Pending requests', $homeowner->serviceRequests()->whereIn('status', ['Pending', 'Processing', 'Approved'])->count())
                ->description('Awaiting HOA completion')
                ->icon(Heroicon::OutlinedClipboardDocumentList)
                ->color('info')
                ->url('/homeowner/service-requests'),
            Stat::make('Unread announcements', $unread)
                ->description($unread > 0 ? 'New community updates' : 'You are up to date')
                ->icon(Heroicon::OutlinedMegaphone)
                ->color('primary')
                ->url('/homeowner/announcements'),
        ];
    }
}
