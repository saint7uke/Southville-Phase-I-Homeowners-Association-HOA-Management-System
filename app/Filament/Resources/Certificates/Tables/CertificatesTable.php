<?php

declare(strict_types=1);

namespace App\Filament\Resources\Certificates\Tables;

use App\Actions\Certificates\ManageCertificateCollection;
use App\Models\Certificate;
use App\Support\ActionFeedback;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\URL;

final class CertificatesTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('certificate_number')->label(__('Certificate no.'))->searchable(),
            TextColumn::make('homeowner.user.full_name')->label(__('Resident'))->searchable(['first_name', 'last_name']),
            TextColumn::make('type')->wrap(),
            TextColumn::make('status')->formatStateUsing(fn ($state) => $state === null ? null : __((string) $state))->badge(),
            TextColumn::make('issued_at')->dateTime('M d, Y g:i A')->sortable(),
            TextColumn::make('expires_at')->dateTime('M d, Y')->placeholder(__('No expiry'))->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('collection')->label(__('Collection'))->state(fn (Certificate $record): string => __($record->collected_at ? 'Collected' : ($record->ready_for_pickup_at ? 'Ready for pickup' : 'Awaiting preparation')))->badge(),
        ])->defaultSort('issued_at', 'desc')->filters([
            TrashedFilter::make()->visible(fn (): bool => Filament::auth()->user()?->hasRole('hoa_admin') ?? false),
        ])->recordActions([
            Action::make('viewPrint')->label(__('View / Print'))->icon('heroicon-o-printer')->button()->tooltip(__('View / Print'))
                ->url(fn (Certificate $record): string => URL::temporarySignedRoute('certificates.download', now()->addMinutes(30), ['certificate' => $record->id, 'view' => '1']))
                ->openUrlInNewTab()->visible(fn (Certificate $record): bool => $record->status === 'Issued'),
            Action::make('download')->icon('heroicon-o-arrow-down-tray')->button()->tooltip(__('Download'))
                ->url(fn (Certificate $record): string => URL::temporarySignedRoute('certificates.download', now()->addMinutes(30), $record))
                ->openUrlInNewTab()
                ->visible(fn (Certificate $record): bool => $record->status === 'Issued'),
            Action::make('readyForPickup')->label(__('Ready for pickup'))->requiresConfirmation()
                ->modalDescription(__('Confirm that the physical certificate has the required signature and dry seal.'))
                ->visible(fn (Certificate $record): bool => $record->status === 'Issued' && ! $record->ready_for_pickup_at && ! $record->trashed() && (Filament::auth()->user()?->can('update', $record) ?? false))
                ->action(fn (Certificate $record) => ActionFeedback::after('Certificate Marked Ready for Pickup Successfully!', fn () => app(ManageCertificateCollection::class)->handle($record->id, 'ready', Filament::auth()->user()))),
            Action::make('collected')->label(__('Mark collected'))->requiresConfirmation()
                ->visible(fn (Certificate $record): bool => $record->status === 'Issued' && $record->ready_for_pickup_at !== null && $record->collected_at === null && ! $record->trashed() && (Filament::auth()->user()?->can('update', $record) ?? false))
                ->action(fn (Certificate $record) => ActionFeedback::after('Certificate Collection Recorded Successfully!', fn () => app(ManageCertificateCollection::class)->handle($record->id, 'collect', Filament::auth()->user()))),
            EditAction::make()->button()->tooltip(__('View details')),
            DeleteAction::make()->visible(fn (): bool => Filament::getCurrentPanel()?->getId() === 'admin'),
            RestoreAction::make()->visible(fn (): bool => Filament::getCurrentPanel()?->getId() === 'admin'),
        ]);
    }
}
