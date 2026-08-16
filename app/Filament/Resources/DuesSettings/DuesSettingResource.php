<?php

namespace App\Filament\Resources\DuesSettings;

use App\Filament\Resources\DuesSettings\Pages\CreateDuesSetting;
use App\Filament\Resources\DuesSettings\Pages\EditDuesSetting;
use App\Filament\Resources\DuesSettings\Pages\ListDuesSettings;
use App\Filament\Resources\DuesSettings\Schemas\DuesSettingForm;
use App\Filament\Resources\DuesSettings\Tables\DuesSettingsTable;
use App\Models\DuesSetting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DuesSettingResource extends Resource
{
    protected static ?string $model = DuesSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return DuesSettingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DuesSettingsTable::configure($table);
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
            'index' => ListDuesSettings::route('/'),
            'create' => CreateDuesSetting::route('/create'),
            'edit' => EditDuesSetting::route('/{record}/edit'),
        ];
    }
}
