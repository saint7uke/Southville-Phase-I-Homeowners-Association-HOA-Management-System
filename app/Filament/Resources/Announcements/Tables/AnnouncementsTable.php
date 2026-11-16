<?php

namespace App\Filament\Resources\Announcements\Tables;

use App\Actions\Announcements\SendAnnouncementBlast;
use App\Filament\Resources\Announcements\Actions\SendImportantSmsAction;
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
                TextColumn::make('status')->formatStateUsing(fn ($state) => $state === null ? null : __((string) $state))
                    ->searchable(),
                TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('creator.full_name')->label(__('Created by'))->sortable(),
                TextColumn::make('email_delivery_status')
                    ->label(__('Email delivery'))
                    ->state(function (Announcement $record): string {
                        $blast = $record->emailBlast;
                        if ($blast === null) {
                            return __('Not queued');
                        }

                        $sent = $blast->deliveries->where('status', 'Sent')->count();
                        $failed = $blast->deliveries->whereIn('status', ['Failed', 'Uncertain'])->count();
                        $queued = $blast->deliveries->whereIn('status', ['Queued', 'Sending'])->count();

                        return __('Queued: :queued | Sent: :sent | Failed: :failed', compact('queued', 'sent', 'failed'));
                    })
                    ->wrap(),
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
                SelectFilter::make('status')->options(['Draft' => __('Draft'), 'Published' => __('Published'), 'Archived' => __('Archived')]),
                Filter::make('published_at')->schema([
                    DatePicker::make('from')->label(__('Published from')),
                    DatePicker::make('to')->label(__('Published to'))->afterOrEqual('from'),
                ])->query(fn (Builder $query, array $data): Builder => $query
                    ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('published_at', '>=', $date))
                    ->when($data['to'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('published_at', '<=', $date))),
                TrashedFilter::make()->visible(fn (): bool => Filament::auth()->user()?->hasRole('hoa_admin') ?? false),
            ])
            ->recordActions([
                SendImportantSmsAction::make(),
                Action::make('emailBlast')
                    ->label(__('Email homeowners'))
                    ->icon('heroicon-o-paper-airplane')
                    ->requiresConfirmation()
                    ->visible(fn (Announcement $record): bool => (Filament::auth()->user()?->hasRole('hoa_admin') ?? false)
                        && $record->status === 'Published'
                        && $record->published_at?->isPast()
                        && $record->emailBlast === null)
                    ->action(function (Announcement $record): void {
                        $actor = app(AuthenticatedActor::class)->user();
                        abort_unless($actor instanceof User && $actor->can('publish_announcements'), 403);

                        $count = app(SendAnnouncementBlast::class)->handle($record, $actor);
                        $deliveries = $record->emailBlast()->with('deliveries')->first()?->deliveries ?? collect();
                        $sent = $deliveries->where('status', 'Sent')->count();
                        $failed = $deliveries->whereIn('status', ['Failed', 'Uncertain'])->count();
                        $queued = $deliveries->whereIn('status', ['Queued', 'Sending'])->count();
                        Notification::make()
                            ->status($count === 0 ? 'info' : ($failed === 0 && $queued === 0 ? 'success' : 'warning'))
                            ->title(__($count === 0 ? 'No Announcement Emails Required Sending' : ($queued > 0 ? 'Announcement Email Delivery Pending' : ($failed > 0 ? 'Announcement Email Delivery Failed or Partially Completed' : 'Announcement Emails Sent Successfully!'))))
                            ->body(__('Recipients: :count | Queued: :queued | Sent: :sent | Failed: :failed', compact('count', 'queued', 'sent', 'failed')).' '.__('Sent means accepted by the mail server; inbox receipt is not confirmed. Failed counts include uncertain outcomes requiring operator review.'))
                            ->send();
                    }),
                Action::make('retryFailedEmails')
                    ->label(__('Retry failed emails'))
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->visible(fn (Announcement $record): bool => (Filament::auth()->user()?->hasRole('hoa_admin') ?? false)
                        && ($record->emailBlast?->deliveries->contains('status', 'Failed') ?? false))
                    ->action(function (Announcement $record): void {
                        $actor = app(AuthenticatedActor::class)->user();
                        abort_unless($actor instanceof User && $actor->can('publish_announcements'), 403);

                        $count = app(SendAnnouncementBlast::class)->retryFailed($record, $actor);
                        $deliveries = $record->emailBlast()->with('deliveries')->first()?->deliveries ?? collect();
                        $sent = $deliveries->where('status', 'Sent')->count();
                        $failed = $deliveries->whereIn('status', ['Failed', 'Uncertain'])->count();
                        $queued = $deliveries->whereIn('status', ['Queued', 'Sending'])->count();
                        Notification::make()
                            ->status($count === 0 ? 'info' : ($failed === 0 && $queued === 0 ? 'success' : 'warning'))
                            ->title(__($count === 0 ? 'No Announcement Emails Required Retrying' : ($queued > 0 ? 'Announcement Email Retry Pending' : ($failed > 0 ? 'Announcement Email Retry Failed or Partially Completed' : 'Announcement Emails Retried Successfully!'))))
                            ->body(__('Retried: :count | Queued: :queued | Sent: :sent | Failed: :failed', compact('count', 'queued', 'sent', 'failed')).' '.__('Sent means accepted by the mail server; inbox receipt is not confirmed. Failed counts include uncertain outcomes requiring operator review.'))
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ])->visible(fn (): bool => Filament::getCurrentPanel()?->getId() === 'admin'),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['emailBlast.deliveries']));
    }
}
