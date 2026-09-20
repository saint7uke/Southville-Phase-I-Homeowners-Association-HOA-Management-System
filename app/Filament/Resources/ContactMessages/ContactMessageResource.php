<?php

namespace App\Filament\Resources\ContactMessages;

use App\Filament\Resources\ContactMessages\Pages\EditContactMessage;
use App\Filament\Resources\ContactMessages\Pages\ListContactMessages;
use App\Models\ContactMessage;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContactMessageResource extends Resource
{
    protected static ?string $model = ContactMessage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('message')->disabled()->columnSpanFull(),
            Select::make('status')->options(['New' => 'New', 'In Progress' => 'In Progress', 'Closed' => 'Closed'])->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable(),
            TextColumn::make('email')->searchable(),
            TextColumn::make('subject')->searchable()->wrap(),
            TextColumn::make('status')->badge(),
            TextColumn::make('created_at')->dateTime()->sortable(),
        ])->defaultSort('created_at', 'desc')->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ListContactMessages::route('/'), 'edit' => EditContactMessage::route('/{record}/edit')];
    }
}
