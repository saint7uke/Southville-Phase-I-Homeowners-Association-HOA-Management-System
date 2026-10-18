<?php

declare(strict_types=1);

namespace App\Filament\Resources\Homeowners\RelationManagers;

use App\Filament\Resources\Homeowners\HomeownerResource;
use App\Models\Homeowner;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

final class TenantsRelationManager extends RelationManager
{
    protected static string $relationship = 'tenants';

    protected static ?string $title = 'Tenants';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Homeowner
            && in_array($ownerRecord->ownership_type, ['Owner', 'Co-owner'], true)
            && parent::canViewForRecord($ownerRecord, $pageClass);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.full_name')->label('Tenant')->searchable(['first_name', 'last_name']),
                TextColumn::make('user.contact_number')->label('Contact'),
                TextColumn::make('lease_start_date')->label('Lease starts')->date(),
                TextColumn::make('lease_end_date')->label('Lease ends')->date(),
                TextColumn::make('user.account_status')->label('Account')->badge(),
                TextColumn::make('landlord_relationship_status')->label('Relationship')->badge(),
            ])
            ->recordActions([
                Action::make('viewTenant')
                    ->label('View')
                    ->url(fn (Homeowner $record): string => HomeownerResource::getUrl('view', ['record' => $record])),
                Action::make('manageTenant')
                    ->label('Manage')
                    ->url(fn (Homeowner $record): string => HomeownerResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
