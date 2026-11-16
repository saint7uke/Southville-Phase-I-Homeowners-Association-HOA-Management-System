<?php

namespace App\Filament\Resources\AuditLogs\Pages;

use App\Filament\Concerns\HasIconOnlyRecordActions;
use App\Filament\Concerns\HasSpecificFeedback;
use App\Filament\Resources\AuditLogs\AuditLogResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAuditLog extends EditRecord
{
    use HasIconOnlyRecordActions;
    use HasSpecificFeedback;

    protected static string $resource = AuditLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
