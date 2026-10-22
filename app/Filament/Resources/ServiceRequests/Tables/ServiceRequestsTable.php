<?php

namespace App\Filament\Resources\ServiceRequests\Tables;

use App\Actions\Certificates\IssueCertificate;
use App\Filament\Resources\Certificates\CertificateResource;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\CertificateTypeCatalog;
use App\Support\AuthenticatedActor;
use App\Support\OperationalAccess;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ServiceRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('homeowner.user.full_name')
                    ->label(__('Homeowner'))
                    ->searchable(['first_name', 'last_name']),
                TextColumn::make('ticket_number')
                    ->searchable(),
                TextColumn::make('request_type')
                    ->searchable(),
                TextColumn::make('subject')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('status')->formatStateUsing(fn ($state) => $state === null ? null : __((string) $state))
                    ->badge(),
                TextColumn::make('handler.full_name')
                    ->label(__('Assigned staff'))
                    ->sortable(),
                TextColumn::make('completed_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('request_type')->options(fn (): array => app(CertificateTypeCatalog::class)->historicalOptions()),
                SelectFilter::make('status')->options(['Pending' => __('Pending'), 'Processing' => __('Processing'), 'Approved' => __('Approved'), 'Rejected' => __('Rejected'), 'Completed' => __('Completed')]),
                Filter::make('created_at')->schema([
                    DatePicker::make('from')->label(__('Submitted from')),
                    DatePicker::make('to')->label(__('Submitted to'))->afterOrEqual('from'),
                ])->query(fn (Builder $query, array $data): Builder => $query
                    ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date))
                    ->when($data['to'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date))),
                TrashedFilter::make()->visible(fn (): bool => Filament::auth()->user()?->hasRole('hoa_admin') ?? false),
            ])
            ->recordActions([
                Action::make('draftPreview')->label(__('Preview draft PDF'))->url(fn (ServiceRequest $record): string => route('service-requests.draft', $record))->openUrlInNewTab()
                    ->visible(fn (ServiceRequest $record): bool => $record->request_type === 'Certificate of Residency'),
                Action::make('issueCertificate')
                    ->label(fn (ServiceRequest $record): string => in_array($record->request_type, CertificateTypeCatalog::ALLOWED, true) ? __('Prepare certificate') : __('Issue certificate'))
                    ->icon('heroicon-o-document-check')
                    ->color('success')
                    ->requiresConfirmation(fn (ServiceRequest $record): bool => ! in_array($record->request_type, CertificateTypeCatalog::ALLOWED, true))
                    ->url(fn (ServiceRequest $record): ?string => in_array($record->request_type, CertificateTypeCatalog::ALLOWED, true)
                        ? CertificateResource::getUrl('create', ['request' => $record->id]) : null)
                    ->visible(fn (ServiceRequest $record): bool => ! $record->trashed() && $record->status === 'Approved'
                        && OperationalAccess::allows(app(AuthenticatedActor::class)->user(), 'manage_requests')
                        && app(CertificateTypeCatalog::class)->isCertificateType($record->request_type))
                    ->action(function (ServiceRequest $record): void {
                        $actor = app(AuthenticatedActor::class)->user();
                        abort_unless($actor instanceof User, 403);
                        app(IssueCertificate::class)->fromApprovedRequest($record, $actor);
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ])->visible(fn (): bool => Filament::getCurrentPanel()?->getId() === 'admin'),
            ]);
    }
}
