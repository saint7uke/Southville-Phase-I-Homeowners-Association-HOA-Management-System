<?php

namespace App\Filament\Resources\DuesSettings\Pages;

use App\Filament\Resources\DuesSettings\DuesSettingResource;
use App\Jobs\GenerateDuesForSetting;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateDuesSetting extends CreateRecord
{
    protected static string $resource = DuesSettingResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Filament::auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        GenerateDuesForSetting::dispatch((int) $this->record->getKey(), now()->startOfMonth()->toDateString());
    }
}
