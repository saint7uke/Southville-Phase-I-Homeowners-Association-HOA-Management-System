<?php

namespace App\Filament\Resources\Homeowners\Pages;

use App\Filament\Resources\Homeowners\HomeownerResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditHomeowner extends EditRecord
{
    protected static string $resource = HomeownerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
