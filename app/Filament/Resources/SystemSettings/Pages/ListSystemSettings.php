<?php

declare(strict_types=1);

namespace App\Filament\Resources\SystemSettings\Pages;

use App\Filament\Resources\SystemSettings\SystemSettingResource;
use Filament\Resources\Pages\ListRecords;

final class ListSystemSettings extends ListRecords
{
    protected static string $resource = SystemSettingResource::class;
}
