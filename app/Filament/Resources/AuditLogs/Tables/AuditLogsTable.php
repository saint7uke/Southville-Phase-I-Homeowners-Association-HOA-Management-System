<?php

declare(strict_types=1);

namespace App\Filament\Resources\AuditLogs\Tables;

use App\Models\AuditLog;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('created_at')->label(__('Date and time'))->dateTime('M d, Y g:i A')->sortable(),
            TextColumn::make('user.full_name')->label(__('Performed by'))->placeholder(__('System')),
            TextColumn::make('panel')->badge()->placeholder(__('System'))->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('record_description')->label(__('Record'))->state(function (AuditLog $record): string {
                $values = $record->new_values ?: $record->old_values ?: [];
                $name = collect(['title', 'subject', 'name', 'certificate_number', 'identifier'])->map(fn ($key) => $values[$key] ?? null)->first(fn ($value) => is_string($value) && $value !== '');

                return __(class_basename($record->auditable_type)).($name ? ' · '.Str::limit($name, 70) : '');
            })->wrap(),
            TextColumn::make('auditable_type')->label(__('Record type'))->formatStateUsing(fn (string $state): string => class_basename($state))->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('auditable_id')->label(__('Record ID'))->sortable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('ip_address')->label(__('IP address'))->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('user_agent')->label(__('User agent'))->limit(50)->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('old_values')->label(__('Old values'))->formatStateUsing(fn (?array $state): string => json_encode($state, JSON_UNESCAPED_SLASHES) ?: '—')->wrap()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('new_values')->label(__('New values'))->formatStateUsing(fn (?array $state): string => json_encode($state, JSON_UNESCAPED_SLASHES) ?: '—')->wrap()->toggleable(isToggledHiddenByDefault: true),
        ])->recordActions([
            Action::make('details')->label(__('View details'))->icon('heroicon-o-eye')->button()->tooltip(__('View details'))
                ->modalHeading(__('Audit details'))->modalContent(fn (AuditLog $record) => view('filament.audit-details', compact('record')))->modalSubmitAction(false)->modalCancelActionLabel(__('Close')),
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
