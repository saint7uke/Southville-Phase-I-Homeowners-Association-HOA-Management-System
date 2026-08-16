<?php

namespace App\Filament\Resources\Homeowners\Pages;

use App\Filament\Resources\Homeowners\HomeownerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateHomeowner extends CreateRecord
{
    protected static string $resource = HomeownerResource::class;
}
