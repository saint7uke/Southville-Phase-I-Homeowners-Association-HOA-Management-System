<?php

namespace App\Filament\Resources\DuesSettings\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DuesSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->maxLength(120)
                    ->required(),
                TextInput::make('amount')
                    ->prefix('PHP')
                    ->minValue(0.01)
                    ->required()
                    ->numeric(),
                Select::make('frequency')
                    ->label('Type')
                    ->options(['Monthly' => 'Monthly', 'Annual' => 'Annual', 'Special Assessment' => 'Special Assessment'])
                    ->default('Monthly')
                    ->required(),
                TextInput::make('due_day')->label('Due day of month')->integer()->minValue(1)->maxValue(31)->default(10)->required(),
                DatePicker::make('starts_on')->label('Effective from')->native(false),
                DatePicker::make('ends_on')->label('Effective until')->native(false)->afterOrEqual('starts_on'),
                Textarea::make('description')->maxLength(2000)->columnSpanFull(),
                Toggle::make('is_active')
                    ->default(true)
                    ->required(),
            ]);
    }
}
