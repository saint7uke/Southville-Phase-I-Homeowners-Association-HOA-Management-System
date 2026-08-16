<?php

namespace App\Filament\Resources\DuesSettings\Pages;

use App\Filament\Resources\DuesSettings\DuesSettingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDuesSettings extends ListRecords
{
    protected static string $resource = DuesSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
