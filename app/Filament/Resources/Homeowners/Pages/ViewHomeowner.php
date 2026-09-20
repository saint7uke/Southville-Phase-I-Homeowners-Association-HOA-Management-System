<?php

declare(strict_types=1);

namespace App\Filament\Resources\Homeowners\Pages;

use App\Filament\Resources\Homeowners\HomeownerResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewHomeowner extends ViewRecord
{
    protected static string $resource = HomeownerResource::class;
}
