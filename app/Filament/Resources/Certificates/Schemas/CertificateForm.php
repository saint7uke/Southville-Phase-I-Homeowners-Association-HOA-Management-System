<?php

declare(strict_types=1);

namespace App\Filament\Resources\Certificates\Schemas;

use App\Models\Homeowner;
use App\Models\ServiceRequest;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

final class CertificateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('homeowner_id')->relationship('homeowner', 'id')->getOptionLabelFromRecordUsing(fn (Homeowner $record): string => $record->user->full_name.' | '.$record->full_address)->searchable()->preload()->required(),
            Select::make('type')->options(array_combine(ServiceRequest::CERTIFICATE_TYPES, ServiceRequest::CERTIFICATE_TYPES))->required(),
            TextInput::make('purpose')->maxLength(255),
            DateTimePicker::make('expires_at')->seconds(false)->minDate(now())->nullable(),
        ])->columns(2);
    }
}
