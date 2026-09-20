<?php

declare(strict_types=1);

namespace App\Filament\Homeowner\Resources\ServiceRequests;

use App\Filament\Homeowner\Resources\ServiceRequests\Pages\CreateServiceRequest;
use App\Filament\Homeowner\Resources\ServiceRequests\Pages\ListServiceRequests;
use App\Filament\Homeowner\Resources\ServiceRequests\Pages\ViewServiceRequest;
use App\Models\ServiceRequest;
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

final class ServiceRequestResource extends Resource
{
    protected static ?string $model = ServiceRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'My Requests';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('request_type')->options(array_combine(ServiceRequest::TYPES, ServiceRequest::TYPES))->required(),
            TextInput::make('subject')->required()->maxLength(160),
            Textarea::make('details')->label('Description')->required()->maxLength(3000)->columnSpanFull(),
            FileUpload::make('attachments')->label('Supporting attachments')->multiple()->maxFiles(3)->storeFiles(false)->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])->maxSize(5120)->columnSpanFull(),
            Placeholder::make('status_summary')->label('Current status')->content(fn (?ServiceRequest $record): string => $record?->status ?? '-')->visible(fn (string $operation): bool => $operation === 'view'),
            Placeholder::make('handler_summary')->label('Handled by')->content(fn (?ServiceRequest $record): string => $record?->handler?->full_name ?? 'Not yet assigned')->visible(fn (string $operation): bool => $operation === 'view'),
            Placeholder::make('response_summary')->label('Response notes')->content(fn (?ServiceRequest $record): string => $record?->admin_remarks ?: 'No response notes yet.')->visible(fn (string $operation): bool => $operation === 'view')->columnSpanFull(),
            Placeholder::make('processed_summary')->label('Processing timeline')->content(fn (?ServiceRequest $record): string => $record === null ? '-' : 'Submitted '.$record->created_at->format('M d, Y g:i A').' · Last updated '.$record->updated_at->format('M d, Y g:i A').($record->completed_at ? ' · Completed '.$record->completed_at->format('M d, Y g:i A') : ''))->visible(fn (string $operation): bool => $operation === 'view')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('ticket_number')->label('Ticket')->searchable(),
            TextColumn::make('request_type')->searchable()->wrap(),
            TextColumn::make('subject')->searchable()->wrap(),
            TextColumn::make('status')->badge(),
            TextColumn::make('document_output')->label('Document')->placeholder('Not yet issued'),
            TextColumn::make('created_at')->dateTime()->sortable(),
            TextColumn::make('completed_at')->label('Processed date')->dateTime()->placeholder('Not completed')->sortable(),
        ])->defaultSort('created_at', 'desc')->recordActions([ViewAction::make()]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereHas('homeowner', fn (Builder $query): Builder => $query->where('user_id', Filament::auth()->id()));
    }

    public static function getPages(): array
    {
        return ['index' => ListServiceRequests::route('/'), 'create' => CreateServiceRequest::route('/create'), 'view' => ViewServiceRequest::route('/{record}')];
    }
}
