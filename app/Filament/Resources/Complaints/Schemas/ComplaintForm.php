<?php

declare(strict_types=1);

namespace App\Filament\Resources\Complaints\Schemas;

use App\Models\Complaint;
use App\Models\Homeowner;
use App\Models\User;
use App\Validation\CaseAttachmentRules;
use App\Validation\ComplaintSubmissionValidator;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

final class ComplaintForm
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
                ->disabled(fn (string $operation): bool => $operation === 'edit' && self::isStaff())
                ->required(),
            TextInput::make('subject')->maxLength(160)->disabled(fn (string $operation): bool => $operation === 'edit' && self::isStaff())->required(),
            Select::make('category')->options(array_combine(ComplaintSubmissionValidator::CATEGORIES, ComplaintSubmissionValidator::CATEGORIES))->disabled(fn (string $operation): bool => $operation === 'edit' && self::isStaff())->required(),
            Select::make('priority')->options(array_combine(ComplaintSubmissionValidator::PRIORITIES, ComplaintSubmissionValidator::PRIORITIES))->default('Medium')->required(),
            Textarea::make('description')->rows(6)->minLength(20)->maxLength(5000)->disabled(fn (string $operation): bool => $operation === 'edit' && self::isStaff())->required()->columnSpanFull(),
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
                ->helperText('New complaints enter the review workflow as Pending.')
                ->visible(fn (string $operation): bool => $operation === 'create'),
            Select::make('status')
                ->options(fn (?Complaint $record): array => self::statusOptions($record, self::isStaff()))
                ->helperText(fn (?Complaint $record): ?string => $record?->status === 'Pending'
                    ? (self::isStaff()
                        ? 'Move the complaint to Under Review before resolving it.'
                        : 'Move the complaint to Under Review before resolving, rejecting, closing, or dismissing it.')
                    : null)
                ->required()
                ->visible(fn (string $operation): bool => $operation === 'edit')
                ->live(),
            Select::make('handled_by')
                ->label('Assigned staff')
                ->relationship('handler', 'email', modifyQueryUsing: fn (Builder $query): Builder => $query->role('hoa_staff')->where('account_status', 'Active'))
                ->getOptionLabelFromRecordUsing(fn (User $record): string => $record->full_name.' | '.$record->email)
                ->searchable()
                ->preload()
                ->disabled(fn (): bool => ! Filament::auth()->user()?->hasRole('hoa_admin'))
                ->visible(fn (string $operation): bool => $operation !== 'view'),
            Textarea::make('admin_remarks')->label('Resolution notes')->rows(4)->maxLength(3000)->required(fn (callable $get): bool => in_array($get('status'), ['Resolved', 'Rejected', 'Closed', 'Dismissed'], true))->visible(fn (string $operation): bool => $operation === 'edit')->columnSpanFull(),
            Placeholder::make('status_summary')->label('Current status')->content(fn (?Complaint $record): string => $record?->status ?? '-')->visible(fn (string $operation): bool => $operation === 'view'),
            Placeholder::make('handler_summary')->label('Assigned staff')->content(fn (?Complaint $record): string => $record?->handler?->full_name ?? 'Not yet assigned')->visible(fn (string $operation): bool => $operation === 'view'),
            Placeholder::make('timeline_summary')->label('Status timeline')->content(fn (?Complaint $record): string => $record === null ? '-' : 'Submitted '.$record->created_at->format('M d, Y g:i A').' | Last updated '.$record->updated_at->format('M d, Y g:i A').($record->resolved_at ? ' | Finalized '.$record->resolved_at->format('M d, Y g:i A') : ''))->visible(fn (string $operation): bool => $operation === 'view')->columnSpanFull(),
            Placeholder::make('resolution_summary')->label('Resolution notes')->content(fn (?Complaint $record): string => $record?->admin_remarks ?: 'No resolution notes yet.')->visible(fn (string $operation): bool => $operation === 'view')->columnSpanFull(),
        ])->columns(2);
    }

    /** @return array<string, string> */
    private static function statusOptions(?Complaint $record, bool $staff): array
    {
        if ($record === null) {
            return ['Pending' => 'Pending'];
        }

        return match ($record->status) {
            'Pending' => ['Pending' => 'Pending', 'Under Review' => 'Under Review'],
            'Under Review' => $staff ? [
                'Under Review' => 'Under Review',
                'Resolved' => 'Resolved',
            ] : [
                'Under Review' => 'Under Review',
                'Resolved' => 'Resolved',
                'Rejected' => 'Rejected',
                'Closed' => 'Closed',
                'Dismissed' => 'Dismissed',
            ],
            default => [$record->status => $record->status],
        };
    }

    private static function isStaff(): bool
    {
        $user = Filament::auth()->user();

        return ($user?->hasRole('hoa_staff') ?? false) && ! ($user?->hasRole('hoa_admin') ?? false);
    }
}
