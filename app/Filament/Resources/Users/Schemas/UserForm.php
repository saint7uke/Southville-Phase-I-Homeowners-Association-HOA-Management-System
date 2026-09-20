<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserAccountStatus;
use App\Support\PersonName;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;

final class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('first_name')->maxLength(100)->regex("/^[\pL\s'.-]+$/u")->required()->dehydrateStateUsing(fn (?string $state): ?string => PersonName::normalize($state)),
            TextInput::make('middle_name')->maxLength(100)->regex("/^[\pL\s'.-]+$/u")->dehydrateStateUsing(fn (?string $state): ?string => PersonName::normalize($state)),
            TextInput::make('last_name')->maxLength(100)->regex("/^[\pL\s'.-]+$/u")->required()->dehydrateStateUsing(fn (?string $state): ?string => PersonName::normalize($state)),
            Select::make('suffix')->options(['N/A' => 'None', 'Jr.' => 'Jr.', 'Sr.' => 'Sr.', 'II' => 'II', 'III' => 'III', 'IV' => 'IV']),
            Select::make('sex')->options(['Male' => 'Male', 'Female' => 'Female', 'Prefer not to say' => 'Prefer not to say'])->required(),
            TextInput::make('contact_number')->tel()->length(11)->regex('/^09\d{9}$/')->placeholder('09XXXXXXXXX')->required(),
            DatePicker::make('date_of_birth')->maxDate(today()->subDay())->required(),
            TextInput::make('email')->email()->maxLength(255)->unique(ignoreRecord: true)->required()->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? mb_strtolower(trim($state), 'UTF-8') : null),
            TextInput::make('password')->password()->revealable()->rule(Password::default())->required(fn (string $operation): bool => $operation === 'create')->dehydrated(fn (?string $state): bool => filled($state)),
            Select::make('role')->options(fn (string $operation): array => $operation === 'create'
                ? ['hoa_admin' => 'HOA Administrator', 'hoa_staff' => 'HOA Staff', 'homeowner' => 'Homeowner']
                : ['hoa_admin' => 'HOA Administrator', 'hoa_staff' => 'HOA Staff', 'homeowner' => 'Homeowner'])->required()->live(),
            TextInput::make('house_number')->maxLength(50)->required(fn (Get $get, string $operation): bool => $operation === 'create' && $get('role') === 'homeowner')->visible(fn (Get $get, string $operation): bool => $operation === 'create' && $get('role') === 'homeowner')->dehydrateStateUsing(fn (?string $state): ?string => self::collapseWhitespace($state)),
            TextInput::make('street')->maxLength(100)->required(fn (Get $get, string $operation): bool => $operation === 'create' && $get('role') === 'homeowner')->visible(fn (Get $get, string $operation): bool => $operation === 'create' && $get('role') === 'homeowner')->dehydrateStateUsing(fn (?string $state): ?string => self::collapseWhitespace($state)),
            TextInput::make('block')->maxLength(20)->required(fn (Get $get, string $operation): bool => $operation === 'create' && $get('role') === 'homeowner')->visible(fn (Get $get, string $operation): bool => $operation === 'create' && $get('role') === 'homeowner')->dehydrateStateUsing(fn (?string $state): ?string => self::uppercase($state)),
            TextInput::make('lot')->maxLength(20)->required(fn (Get $get, string $operation): bool => $operation === 'create' && $get('role') === 'homeowner')->visible(fn (Get $get, string $operation): bool => $operation === 'create' && $get('role') === 'homeowner')->dehydrateStateUsing(fn (?string $state): ?string => self::uppercase($state)),
            DatePicker::make('residency_date')->label('Move-in date')->maxDate(today())->required(fn (Get $get, string $operation): bool => $operation === 'create' && $get('role') === 'homeowner')->visible(fn (Get $get, string $operation): bool => $operation === 'create' && $get('role') === 'homeowner'),
            Select::make('ownership_type')->options(['Owner' => 'Owner', 'Tenant' => 'Tenant', 'Co-owner' => 'Co-owner'])->required(fn (Get $get, string $operation): bool => $operation === 'create' && $get('role') === 'homeowner')->visible(fn (Get $get, string $operation): bool => $operation === 'create' && $get('role') === 'homeowner'),
            TextInput::make('emergency_contact_name')->maxLength(100)->regex("/^[\pL\s'.-]+$/u")->required(fn (Get $get, string $operation): bool => $operation === 'create' && $get('role') === 'homeowner')->visible(fn (Get $get, string $operation): bool => $operation === 'create' && $get('role') === 'homeowner')->dehydrateStateUsing(fn (?string $state): ?string => PersonName::normalize($state)),
            TextInput::make('emergency_contact_number')->tel()->length(11)->regex('/^09\d{9}$/')->placeholder('09XXXXXXXXX')->required(fn (Get $get, string $operation): bool => $operation === 'create' && $get('role') === 'homeowner')->visible(fn (Get $get, string $operation): bool => $operation === 'create' && $get('role') === 'homeowner'),
            Select::make('account_status')->options(UserAccountStatus::options())->required()->visible(fn (string $operation): bool => $operation === 'edit')->dehydrated(fn (string $operation): bool => $operation === 'edit'),
            Textarea::make('rejection_reason')->maxLength(500)->columnSpanFull()->visible(fn (string $operation): bool => $operation === 'edit'),
            Textarea::make('suspension_reason')->maxLength(500)->columnSpanFull()->visible(fn (string $operation): bool => $operation === 'edit'),
        ])->columns(2);
    }

    private static function uppercase(?string $value): ?string
    {
        $value = self::collapseWhitespace($value);

        return $value === null ? null : mb_strtoupper($value, 'UTF-8');
    }

    private static function collapseWhitespace(?string $value): ?string
    {
        $value = preg_replace('/\s+/u', ' ', trim((string) $value));

        return filled($value) ? $value : null;
    }
}
