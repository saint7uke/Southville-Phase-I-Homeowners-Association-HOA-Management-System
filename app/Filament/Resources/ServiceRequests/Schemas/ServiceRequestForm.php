<?php

declare(strict_types=1);

namespace App\Filament\Resources\ServiceRequests\Schemas;

use App\Models\Homeowner;
use App\Models\User;
use App\Services\CertificateTypeCatalog;
use App\Validation\CaseAttachmentRules;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
                ->required(),
            Select::make('request_type')->options(fn (): array => app(CertificateTypeCatalog::class)->requestOptions())->required(),
            TextInput::make('subject')->required()->maxLength(160),
            Textarea::make('details')->label('Description')->required()->maxLength(3000)->columnSpanFull(),
            FileUpload::make('attachments')
                ->label('Supporting attachments')
                ->helperText('Upload up to 3 PDF, image, or Word files; 5 MB each.')
                ->multiple()
                ->maxFiles(3)
                ->storeFiles(false)
                ->acceptedFileTypes(CaseAttachmentRules::MIME_TYPES)
                ->maxSize(5120)
                ->visible(fn (string $operation): bool => $operation === 'create')
                ->columnSpanFull(),
            Placeholder::make('initial_status')
                ->label('Initial status')
                ->content('Pending')
                ->helperText('New service requests enter the workflow as Pending.')
                ->visible(fn (string $operation): bool => $operation === 'create'),
            Select::make('status')->options(['Pending' => 'Pending', 'Processing' => 'Processing', 'Approved' => 'Approved', 'Completed' => 'Completed', 'Rejected' => 'Rejected'])->required()->live()->visible(fn (string $operation): bool => $operation === 'edit'),
            Select::make('handled_by')->label('Assigned staff')->relationship('handler', 'email', modifyQueryUsing: fn (Builder $query): Builder => $query->role('hoa_staff')->where('account_status', 'Active'))->getOptionLabelFromRecordUsing(fn (User $record): string => $record->full_name.' | '.$record->email)->searchable()->preload(),
            Textarea::make('admin_remarks')->maxLength(3000)->required(fn (callable $get): bool => $get('status') === 'Rejected')->visible(fn (string $operation): bool => $operation === 'edit')->columnSpanFull(),
        ])->columns(2);
    }
}
