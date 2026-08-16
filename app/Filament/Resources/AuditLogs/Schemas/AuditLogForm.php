<?php

namespace App\Filament\Resources\AuditLogs\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AuditLogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'id'),
                TextInput::make('action')
                    ->required(),
                TextInput::make('auditable_type'),
                TextInput::make('auditable_id')
                    ->numeric(),
                Textarea::make('old_values')
                    ->columnSpanFull(),
                Textarea::make('new_values')
                    ->columnSpanFull(),
                TextInput::make('ip_address'),
                TextInput::make('user_agent'),
            ]);
    }
}
