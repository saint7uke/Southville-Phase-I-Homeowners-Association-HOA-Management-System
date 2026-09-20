<?php

namespace App\Filament\Resources\AuditLogs\Pages;

use App\Filament\Resources\AuditLogs\AuditLogResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListAuditLogs extends ListRecords
{
    protected static string $resource = AuditLogResource::class;

    protected function getHeaderActions(): array
    {
        return [Action::make('export')->label('Export CSV')->icon('heroicon-o-arrow-down-tray')->url(route('admin.reports.audit-logs'))];
    }
}
