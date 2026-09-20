<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Payment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

final class RecentPayments extends TableWidget
{
    protected static ?string $heading = 'Recent payments';

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table->query(Payment::query()->with(['homeowner.user', 'duesSetting'])->latest('payment_date')->latest('id')->limit(10))->columns([
            TextColumn::make('or_number')->label('OR'),
            TextColumn::make('homeowner.user.full_name')->label('Homeowner'),
            TextColumn::make('amount_paid')->money('PHP'),
            TextColumn::make('status')->badge(),
            TextColumn::make('payment_date')->date(),
        ])->paginated(false);
    }
}
