<?php

namespace App\Filament\Resources\AuditLogs\Pages;

use App\Filament\Concerns\HasSpecificFeedback;
use App\Filament\Resources\AuditLogs\AuditLogResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAuditLog extends CreateRecord
{
    use HasSpecificFeedback;

    protected static string $resource = AuditLogResource::class;
}
