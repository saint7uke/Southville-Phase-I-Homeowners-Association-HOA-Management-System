<?php

declare(strict_types=1);

namespace App\Filament\Resources\ServiceRequests\Schemas;

use App\Models\Homeowner;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\CertificateTypeCatalog;
use App\Services\ServiceRequestWorkflow;
use App\Validation\CaseAttachmentRules;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

final class ServiceRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('homeowner_id')
                ->relationship('homeowner', 'id', modifyQueryUsing: fn (Builder $query): Builder => $query
                    ->whereHas('user', fn (Builder $query): Builder => $query->whereNull('users.deleted_at'))
                    ->with('user:id,first_name,middle_name,last_name,suffix'))
                ->getOptionLabelFromRecordUsing(fn (Homeowner $record): string => $record->user->full_name.' | '.$record->full_address)
                ->searchable()
                ->preload()
                ->disabled(fn (string $operation): bool => $operation === 'edit')->dehydrated(fn (string $operation): bool => $operation === 'create')
                ->required(),
            Select::make('request_type')->options(fn (?ServiceRequest $record): array => $record
                ? [$record->request_type => $record->request_type] : app(CertificateTypeCatalog::class)->requestOptions())->live()->required()
                ->disabled(fn (string $operation): bool => $operation !== 'create')->dehydrated(fn (string $operation): bool => $operation === 'create'),
            TextInput::make('cohabitant_one')->label(__('First resident full name'))->maxLength(160)->required(fn (Get $get): bool => $get('request_type') === 'Certificate of Cohabitation')->visible(fn (Get $get): bool => $get('request_type') === 'Certificate of Cohabitation')->disabled(fn (string $operation): bool => $operation !== 'create')->dehydrated(fn (string $operation): bool => $operation === 'create'),
            TextInput::make('cohabitant_two')->label(__('Second resident full name'))->maxLength(160)->required(fn (Get $get): bool => $get('request_type') === 'Certificate of Cohabitation')->visible(fn (Get $get): bool => $get('request_type') === 'Certificate of Cohabitation')->disabled(fn (string $operation): bool => $operation !== 'create')->dehydrated(fn (string $operation): bool => $operation === 'create'),
            TextInput::make('subject')->required()->maxLength(160),
            Textarea::make('details')->label(__('Description'))->required()->maxLength(3000)->columnSpanFull(),
            FileUpload::make('attachments')
                ->label(__('Supporting attachments'))
                ->helperText(__('Upload up to 3 PDF, image, or Word files; 5 MB each.'))
                ->multiple()
                ->maxFiles(3)
                ->storeFiles(false)
                ->acceptedFileTypes(CaseAttachmentRules::MIME_TYPES)
                ->maxSize(5120)
                ->visible(fn (string $operation): bool => $operation === 'create')
                ->columnSpanFull(),
            Placeholder::make('initial_status')
                ->label(__('Initial status'))
                ->content('Pending')
                ->helperText(__('New service requests enter the workflow as Pending.'))
                ->visible(fn (string $operation): bool => $operation === 'create'),
            Select::make('status')->options(fn (?ServiceRequest $record): array => $record ? app(ServiceRequestWorkflow::class)->editableOptions($record) : [])
                ->helperText(fn (?ServiceRequest $record) => $record ? view('filament.resources.service-requests.workflow-guidance', ['serviceRequest' => $record]) : null)
                ->required()->live()->visible(fn (string $operation): bool => $operation === 'edit'),
            Select::make('handled_by')->label(__('Assigned staff'))->relationship('handler', 'email', modifyQueryUsing: fn (Builder $query): Builder => $query->role('hoa_staff')->where('account_status', 'Active'))->getOptionLabelFromRecordUsing(fn (User $record): string => $record->full_name.' | '.$record->email)->searchable()->preload(),
            Textarea::make('admin_remarks')->maxLength(3000)->required(fn (callable $get): bool => $get('status') === 'Rejected')->visible(fn (string $operation): bool => $operation === 'edit')->columnSpanFull(),
        ])->columns(2);
    }
}
