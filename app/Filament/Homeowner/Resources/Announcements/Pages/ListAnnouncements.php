<?php

declare(strict_types=1);

namespace App\Filament\Homeowner\Resources\Announcements\Pages;

use App\Filament\Homeowner\Resources\Announcements\AnnouncementResource;
use Filament\Resources\Pages\ListRecords;

final class ListAnnouncements extends ListRecords
{
    protected static string $resource = AnnouncementResource::class;
}
