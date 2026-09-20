<?php

declare(strict_types=1);

namespace App\Filament\Homeowner\Resources\Complaints\Pages;

use App\Filament\Homeowner\Resources\Complaints\ComplaintResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListComplaints extends ListRecords
{
    protected static string $resource = ComplaintResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
