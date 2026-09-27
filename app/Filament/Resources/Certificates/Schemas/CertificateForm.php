<?php

declare(strict_types=1);

namespace App\Filament\Resources\Certificates\Schemas;

use App\Models\Homeowner;
use App\Models\ServiceRequest;
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
            Select::make('type')->options(array_combine(ServiceRequest::CERTIFICATE_TYPES, ServiceRequest::CERTIFICATE_TYPES))->required(),
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
