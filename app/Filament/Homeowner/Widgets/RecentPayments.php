<?php

declare(strict_types=1);

namespace App\Filament\Homeowner\Widgets;

use App\Models\Payment;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

final class RecentPayments extends TableWidget
{
    protected static ?string $heading = 'Recent payment history';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $user = Filament::auth()->user();
        $homeownerId = $user instanceof User ? $user->homeowner()->value('id') : null;

        return $table->query(Payment::query()->where('homeowner_id', $homeownerId)->latest('payment_date')->limit(3))->columns([
            TextColumn::make('covered_period')->label('Period'),
            TextColumn::make('amount_paid')->money('PHP'),
            TextColumn::make('status')->badge(),
            TextColumn::make('payment_date')->date('M d, Y'),
        ])->paginated(false);
    }
}
