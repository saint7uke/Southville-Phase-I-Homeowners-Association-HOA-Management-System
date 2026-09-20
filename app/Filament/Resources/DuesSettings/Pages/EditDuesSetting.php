<?php

namespace App\Filament\Resources\DuesSettings\Pages;

use App\Filament\Resources\DuesSettings\DuesSettingResource;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;

class EditDuesSetting extends EditRecord
{
    protected static string $resource = DuesSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->visible(fn (): bool => Filament::getCurrentPanel()?->getId() === 'admin'),
        ];
    }
}
