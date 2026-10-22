<?php

namespace App\Filament\Resources\DuesSettings\Schemas;

use App\Models\DuesSetting;
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
                    ->disabled(fn (?DuesSetting $record): bool => $record?->obligations()->exists() ?? false)->dehydrated()
                    ->required(),
                TextInput::make('amount')
                    ->prefix('PHP')
                    ->minValue(0.01)
                    ->disabled(fn (?DuesSetting $record): bool => $record?->obligations()->exists() ?? false)->dehydrated()
                    ->required()
                    ->numeric(),
                Select::make('frequency')
                    ->label(__('Type'))
                    ->options(['Monthly' => __('Monthly'), 'Annual' => __('Annual'), 'Special Assessment' => __('Special Assessment')])
                    ->default('Monthly')
                    ->disabled(fn (?DuesSetting $record): bool => $record?->obligations()->exists() ?? false)->dehydrated()
                    ->required(),
                TextInput::make('due_day')->label(__('Due day of month'))->integer()->minValue(1)->maxValue(31)->default(10)
                    ->disabled(fn (?DuesSetting $record): bool => $record?->obligations()->exists() ?? false)->dehydrated()->required(),
                DatePicker::make('starts_on')->label(__('Effective from'))->native(false)
                    ->disabled(fn (?DuesSetting $record): bool => $record?->obligations()->exists() ?? false)->dehydrated()->required(),
                DatePicker::make('ends_on')->label(__('Effective until'))->native(false)->afterOrEqual('starts_on')
                    ->disabled(fn (?DuesSetting $record): bool => $record?->obligations()->exists() ?? false)->dehydrated(),
                Textarea::make('description')->maxLength(2000)->columnSpanFull(),
                Toggle::make('is_active')
                    ->default(true)
                    ->disabled(fn (?DuesSetting $record): bool => $record?->obligations()->exists() ?? false)->dehydrated()
                    ->required(),
            ]);
    }
}
