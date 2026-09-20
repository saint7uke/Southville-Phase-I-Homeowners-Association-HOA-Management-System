<?php

declare(strict_types=1);

namespace App\Filament\Homeowner\Resources\ServiceRequests\Pages;

use App\Filament\Homeowner\Resources\ServiceRequests\ServiceRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListServiceRequests extends ListRecords
{
    protected static string $resource = ServiceRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
