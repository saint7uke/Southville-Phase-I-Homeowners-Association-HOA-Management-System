<?php

declare(strict_types=1);

namespace App\Filament\Resources\Payments\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

final class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('homeowner.user.full_name')->label('Homeowner')->searchable(['first_name', 'last_name']),
            TextColumn::make('or_number')->label('OR number')->searchable()->copyable(),
            TextColumn::make('duesSetting.name')->label('Dues type'),
            TextColumn::make('amount_paid')->money('PHP')->sortable(),
            TextColumn::make('balance')->money('PHP')->sortable(),
            TextColumn::make('payment_date')->date()->sortable(),
            TextColumn::make('payment_method')->badge(),
            TextColumn::make('status')->badge(),
        ])->defaultSort('payment_date', 'desc')->filters([
            SelectFilter::make('status')->options(['Paid' => 'Paid', 'Partial' => 'Partial', 'Overdue' => 'Overdue']),
            TrashedFilter::make(),
        ])->recordActions([EditAction::make()])->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make(), RestoreBulkAction::make()])]);
    }
}
