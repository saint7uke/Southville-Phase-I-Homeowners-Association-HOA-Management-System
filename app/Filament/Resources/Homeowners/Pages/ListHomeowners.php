<?php

namespace App\Filament\Resources\Homeowners\Pages;

use App\Filament\Resources\Homeowners\HomeownerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHomeowners extends ListRecords
{
    protected static string $resource = HomeownerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
