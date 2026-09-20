<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Payment;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

final class MonthlyRevenueChart extends ChartWidget
{
    protected ?string $heading = 'Monthly payment collection trend';

    protected int|string|array $columnSpan = 1;

    protected function getData(): array
    {
        $months = collect(range(11, 0))->map(fn (int $offset) => now()->startOfMonth()->subMonths($offset));
        $expression = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', payment_date)"
            : "DATE_FORMAT(payment_date, '%Y-%m')";
        $totals = Payment::query()
            ->whereIn('review_status', ['Approved', 'Recorded'])
            ->whereDate('payment_date', '>=', $months->first()->toDateString())
            ->selectRaw("{$expression} as period, SUM(amount_paid) as total")
            ->groupBy('period')
            ->pluck('total', 'period');

        return [
            'datasets' => [[
                'label' => 'Collections (PHP)',
                'data' => $months->map(fn ($month): float => (float) ($totals[$month->format('Y-m')] ?? 0))->all(),
                'borderColor' => '#ff1053',
                'backgroundColor' => 'rgba(255, 16, 83, .14)',
                'fill' => true,
                'tension' => 0.3,
            ]],
            'labels' => $months->map->format('M Y')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
