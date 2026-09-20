<?php

namespace App\Filament\Resources\ServiceRequests\Pages;

use App\Filament\Resources\ServiceRequests\ServiceRequestResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;

class EditServiceRequest extends EditRecord
{
    protected static string $resource = ServiceRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->visible(fn (): bool => Filament::getCurrentPanel()?->getId() === 'admin'),
            RestoreAction::make()->visible(fn (): bool => Filament::getCurrentPanel()?->getId() === 'admin'),
        ];
    }
}
