<?php

declare(strict_types=1);

namespace App\Filament\Homeowner\Resources\Certificates\Pages;

use App\Filament\Homeowner\Resources\Certificates\CertificateResource;
use App\Filament\Homeowner\Resources\ServiceRequests\ServiceRequestResource;
use Filament\Resources\Pages\ListRecords;

final class ListCertificates extends ListRecords
{
    protected static string $resource = CertificateResource::class;

    public function mount(): void
    {
        parent::mount();
        $this->redirect(ServiceRequestResource::getUrl('index', panel: 'homeowner'));
    }
}
