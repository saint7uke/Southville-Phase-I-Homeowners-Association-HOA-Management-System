<?php

declare(strict_types=1);

namespace App\Filament\Resources\Homeowners\Schemas;

use App\Models\Homeowner;
use App\Models\User;
use App\Support\PersonName;
use App\Support\PropertyAddress;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

final class HomeownerForm
{
    public static function configure(Schema $schema): Schema
    {
        $lockedForStaff = fn (?Homeowner $record): bool => $record !== null && Filament::getCurrentPanel()?->getId() === 'staff';

        return $schema->components([
            Select::make('user_id')
                ->relationship(
                    'user',
                    'id',
                    modifyQueryUsing: fn (Builder $query, ?Homeowner $record): Builder => $query
                        ->role('homeowner')
                        ->where(function (Builder $query) use ($record): void {
                            $query->whereDoesntHave('homeowner', fn (Builder $homeownerQuery): Builder => $homeownerQuery->withTrashed());
                            if ($record !== null) {
                                $query->orWhere('users.id', $record->user_id);
                            }
                        }),
                )
                ->getOptionLabelFromRecordUsing(fn (User $record): string => $record->full_name.' | '.$record->email)
                ->rule(fn (?Homeowner $record): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($record): void {
                    $user = User::query()->find($value);
                    if ($user === null || ! $user->hasRole('homeowner')) {
                        $fail('The selected user must have the Homeowner role.');

                        return;
                    }

                    if ($user->homeowner()->withTrashed()->where('id', '!=', $record?->id ?? 0)->exists()) {
                        $fail('The selected user is already linked to a homeowner record.');
                    }
                })
                ->searchable()
                ->preload()
                ->disabled(fn (?Homeowner $record): bool => $record !== null)
                ->required(),
            TextInput::make('user_email')
                ->label('Email')
                ->email()
                ->maxLength(255)
                ->rule(fn (?Homeowner $record): mixed => Rule::unique('users', 'email')->ignore($record?->user_id))
                ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? mb_strtolower(trim($state)) : null)
                ->visible(fn (string $operation): bool => $operation === 'edit')
                ->required(fn (string $operation): bool => $operation === 'edit'),
            TextInput::make('user_contact_number')
                ->label('Contact number')
                ->tel()
                ->length(11)
                ->regex('/^09\d{9}$/')
                ->visible(fn (string $operation): bool => $operation === 'edit')
                ->required(fn (string $operation): bool => $operation === 'edit'),
            TextInput::make('house_number')->maxLength(50)->required()->disabled($lockedForStaff)->dehydrateStateUsing(fn (?string $state): ?string => self::collapseWhitespace($state)),
            TextInput::make('street')->maxLength(100)->required()->disabled($lockedForStaff)->dehydrateStateUsing(fn (?string $state): ?string => self::collapseWhitespace($state)),
            TextInput::make('block')->maxLength(20)->required()->disabled($lockedForStaff)->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $state)), 'UTF-8') : null),
            TextInput::make('lot')
                ->maxLength(20)
                ->required()
                ->disabled($lockedForStaff)
                ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $state)), 'UTF-8') : null),
            TextInput::make('phase')->maxLength(50)->required()->default('Southville Phase I')->disabled($lockedForStaff),
            Select::make('ownership_type')->options(['Owner' => 'Owner', 'Tenant' => 'Tenant', 'Co-owner' => 'Co-owner'])->required()->disabled($lockedForStaff)->reactive(),
            DatePicker::make('residency_date')->maxDate(today())->disabled($lockedForStaff)->visible(fn (Get $get) => in_array($get('ownership_type'), ['Owner', 'Co-owner'])),
            Select::make('landlord_homeowner_id')
                ->label('Registered landlord')
                ->options(function (Get $get, ?Homeowner $record): array {
                    $addressKey = PropertyAddress::key([
                        'house_number' => $get('house_number'),
                        'street' => $get('street'),
                        'block' => $get('block'),
                        'lot' => $get('lot'),
                        'phase' => $get('phase'),
                    ]);

                    return Homeowner::query()
                        ->where('address_key', $addressKey)
                        ->whereIn('ownership_type', ['Owner', 'Co-owner'])
                        ->where('status', 'Active')
                        ->whereHas('user', fn (Builder $query): Builder => $query->where('account_status', 'Active'))
                        ->when($record !== null, fn (Builder $query): Builder => $query->whereKeyNot($record->id))
                        ->with('user')
                        ->get()
                        ->mapWithKeys(fn (Homeowner $homeowner): array => [$homeowner->id => $homeowner->user->full_name])
                        ->all();
                })
                ->searchable()
                ->live()
                ->helperText('Only active Owners or Co-owners registered at this address are listed.')
                ->visible(fn (Get $get): bool => $get('ownership_type') === 'Tenant'),
            TextInput::make('landlord_name')->label('Landlord name snapshot')->maxLength(100)->visible(fn (Get $get) => $get('ownership_type') === 'Tenant'),
            TextInput::make('landlord_contact')->tel()->length(11)->regex('/^09\d{9}$/')->visible(fn (Get $get) => $get('ownership_type') === 'Tenant'),
            DatePicker::make('lease_start_date')->visible(fn (Get $get) => $get('ownership_type') === 'Tenant'),
            DatePicker::make('lease_end_date')->visible(fn (Get $get) => $get('ownership_type') === 'Tenant')->after('lease_start_date'),
            FileUpload::make('verification_document_path')->label('Verification Document')->disk('local')->directory('verification_documents')->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png']),
            TextInput::make('emergency_contact_name')->maxLength(100)->regex("/^[\pL\s'.-]+$/u")->required()->dehydrateStateUsing(fn (?string $state): ?string => PersonName::normalize($state)),
            TextInput::make('emergency_contact_number')->tel()->length(11)->regex('/^09\d{9}$/')->required(),
            Select::make('status')->options(['Active' => 'Active', 'Inactive' => 'Inactive', 'Delinquent' => 'Delinquent'])->required()->disabled($lockedForStaff),
        ])->columns(2);
    }

    private static function collapseWhitespace(?string $value): ?string
    {
        $value = preg_replace('/\s+/u', ' ', trim((string) $value));

        return filled($value) ? $value : null;
    }
}
