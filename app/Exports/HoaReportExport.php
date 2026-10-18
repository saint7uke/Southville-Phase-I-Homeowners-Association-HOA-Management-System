<?php

declare(strict_types=1);

namespace App\Exports;

use App\Services\Reporting\HoaReportCatalog;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithProperties;

final class HoaReportExport implements FromQuery, WithHeadings, WithMapping, WithProperties
{
    /** @var array<string, mixed> */
    private array $filters;

    /** @param array<string, mixed> $filters */
    public function __construct(
        public readonly string $report,
        ?string $from = null,
        ?string $to = null,
        ?string $status = null,
        array $filters = [],
        private readonly ?string $generatedBy = null,
    ) {
        $this->filters = [
            'from' => $from,
            'to' => $to,
            'status' => $status,
            'q' => null,
            'scope' => 'current',
            'sort' => app(HoaReportCatalog::class)->definitions()[$report]['defaultSort'] ?? 'date',
            'direction' => 'asc',
            ...$filters,
        ];
    }

    public function title(): string
    {
        return app(HoaReportCatalog::class)->definitions()[$this->report]['title'];
    }

    public function query(): Builder
    {
        return app(HoaReportCatalog::class)->query($this->report, $this->filters);
    }

    public function headings(): array
    {
        return app(HoaReportCatalog::class)->headings($this->report);
    }

    public function map($record): array
    {
        return app(HoaReportCatalog::class)->map($this->report, $record);
    }

    /** @return array<string, string> */
    public function properties(): array
    {
        return [
            'creator' => $this->generatedBy ?? 'Southville Phase I HOA',
            'title' => $this->title(),
            'description' => 'Filters: '.json_encode($this->filters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'subject' => 'Generated '.now()->toIso8601String(),
            'company' => 'Southville Phase I Homeowners Association',
        ];
    }
}
