<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

final class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('full_name')->label('Name')->searchable(['first_name', 'last_name']),
            TextColumn::make('email')->searchable()->copyable(),
            TextColumn::make('roles.name')->label('Role')->badge(),
            TextColumn::make('account_status')->label('Account status')->badge(),
            TextColumn::make('contact_number')->label('Contact'),
            TextColumn::make('created_at')->label('Registered')->dateTime()->sortable(),
        ])->defaultSort('created_at', 'desc')->filters([
            SelectFilter::make('account_status')->options(['Pending' => 'Pending', 'Active' => 'Active', 'Rejected' => 'Rejected', 'Inactive' => 'Inactive']),
            TrashedFilter::make(),
        ])->recordActions([EditAction::make()])->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make(), RestoreBulkAction::make()])]);
    }
}
