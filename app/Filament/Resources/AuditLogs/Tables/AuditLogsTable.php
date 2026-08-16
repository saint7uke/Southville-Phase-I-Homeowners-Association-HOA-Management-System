<?php

declare(strict_types=1);

namespace App\Filament\Resources\AuditLogs\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('user.full_name')->label('Actor')->placeholder('System'),
            TextColumn::make('action')->searchable()->badge(),
            TextColumn::make('auditable_type')->label('Record type')->formatStateUsing(fn (string $state): string => class_basename($state)),
            TextColumn::make('auditable_id')->label('Record ID')->sortable(),
            TextColumn::make('ip_address')->label('IP address'),
            TextColumn::make('created_at')->dateTime()->sortable(),
        ])->defaultSort('created_at', 'desc');
    }
}
