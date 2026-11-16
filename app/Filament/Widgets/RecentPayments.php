<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Payment;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

final class RecentPayments extends TableWidget
{
    protected static ?int $sort = 40;

    protected static ?string $heading = 'Recent payments';

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        $columns = [
            TextColumn::make('homeowner.user.full_name')->label(__('Homeowner')),
            TextColumn::make('amount_paid')->money('PHP'),
            TextColumn::make('status')->formatStateUsing(fn ($state) => $state === null ? null : __((string) $state))->badge(),
            TextColumn::make('payment_date')->date(),
        ];

        if (Filament::getCurrentPanel()?->getId() !== 'admin') {
            array_unshift($columns, TextColumn::make('or_number')->label(__('OR')));
        }

        return $table->query(Payment::query()->with(['homeowner.user', 'duesSetting'])->latest('payment_date')->latest('id')->limit(10))->columns($columns)->paginated(false);
    }
}
