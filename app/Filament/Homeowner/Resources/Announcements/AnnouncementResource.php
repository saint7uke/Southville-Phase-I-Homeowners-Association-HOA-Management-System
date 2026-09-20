<?php

declare(strict_types=1);

namespace App\Filament\Homeowner\Resources\Announcements;

use App\Filament\Homeowner\Resources\Announcements\Pages\ListAnnouncements;
use App\Filament\Homeowner\Resources\Announcements\Pages\ViewAnnouncement;
use App\Models\Announcement;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Placeholder;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class AnnouncementResource extends Resource
{
    protected static ?string $model = Announcement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Placeholder::make('title')->content(fn (Announcement $record): string => $record->title),
            Placeholder::make('category')->content(fn (Announcement $record): string => $record->category),
            Placeholder::make('published_at')->content(fn (Announcement $record): string => $record->published_at?->format('F j, Y g:i A') ?? '-'),
            Placeholder::make('content')->content(fn (Announcement $record): string => $record->content)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Stack::make([
                    TextColumn::make('category')
                        ->badge()
                        ->color(fn (Announcement $record): string => $record->category === 'Urgent' ? 'danger' : 'info'),
                    TextColumn::make('title')
                        ->searchable()
                        ->wrap()
                        ->size('lg')
                        ->weight(fn (Announcement $record): string => $record->is_read ? 'normal' : 'bold')
                        ->color(fn (Announcement $record): ?string => ! $record->is_read ? 'danger' : null),
                    TextColumn::make('content')->limit(180)->wrap(),
                    TextColumn::make('published_at')->label('Published')->dateTime('M d, Y')->sortable(),
                ])->space(3),
            ])
            ->contentGrid([
                'md' => 2,
                'xl' => 3,
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label('Type')
                    ->options(array_combine(Announcement::CATEGORIES, Announcement::CATEGORIES)),
            ])
            ->defaultSort('published_at', 'desc')
            ->recordActions([ViewAction::make()]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleToResidents()->withExists([
            'readers as is_read' => fn (Builder $query): Builder => $query->where('users.id', Filament::auth()->id()),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListAnnouncements::route('/'), 'view' => ViewAnnouncement::route('/{record}')];
    }
}
