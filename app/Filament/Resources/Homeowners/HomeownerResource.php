<?php

namespace App\Filament\Resources\Homeowners;

use App\Filament\Resources\Homeowners\Pages\CreateHomeowner;
use App\Filament\Resources\Homeowners\Pages\EditHomeowner;
use App\Filament\Resources\Homeowners\Pages\ListHomeowners;
use App\Filament\Resources\Homeowners\Schemas\HomeownerForm;
use App\Filament\Resources\Homeowners\Tables\HomeownersTable;
use App\Models\Homeowner;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class HomeownerResource extends Resource
{
    protected static ?string $model = Homeowner::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return HomeownerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HomeownersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHomeowners::route('/'),
            'create' => CreateHomeowner::route('/create'),
            'edit' => EditHomeowner::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
