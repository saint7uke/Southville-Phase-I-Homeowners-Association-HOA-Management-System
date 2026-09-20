<?php

declare(strict_types=1);

namespace App\Filament\Homeowner\Resources\Announcements\Pages;

use App\Filament\Homeowner\Resources\Announcements\AnnouncementResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\DB;

final class ViewAnnouncement extends ViewRecord
{
    protected static string $resource = AnnouncementResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        DB::table('announcement_reads')->insertOrIgnore([
            'announcement_id' => $this->record->getKey(),
            'user_id' => Filament::auth()->id(),
            'read_at' => now(),
        ]);
    }
}
