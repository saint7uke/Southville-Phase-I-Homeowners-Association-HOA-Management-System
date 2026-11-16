<?php

namespace App\Filament\Resources\Announcements\Pages;

use App\Filament\Concerns\HasIconOnlyRecordActions;
use App\Filament\Concerns\HasSpecificFeedback;
use App\Filament\Resources\Announcements\AnnouncementResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;

class EditAnnouncement extends EditRecord
{
    use HasIconOnlyRecordActions;
    use HasSpecificFeedback;

    protected static string $resource = AnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->visible(fn (): bool => Filament::getCurrentPanel()?->getId() === 'admin'),
            RestoreAction::make()->visible(fn (): bool => Filament::getCurrentPanel()?->getId() === 'admin'),
        ];
    }
}
