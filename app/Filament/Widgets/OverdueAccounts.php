<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\DuesObligation;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

final class OverdueAccounts extends TableWidget
{
    protected static ?int $sort = 30;

    protected static ?string $heading = 'Most overdue accounts';

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table->query(DuesObligation::query()->with(['homeowner.user', 'duesSetting'])->where('status', 'Overdue')->oldest('due_date')->limit(10))->columns([
            TextColumn::make('homeowner.user.full_name')->label(__('Homeowner')),
            TextColumn::make('duesSetting.name')->label(__('Dues')),
            TextColumn::make('amount_due')->money('PHP'),
            TextColumn::make('due_date')->date(),
            TextColumn::make('overdue_days')->label(__('Days overdue'))->state(fn (DuesObligation $record): int => $record->due_date->diffInDays(today())),
        ])->paginated(false);
    }
}
