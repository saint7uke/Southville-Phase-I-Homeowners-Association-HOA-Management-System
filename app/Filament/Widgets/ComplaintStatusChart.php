<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Complaint;
use Filament\Widgets\ChartWidget;

final class ComplaintStatusChart extends ChartWidget
{
    protected ?string $heading = 'Complaint status breakdown';

    protected int|string|array $columnSpan = 1;

    protected function getData(): array
    {
        $counts = Complaint::query()->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $statuses = ['Pending', 'Under Review', 'Resolved', 'Rejected', 'Closed', 'Dismissed'];

        return [
            'datasets' => [[
                'data' => collect($statuses)->map(fn (string $status): int => (int) ($counts[$status] ?? 0))->all(),
                'backgroundColor' => ['#66c7f4', '#6c6ea0', '#22c55e', '#ff1053', '#424c55', '#c1cad6'],
            ]],
            'labels' => $statuses,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
