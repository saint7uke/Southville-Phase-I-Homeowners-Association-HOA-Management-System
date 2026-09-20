<?php

namespace App\Filament\Resources\Announcements\Tables;

use App\Actions\Announcements\SendAnnouncementBlast;
use App\Models\Announcement;
use App\Models\User;
use App\Support\AuthenticatedActor;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AnnouncementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable(),
                ImageColumn::make('banner_image'),
                TextColumn::make('category')
                    ->searchable(),
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('creator.full_name')->label('Created by')->sortable(),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')->options(array_combine(Announcement::CATEGORIES, Announcement::CATEGORIES)),
                SelectFilter::make('status')->options(['Draft' => 'Draft', 'Published' => 'Published', 'Archived' => 'Archived']),
                Filter::make('published_at')->schema([
                    DatePicker::make('from')->label('Published from'),
                    DatePicker::make('to')->label('Published to')->afterOrEqual('from'),
                ])->query(fn (Builder $query, array $data): Builder => $query
                    ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('published_at', '>=', $date))
                    ->when($data['to'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('published_at', '<=', $date))),
                TrashedFilter::make()->visible(fn (): bool => Filament::auth()->user()?->hasRole('hoa_admin') ?? false),
            ])
            ->recordActions([
                Action::make('emailBlast')
                    ->label('Email homeowners')
                    ->icon('heroicon-o-paper-airplane')
                    ->requiresConfirmation()
                    ->visible(fn (Announcement $record): bool => (Filament::auth()->user()?->hasRole('hoa_admin') ?? false) && $record->status === 'Published' && $record->published_at?->isPast())
                    ->action(function (Announcement $record): void {
                        $actor = app(AuthenticatedActor::class)->user();
                        abort_unless($actor instanceof User && $actor->can('publish_announcements'), 403);

                        $count = app(SendAnnouncementBlast::class)->handle($record, $actor);
                        Notification::make()->success()->title("Queued for {$count} active homeowner(s)")->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ])->visible(fn (): bool => Filament::getCurrentPanel()?->getId() === 'admin'),
            ]);
    }
}
