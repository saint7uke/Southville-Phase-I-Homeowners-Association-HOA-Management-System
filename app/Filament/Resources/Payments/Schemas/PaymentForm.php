<?php

declare(strict_types=1);

namespace App\Filament\Resources\Payments\Schemas;

use App\Models\DuesObligation;
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
            Select::make('dues_obligation_id')
                ->relationship('duesObligation', 'id', modifyQueryUsing: fn ($query) => $query->whereIn('status', ['Pending', 'Partial', 'Overdue'])->with(['homeowner.user', 'duesSetting']))
                ->getOptionLabelFromRecordUsing(fn (DuesObligation $record): string => $record->homeowner->user->full_name.' | '.$record->duesSetting->name.' | '.str_pad((string) $record->billing_month, 2, '0', STR_PAD_LEFT).'/'.$record->billing_year)
                ->searchable()
                ->preload()
                ->disabled(fn (string $operation): bool => $operation === 'edit')
                ->required(),
            TextInput::make('amount_paid')->label('Amount paid')->numeric()->prefix('PHP')->minValue(0.01)->disabled(fn (string $operation): bool => $operation === 'edit')->required(),
            TextInput::make('penalty')->numeric()->prefix('PHP')->minValue(0)->default(0)->disabled(fn (string $operation): bool => $operation === 'edit')->required(),
            DatePicker::make('payment_date')->default(today())->maxDate(today())->required(),
            TextInput::make('covered_period')->placeholder('August 2026')->maxLength(40)->required(),
            Select::make('payment_method')->options(['Cash' => 'Cash', 'Bank Transfer' => 'Bank Transfer', 'GCash' => 'GCash', 'Maya' => 'Maya'])->required(),
            Textarea::make('notes')->maxLength(500)->columnSpanFull(),
        ])->columns(2);
    }
}
