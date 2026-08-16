<?php

declare(strict_types=1);

namespace App\Filament\Resources\Homeowners\Schemas;

use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

final class HomeownerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')->relationship('user', 'id')->getOptionLabelFromRecordUsing(fn (User $record): string => $record->full_name.' | '.$record->email)->searchable()->preload()->required(),
            TextInput::make('house_number')->maxLength(50)->required(),
            TextInput::make('street')->maxLength(100)->required(),
            TextInput::make('block')->maxLength(20),
            TextInput::make('lot')->maxLength(20),
            DatePicker::make('residency_date')->maxDate(today())->required(),
            Select::make('ownership_type')->options(['Owner' => 'Owner', 'Tenant' => 'Tenant', 'Co-owner' => 'Co-owner'])->required(),
            Select::make('status')->options(['Active' => 'Active', 'Inactive' => 'Inactive', 'Delinquent' => 'Delinquent'])->required(),
        ])->columns(2);
    }
}
