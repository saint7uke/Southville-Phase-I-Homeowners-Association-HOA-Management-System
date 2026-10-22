<?php

declare(strict_types=1);

namespace App\Filament\Homeowner\Resources\ServiceRequests;

use App\Filament\Homeowner\Resources\ServiceRequests\Pages\CreateServiceRequest;
use App\Filament\Homeowner\Resources\ServiceRequests\Pages\ListServiceRequests;
use App\Filament\Homeowner\Resources\ServiceRequests\Pages\ViewServiceRequest;
use App\Models\ServiceRequest;
use App\Services\CertificateTypeCatalog;
use App\Services\CohabitationCertificateData;
use App\Validation\CaseAttachmentRules;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
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

    protected static string|\UnitEnum|null $navigationGroup = 'Requests & Concerns';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('request_type')->options(fn (?ServiceRequest $record): array => $record ? [$record->request_type => $record->request_type] : app(CertificateTypeCatalog::class)->requestOptions())->live()->required(),
            TextInput::make('cohabitant_one')->label(__('First resident full name'))->maxLength(160)->required(fn (Get $get): bool => $get('request_type') === 'Certificate of Cohabitation')->visible(fn (Get $get): bool => $get('request_type') === 'Certificate of Cohabitation'),
            TextInput::make('cohabitant_two')->label(__('Second resident full name'))->maxLength(160)->required(fn (Get $get): bool => $get('request_type') === 'Certificate of Cohabitation')->visible(fn (Get $get): bool => $get('request_type') === 'Certificate of Cohabitation'),
            TextInput::make('subject')->required()->maxLength(160),
            Textarea::make('details')->label(__('Description'))->required()->maxLength(3000)->columnSpanFull(),
            FileUpload::make('attachments')->label(__('Supporting attachments'))->multiple()->maxFiles(3)->storeFiles(false)->acceptedFileTypes(CaseAttachmentRules::MIME_TYPES)->maxSize(5120)->columnSpanFull(),
            Placeholder::make('status_summary')->label(__('Current status'))->content(fn (?ServiceRequest $record): string => $record?->status ?? '-')->visible(fn (string $operation): bool => $operation === 'view'),
            Placeholder::make('certificate_guidance')->label(__('Cohabitation certificate preparation'))
                ->content(fn (?ServiceRequest $record) => $record ? view('filament.resources.service-requests.cohabitation-guidance', ['serviceRequest' => $record]) : null)
                ->visible(fn (string $operation, ?ServiceRequest $record): bool => $operation === 'view' && $record?->request_type === CohabitationCertificateData::TYPE)
                ->columnSpanFull(),
            Placeholder::make('handler_summary')->label(__('Handled by'))->content(fn (?ServiceRequest $record): string => $record?->handler?->full_name ?? 'Not yet assigned')->visible(fn (string $operation): bool => $operation === 'view'),
            Placeholder::make('response_summary')->label(__('Response notes'))->content(fn (?ServiceRequest $record): string => $record?->admin_remarks ?: 'No response notes yet.')->visible(fn (string $operation): bool => $operation === 'view')->columnSpanFull(),
            Placeholder::make('processed_summary')->label(__('Processing timeline'))->content(fn (?ServiceRequest $record): string => $record === null ? '-' : 'Submitted '.$record->created_at->format('M d, Y g:i A').' · Last updated '.$record->updated_at->format('M d, Y g:i A').($record->completed_at ? ' · Completed '.$record->completed_at->format('M d, Y g:i A') : ''))->visible(fn (string $operation): bool => $operation === 'view')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('ticket_number')->label(__('Ticket'))->searchable(),
            TextColumn::make('request_type')->searchable()->wrap(),
            TextColumn::make('subject')->searchable()->wrap(),
            TextColumn::make('status')->formatStateUsing(fn ($state) => $state === null ? null : __((string) $state))->badge(),
            TextColumn::make('document_output')->label(__('Document'))->placeholder(__('Not yet issued')),
            TextColumn::make('created_at')->dateTime()->sortable(),
            TextColumn::make('completed_at')->label(__('Processed date'))->dateTime()->placeholder(__('Not completed'))->sortable(),
        ])->defaultSort('created_at', 'desc')->recordActions([ViewAction::make(),
            Action::make('draftPreview')->label(__('Preview draft PDF'))->url(fn (ServiceRequest $record): string => route('service-requests.draft', $record))->openUrlInNewTab()
                ->visible(fn (ServiceRequest $record): bool => $record->request_type === 'Certificate of Residency'),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('homeowner')->whereHas('homeowner', fn (Builder $query): Builder => $query->where('user_id', Filament::auth()->id()));
    }

    public static function getPages(): array
    {
        return ['index' => ListServiceRequests::route('/'), 'create' => CreateServiceRequest::route('/create'), 'view' => ViewServiceRequest::route('/{record}')];
    }
}
