<?php

declare(strict_types=1);

namespace App\Filament\Resources\Homeowners\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

final class HomeownersTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('user.full_name')->label('Resident')->searchable(['first_name', 'last_name'])->sortable(['last_name']),
            TextColumn::make('full_address')->label('Property address')->wrap(),
            TextColumn::make('ownership_type')->badge(),
            TextColumn::make('status')->badge(),
            TextColumn::make('residency_date')->date()->sortable(),
            TextColumn::make('user.contact_number')->label('Contact'),
        ])->defaultSort('created_at', 'desc')->filters([
            SelectFilter::make('status')->options(['Active' => 'Active', 'Inactive' => 'Inactive', 'Delinquent' => 'Delinquent']),
            SelectFilter::make('ownership_type')->options(['Owner' => 'Owner', 'Tenant' => 'Tenant', 'Co-owner' => 'Co-owner']),
            TrashedFilter::make(),
        ])->recordActions([EditAction::make()])->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make(), RestoreBulkAction::make()])]);
    }
}
