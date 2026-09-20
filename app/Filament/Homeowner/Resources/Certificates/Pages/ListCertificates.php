<?php

declare(strict_types=1);

namespace App\Filament\Homeowner\Resources\Certificates\Pages;

use App\Filament\Homeowner\Resources\Certificates\CertificateResource;
use Filament\Resources\Pages\ListRecords;

final class ListCertificates extends ListRecords
{
    protected static string $resource = CertificateResource::class;
}
