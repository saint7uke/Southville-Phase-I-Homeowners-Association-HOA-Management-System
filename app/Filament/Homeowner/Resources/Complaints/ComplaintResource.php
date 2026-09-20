<?php

declare(strict_types=1);

namespace App\Filament\Homeowner\Resources\Complaints;

use App\Filament\Homeowner\Resources\Complaints\Pages\CreateComplaint;
use App\Filament\Homeowner\Resources\Complaints\Pages\ListComplaints;
use App\Filament\Homeowner\Resources\Complaints\Pages\ViewComplaint;
use App\Models\Complaint;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class ComplaintResource extends Resource
{
    protected static ?string $model = Complaint::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?string $navigationLabel = 'My Complaints';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('subject')->required()->maxLength(160),
            Select::make('category')->options(array_combine($categories = ['Noise', 'Property Damage', 'Neighbor Dispute', 'Common Area', 'Security', 'Others'], $categories))->required(),
            Select::make('priority')->options(['Low' => 'Low', 'Medium' => 'Medium', 'High' => 'High', 'Urgent' => 'Urgent'])->required()->default('Medium'),
            Textarea::make('description')->required()->minLength(20)->maxLength(5000)->columnSpanFull(),
            FileUpload::make('attachments')->label('Supporting attachments')->multiple()->maxFiles(3)->storeFiles(false)->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])->maxSize(5120)->columnSpanFull(),
            Placeholder::make('status_summary')->label('Current status')->content(fn (?Complaint $record): string => $record?->status ?? '-')->visible(fn (string $operation): bool => $operation === 'view'),
            Placeholder::make('handler_summary')->label('Handled by')->content(fn (?Complaint $record): string => $record?->handler?->full_name ?? 'Not yet assigned')->visible(fn (string $operation): bool => $operation === 'view'),
            Placeholder::make('timeline_summary')->label('Status timeline')->content(fn (?Complaint $record): string => $record === null ? '-' : 'Submitted '.$record->created_at->format('M d, Y g:i A').' · Last updated '.$record->updated_at->format('M d, Y g:i A').($record->resolved_at ? ' · Closed '.$record->resolved_at->format('M d, Y g:i A') : ''))->visible(fn (string $operation): bool => $operation === 'view')->columnSpanFull(),
            Placeholder::make('resolution_summary')->label('Resolution notes')->content(fn (?Complaint $record): string => $record?->admin_remarks ?: 'No resolution notes yet.')->visible(fn (string $operation): bool => $operation === 'view')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('ticket_number')->label('Ticket')->searchable(),
            TextColumn::make('subject')->searchable()->wrap(),
            TextColumn::make('category')->badge(),
            TextColumn::make('priority')->badge(),
            TextColumn::make('status')->badge(),
            TextColumn::make('created_at')->dateTime()->sortable(),
            TextColumn::make('updated_at')->label('Last updated')->dateTime()->sortable(),
        ])->defaultSort('created_at', 'desc')->recordActions([ViewAction::make()]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereHas('homeowner', fn (Builder $query): Builder => $query->where('user_id', Filament::auth()->id()));
    }

    public static function getPages(): array
    {
        return ['index' => ListComplaints::route('/'), 'create' => CreateComplaint::route('/create'), 'view' => ViewComplaint::route('/{record}')];
    }
}
