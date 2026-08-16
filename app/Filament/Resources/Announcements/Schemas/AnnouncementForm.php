<?php

declare(strict_types=1);

namespace App\Filament\Resources\Announcements\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

final class AnnouncementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->maxLength(180)->required()->columnSpanFull(),
            Textarea::make('content')->rows(10)->maxLength(10000)->required()->columnSpanFull(),
            Select::make('category')->options(['General' => 'General', 'Maintenance' => 'Maintenance', 'Event' => 'Event', 'Emergency' => 'Emergency', 'Financial' => 'Financial'])->required(),
            Select::make('status')->options(['Draft' => 'Draft', 'Published' => 'Published', 'Archived' => 'Archived'])->default('Draft')->required(),
        ])->columns(2);
    }
}
