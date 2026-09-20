<?php

namespace App\Filament\Resources\Complaints\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ComplaintsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('homeowner.user.full_name')
                    ->label('Homeowner')
                    ->searchable(['first_name', 'last_name']),
                TextColumn::make('ticket_number')
                    ->searchable(),
                TextColumn::make('subject')
                    ->searchable(),
                TextColumn::make('category')
                    ->badge(),
                TextColumn::make('priority')
                    ->badge(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('handler.full_name')
                    ->label('Assigned staff')
                    ->sortable(),
                TextColumn::make('resolved_at')
                    ->dateTime()
                    ->sortable(),
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
                SelectFilter::make('category')->options(['Noise' => 'Noise', 'Property Damage' => 'Property Damage', 'Neighbor Dispute' => 'Neighbor Dispute', 'Common Area' => 'Common Area', 'Security' => 'Security', 'Others' => 'Others']),
                SelectFilter::make('priority')->options(['Low' => 'Low', 'Medium' => 'Medium', 'High' => 'High', 'Urgent' => 'Urgent']),
                SelectFilter::make('status')->options(['Pending' => 'Pending', 'Under Review' => 'Under Review', 'Resolved' => 'Resolved', 'Rejected' => 'Rejected', 'Closed' => 'Closed', 'Dismissed' => 'Dismissed']),
                Filter::make('created_at')->schema([
                    DatePicker::make('from')->label('Submitted from'),
                    DatePicker::make('to')->label('Submitted to')->afterOrEqual('from'),
                ])->query(fn (Builder $query, array $data): Builder => $query
                    ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date))
                    ->when($data['to'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date))),
                TrashedFilter::make()->visible(fn (): bool => Filament::auth()->user()?->hasRole('hoa_admin') ?? false),
            ])
            ->recordActions([
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
