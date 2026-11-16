<?php

namespace App\Filament\Resources\Announcements\Pages;

use App\Filament\Concerns\HasSpecificFeedback;
use App\Filament\Resources\Announcements\AnnouncementResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAnnouncement extends CreateRecord
{
    use HasSpecificFeedback;

    protected static string $resource = AnnouncementResource::class;
}
