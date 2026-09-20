<?php

declare(strict_types=1);

namespace App\Filament\Homeowner\Resources\Payments\Pages;

use App\Filament\Homeowner\Resources\Payments\PaymentResource;
use Filament\Resources\Pages\ListRecords;

final class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;
}
