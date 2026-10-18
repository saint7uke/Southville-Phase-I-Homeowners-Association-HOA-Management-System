<?php

declare(strict_types=1);

namespace App\Filament\Homeowner\Resources\Announcements\Pages;

use App\Filament\Homeowner\Resources\Announcements\AnnouncementResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class ViewAnnouncement extends ViewRecord
{
    protected static string $resource = AnnouncementResource::class;

    protected string $view = 'filament.homeowner.resources.announcements.pages.view-announcement';

    public function mount(int|string $record): void
    {
        parent::mount($record);

        DB::table('announcement_reads')->insertOrIgnore([
            'announcement_id' => $this->record->getKey(),
            'user_id' => Filament::auth()->id(),
            'read_at' => now(),
        ]);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Announcement';
    }

    public function getSubheading(): ?string
    {
        return 'Community news and updates for Southville Phase I.';
    }

    public function getBannerUrl(): ?string
    {
        $path = $this->record->banner_image;

        return filled($path) ? Storage::disk('public')->url((string) $path) : null;
    }
}
