<?php

declare(strict_types=1);

namespace App\Filament\Staff\Widgets;

use App\Models\Payment;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

final class StaffRecentPayments extends TableWidget
{
    protected static ?string $heading = 'My recently recorded payments';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table->query(Payment::query()->with('homeowner.user')->where('recorded_by', Filament::auth()->id())->latest('payment_date')->limit(10))->columns([
            TextColumn::make('or_number')->label('Receipt'),
            TextColumn::make('homeowner.user.full_name')->label('Homeowner')->searchable(),
            TextColumn::make('amount_paid')->money('PHP')->sortable(),
            TextColumn::make('status')->badge(),
            TextColumn::make('payment_date')->date('M d, Y'),
        ])->paginated(false);
    }
}
