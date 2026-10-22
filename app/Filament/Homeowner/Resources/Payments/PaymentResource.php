<?php

declare(strict_types=1);

namespace App\Filament\Homeowner\Resources\Payments;

use App\Filament\Homeowner\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Actions\ReceiptActions;
use App\Models\Payment;
use App\Services\PaymentReceiptResolver;
use BackedEnum;
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

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('or_number')->label(__('OR number'))->searchable(),
            TextColumn::make('duesSetting.name')->label(__('Dues type')),
            TextColumn::make('covered_period')->label(__('Period')),
            TextColumn::make('amount_paid')->money('PHP')->sortable(),
            TextColumn::make('balance')->money('PHP')->sortable(),
            TextColumn::make('status')->formatStateUsing(fn ($state) => $state === null ? null : __((string) $state))->badge(),
            TextColumn::make('payment_date')->date()->sortable(),
        ])->defaultSort('payment_date', 'desc')->recordActions(ReceiptActions::make());
    }

    public static function getEloquentQuery(): Builder
    {
        $residentId = Filament::auth()->user()?->homeowner?->id;

        return app(PaymentReceiptResolver::class)->withReceipts(parent::getEloquentQuery())->where(function (Builder $query) use ($residentId): void {
            $query->where('payer_homeowner_id', $residentId)
                ->orWhereHas('homeowner', fn (Builder $query): Builder => $query->where('user_id', Filament::auth()->id()));
        });
    }

    public static function getPages(): array
    {
        return ['index' => ListPayments::route('/')];
    }
}
