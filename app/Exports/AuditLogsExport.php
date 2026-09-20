<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

final class AuditLogsExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(private readonly ?string $from = null, private readonly ?string $to = null) {}

    public function query(): Builder
    {
        return AuditLog::query()->with('user')
            ->when($this->from, fn (Builder $query) => $query->whereDate('created_at', '>=', $this->from))
            ->when($this->to, fn (Builder $query) => $query->whereDate('created_at', '<=', $this->to))
            ->latest('id');
    }

    public function headings(): array
    {
        return ['Timestamp', 'Actor', 'Panel', 'Action', 'Record type', 'Record ID', 'IP address'];
    }

    public function map($log): array
    {
        return [$log->created_at?->toDateTimeString(), $log->user?->full_name ?? 'System', $log->panel, $log->action, class_basename((string) $log->auditable_type), $log->auditable_id, $log->ip_address];
    }
}
