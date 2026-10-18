<?php

declare(strict_types=1);

namespace App\Filament\Resources\Certificates\Schemas;

use App\Models\Homeowner;
use App\Services\CertificateTypeCatalog;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

final class CertificateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('homeowner_id')->relationship('homeowner', 'id', modifyQueryUsing: fn (Builder $query): Builder => $query->with('user:id,first_name,middle_name,last_name,suffix'))->getOptionLabelFromRecordUsing(fn (Homeowner $record): string => $record->user->full_name.' | '.$record->full_address)->searchable()->preload()->required(),
            Select::make('type')
                ->options(fn (string $operation): array => app(CertificateTypeCatalog::class)->activeOptions(
                    $operation === 'create' && (Filament::auth()->user()?->hasRole('hoa_admin') ?? false),
                ))
                ->live()
                ->required(),
            TextInput::make('custom_type')
                ->label('Custom certificate type')
                ->helperText('Enter a distinct certificate type. Capitalization and spacing differences do not create a new type.')
                ->maxLength(64)
                ->required(fn (callable $get): bool => $get('type') === CertificateTypeCatalog::OTHER_VALUE)
                ->visible(fn (callable $get, string $operation): bool => $operation === 'create' && $get('type') === CertificateTypeCatalog::OTHER_VALUE)
                ->dehydrated(fn (callable $get): bool => $get('type') === CertificateTypeCatalog::OTHER_VALUE),
            TextInput::make('purpose')->maxLength(255),
            DatePicker::make('expires_at')
                ->default(fn () => today()->endOfYear())
                ->displayFormat('F j, Y')
                ->disabled()
                ->dehydrated(false)
                ->helperText('Automatically set to the last day of the current year.'),
        ])->columns(2);
    }
}
