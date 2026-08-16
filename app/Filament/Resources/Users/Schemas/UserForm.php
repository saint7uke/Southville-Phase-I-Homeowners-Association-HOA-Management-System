<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

final class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('first_name')->maxLength(100)->required(),
            TextInput::make('middle_name')->maxLength(100),
            TextInput::make('last_name')->maxLength(100)->required(),
            Select::make('suffix')->options(['N/A' => 'None', 'Jr.' => 'Jr.', 'Sr.' => 'Sr.', 'II' => 'II', 'III' => 'III', 'IV' => 'IV']),
            Select::make('sex')->options(['Male' => 'Male', 'Female' => 'Female', 'Prefer not to say' => 'Prefer not to say'])->required(),
            TextInput::make('contact_number')->tel()->length(11)->required(),
            DatePicker::make('date_of_birth')->maxDate(today()->subDay())->required(),
            TextInput::make('email')->email()->maxLength(255)->unique(ignoreRecord: true)->required(),
            TextInput::make('password')->password()->minLength(12)->required(fn (string $operation): bool => $operation === 'create')->dehydrated(fn (?string $state): bool => filled($state)),
            Select::make('account_status')->options(['Pending' => 'Pending', 'Active' => 'Active', 'Rejected' => 'Rejected', 'Inactive' => 'Inactive'])->required(),
            Textarea::make('rejection_reason')->maxLength(500)->columnSpanFull(),
        ])->columns(2);
    }
}
