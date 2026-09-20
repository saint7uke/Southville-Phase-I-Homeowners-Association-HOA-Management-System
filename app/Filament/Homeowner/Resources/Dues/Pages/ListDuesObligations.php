<?php

declare(strict_types=1);

namespace App\Filament\Homeowner\Resources\Dues\Pages;

use App\Filament\Homeowner\Resources\Dues\DuesObligationResource;
use Filament\Resources\Pages\ListRecords;

final class ListDuesObligations extends ListRecords
{
    protected static string $resource = DuesObligationResource::class;
}
