<?php

declare(strict_types=1);

namespace App\Filament\Resources\AuditLogs\Tables;

use App\Models\AuditLog;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('user.full_name')->label(__('Actor'))->placeholder(__('System')),
            TextColumn::make('action')->searchable()->badge(),
            TextColumn::make('panel')->badge()->placeholder(__('System')),
            TextColumn::make('auditable_type')->label(__('Record type'))->formatStateUsing(fn (string $state): string => class_basename($state)),
            TextColumn::make('auditable_id')->label(__('Record ID'))->sortable(),
            TextColumn::make('ip_address')->label(__('IP address')),
            TextColumn::make('user_agent')->label(__('User agent'))->limit(50)->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('old_values')->label(__('Old values'))->formatStateUsing(fn (?array $state): string => json_encode($state, JSON_UNESCAPED_SLASHES) ?: '—')->wrap()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('new_values')->label(__('New values'))->formatStateUsing(fn (?array $state): string => json_encode($state, JSON_UNESCAPED_SLASHES) ?: '—')->wrap()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('created_at')->dateTime()->sortable(),
        ])->filters([
            SelectFilter::make('user_id')->label(__('User'))->relationship('user', 'email')->searchable()->preload(),
            SelectFilter::make('panel')->options(['admin' => 'Admin', 'staff' => 'Staff', 'homeowner' => 'Homeowner', 'portal' => 'Legacy portal']),
            SelectFilter::make('action')->options(fn (): array => AuditLog::query()->whereNotNull('action')->distinct()->orderBy('action')->pluck('action', 'action')->all())->searchable(),
            Filter::make('created_at')->form([
                DatePicker::make('from'),
                DatePicker::make('until')->afterOrEqual('from'),
            ])->query(fn (Builder $query, array $data): Builder => $query
                ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date))
                ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date))),
        ])->defaultSort('created_at', 'desc');
    }
}
