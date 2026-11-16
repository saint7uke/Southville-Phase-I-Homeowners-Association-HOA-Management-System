<?php

declare(strict_types=1);

namespace App\Filament\Resources\Announcements\Schemas;

use App\Models\Announcement;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

final class AnnouncementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->maxLength(180)->required()->columnSpanFull(),
            Textarea::make('content')->rows(10)->maxLength(10000)->required()->columnSpanFull(),
            FileUpload::make('banner_image')
                ->label(__('Banner image'))
                ->disk('public')
                ->directory('announcements')
                ->visibility('public')
                ->image()
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->maxSize(5120)
                ->getUploadedFileNameForStorageUsing(fn (TemporaryUploadedFile $file): string => (string) str()->ulid().'.'.$file->guessExtension())
                ->columnSpanFull(),
            Select::make('category')->options(array_combine(Announcement::CATEGORIES, Announcement::CATEGORIES))->required(),
            Toggle::make('is_important')->label(__('Important announcement'))->default(false)
                ->helperText(__('Marking an announcement Important does not send SMS. Use Send Important SMS after publication.'))
                ->visible(fn (): bool => (Filament::auth()->user()?->hasRole('hoa_admin') ?? false)
                    && (Filament::auth()->user()?->can('manage_settings') ?? false)),
            Select::make('audience')->options(['Public' => 'Public website and residents', 'Residents' => 'Residents only'])->default('Public')->required(),
            Select::make('status')->options(['Draft' => __('Draft'), 'Published' => __('Published'), 'Archived' => __('Archived')])->default('Draft')->required(),
            DateTimePicker::make('published_at')->seconds(false)->helperText(__('Leave blank to publish immediately.'))->nullable(),
            DateTimePicker::make('expires_at')->seconds(false)->after('published_at')->nullable(),
        ])->columns(2);
    }
}
