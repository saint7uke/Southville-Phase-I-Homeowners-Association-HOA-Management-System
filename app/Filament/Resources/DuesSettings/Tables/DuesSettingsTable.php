<?php

namespace App\Filament\Resources\DuesSettings\Tables;

use App\Models\DuesSetting;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
                TextColumn::make('due_day')->label('Due day')->sortable(),
                TextColumn::make('starts_on')->date('M d, Y')->sortable(),
                TextColumn::make('ends_on')->date('M d, Y')->placeholder('Ongoing')->sortable(),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('creator.full_name')->label('Created by')->placeholder('Legacy record'),
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
                SelectFilter::make('frequency')->options(['Monthly' => 'Monthly', 'Annual' => 'Annual', 'Special Assessment' => 'Special Assessment']),
                SelectFilter::make('is_active')->options(['1' => 'Active', '0' => 'Inactive']),
                Filter::make('starts_on')->schema([
                    DatePicker::make('from')->label('Starts from'),
                    DatePicker::make('to')->label('Starts to')->afterOrEqual('from'),
                ])->query(fn (Builder $query, array $data): Builder => $query
                    ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('starts_on', '>=', $date))
                    ->when($data['to'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('starts_on', '<=', $date))),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('deactivate')
                    ->requiresConfirmation()
                    ->color('warning')
                    ->visible(fn (DuesSetting $record): bool => $record->is_active)
                    ->action(fn (DuesSetting $record) => $record->update(['is_active' => false])),
                DeleteAction::make()->visible(fn (): bool => Filament::getCurrentPanel()?->getId() === 'admin'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ])->visible(fn (): bool => Filament::getCurrentPanel()?->getId() === 'admin'),
            ]);
    }
}
