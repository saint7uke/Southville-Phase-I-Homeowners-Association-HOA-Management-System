<?php

declare(strict_types=1);

namespace App\Filament\Resources\Payments\Schemas;

use App\Models\Homeowner;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

final class PaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('homeowner_id')->relationship('homeowner', 'id')->getOptionLabelFromRecordUsing(fn (Homeowner $record): string => $record->user->full_name.' | '.$record->full_address)->searchable()->preload()->required(),
            Select::make('dues_setting_id')->relationship('duesSetting', 'name')->required()->preload(),
            TextInput::make('amount_paid')->label('Amount paid')->numeric()->prefix('PHP')->minValue(0.01)->required(),
            TextInput::make('penalty')->numeric()->prefix('PHP')->minValue(0)->default(0)->required(),
            DatePicker::make('payment_date')->default(today())->maxDate(today())->required(),
            TextInput::make('covered_period')->placeholder('August 2026')->maxLength(40)->required(),
            Select::make('payment_method')->options(['Cash' => 'Cash', 'Bank Transfer' => 'Bank Transfer', 'GCash' => 'GCash', 'Maya' => 'Maya'])->required(),
            Textarea::make('notes')->maxLength(500)->columnSpanFull(),
        ])->columns(2);
    }
}
