<?php

namespace App\Filament\Resources\DuesSettings\Tables;

use App\Actions\Dues\CloseDuesSetting;
use App\Models\DuesSetting;
use App\Models\User;
use App\Support\AuthenticatedActor;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class DuesSettingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('amount')
                    ->money('PHP')
                    ->sortable(),
                TextColumn::make('frequency')
                    ->searchable(),
                TextColumn::make('due_day')->label(__('Due day'))->sortable(),
                TextColumn::make('starts_on')->date('M d, Y')->sortable(),
                TextColumn::make('ends_on')->date('M d, Y')->placeholder(__('Ongoing'))->sortable(),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('creator.full_name')->label(__('Created by'))->placeholder(__('Legacy record')),
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
                SelectFilter::make('frequency')->options(['Monthly' => __('Monthly'), 'Annual' => __('Annual'), 'Special Assessment' => __('Special Assessment')]),
                SelectFilter::make('is_active')->options(['1' => 'Active', '0' => 'Inactive']),
                Filter::make('starts_on')->schema([
                    DatePicker::make('from')->label(__('Starts from')),
                    DatePicker::make('to')->label(__('Starts to'))->afterOrEqual('from'),
                ])->query(fn (Builder $query, array $data): Builder => $query
                    ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('starts_on', '>=', $date))
                    ->when($data['to'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('starts_on', '<=', $date))),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('deactivate')
                    ->label(__('Close schedule'))
                    ->requiresConfirmation()
                    ->modalDescription(__('The schedule will end after its latest generated billing month and will no longer generate obligations.'))
                    ->color('warning')
                    ->visible(fn (DuesSetting $record): bool => $record->is_active)
                    ->action(function (DuesSetting $record, Action $action): void {
                        $actor = app(AuthenticatedActor::class)->user();
                        abort_unless($actor instanceof User, 403);
                        try {
                            app(CloseDuesSetting::class)->handle($record, $actor);
                        } catch (ValidationException $exception) {
                            Notification::make()->danger()->title(__('Please review the form'))->body(collect($exception->errors())->flatten()->first())->send();
                            $action->halt();
                        }
                    }),
                DeleteAction::make()->visible(fn (): bool => Filament::getCurrentPanel()?->getId() === 'admin'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ])->visible(fn (): bool => Filament::getCurrentPanel()?->getId() === 'admin'),
            ]);
    }
}
