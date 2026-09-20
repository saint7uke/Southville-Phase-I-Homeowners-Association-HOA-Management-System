<?php

declare(strict_types=1);

namespace App\Filament\Homeowner\Resources\Payments;

use App\Filament\Homeowner\Resources\Payments\Pages\ListPayments;
use App\Models\Payment;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Payment History';

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('or_number')->label('OR number')->searchable(),
            TextColumn::make('duesSetting.name')->label('Dues type'),
            TextColumn::make('covered_period')->label('Period'),
            TextColumn::make('amount_paid')->money('PHP')->sortable(),
            TextColumn::make('balance')->money('PHP')->sortable(),
            TextColumn::make('status')->badge(),
            TextColumn::make('payment_date')->date()->sortable(),
        ])->defaultSort('payment_date', 'desc')->recordActions([
            Action::make('receipt')->url(fn (Payment $record): string => route('payments.receipt.download', $record))->openUrlInNewTab()->visible(fn (Payment $record): bool => in_array($record->review_status, ['Approved', 'Recorded'], true) && $record->status === 'Paid'),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereHas('homeowner', fn (Builder $query): Builder => $query->where('user_id', Filament::auth()->id()));
    }

    public static function getPages(): array
    {
        return ['index' => ListPayments::route('/')];
    }
}
