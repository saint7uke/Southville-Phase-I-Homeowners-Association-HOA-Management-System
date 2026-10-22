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
                TextColumn::make('user.full_name')->label(__('Tenant'))->searchable(['first_name', 'last_name']),
                TextColumn::make('user.contact_number')->label(__('Contact')),
                TextColumn::make('lease_start_date')->label(__('Lease starts'))->date(),
                TextColumn::make('lease_end_date')->label(__('Lease ends'))->date(),
                TextColumn::make('user.account_status')->formatStateUsing(fn ($state) => $state === null ? null : __((string) $state))->label(__('Account'))->badge(),
                TextColumn::make('landlord_relationship_status')->label(__('Relationship'))->badge(),
            ])
            ->recordActions([
                Action::make('viewTenant')
                    ->label(__('View'))
                    ->url(fn (Homeowner $record): string => HomeownerResource::getUrl('view', ['record' => $record])),
                Action::make('manageTenant')
                    ->label(__('Manage'))
                    ->url(fn (Homeowner $record): string => HomeownerResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
