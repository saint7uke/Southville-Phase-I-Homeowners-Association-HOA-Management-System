<?php

declare(strict_types=1);

namespace App\Filament\Resources\Payments\Tables;

use App\Actions\Payments\ChangePaymentDeletionState;
use App\Actions\Payments\ReviewPaymentProof;
use App\Filament\Resources\Payments\Actions\ReceiptActions;
use App\Models\Payment;
use App\Models\User;
use App\Support\AuthenticatedActor;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('homeowner.user.full_name')->label(__('Liable resident'))->searchable(['first_name', 'last_name']),
            TextColumn::make('payer.user.full_name')->label(__('Actual payer'))->placeholder(__('Legacy'))->searchable(['first_name', 'last_name']),
            TextColumn::make('homeowner.full_address')->label(__('Property'))->wrap()->toggleable(),
            TextColumn::make('or_number')->label(__('OR number'))->searchable()->copyable(),
            TextColumn::make('duesSetting.name')->label(__('Dues type')),
            TextColumn::make('duesObligation.amount_due')->label(__('Amount due'))->money('PHP')->placeholder(__('Legacy')),
            TextColumn::make('amount_paid')->money('PHP')->sortable(),
            TextColumn::make('penalty')->money('PHP')->sortable()->toggleable(),
            TextColumn::make('balance')->money('PHP')->sortable(),
            TextColumn::make('payment_date')->date()->sortable(),
            TextColumn::make('payment_method')->badge(),
            TextColumn::make('transaction_reference')->label(__('Reference'))->placeholder(__('Cash / legacy'))->copyable()->toggleable(),
            TextColumn::make('covered_period')->label(__('Billing period')),
            TextColumn::make('status')->formatStateUsing(fn ($state) => $state === null ? null : __((string) $state))->label(__('Payment status'))->badge(),
            TextColumn::make('review_status')->formatStateUsing(fn ($state) => $state === null ? null : __((string) $state))->label(__('Review status'))->badge(),
            TextColumn::make('recorder.full_name')->label(__('Recorded by'))->toggleable(),
        ])->defaultSort('payment_date', 'desc')->filters([
            SelectFilter::make('status')->label(__('Payment status'))->options(['Pending Review' => 'Pending Review', 'Paid' => __('Paid'), 'Partial' => __('Partial'), 'Rejected' => __('Rejected')]),
            SelectFilter::make('review_status')->label(__('Review status'))->options(['Pending' => __('Pending'), 'Approved' => __('Approved'), 'Rejected' => __('Rejected'), 'Recorded' => __('Recorded')]),
            SelectFilter::make('dues_setting_id')->label(__('Dues schedule'))->relationship('duesSetting', 'name')->searchable()->preload(),
            Filter::make('payment_date')->schema([
                DatePicker::make('from')->label(__('Paid from')),
                DatePicker::make('to')->label(__('Paid to'))->afterOrEqual('from'),
            ])->query(fn (Builder $query, array $data): Builder => $query
                ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('payment_date', '>=', $date))
                ->when($data['to'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('payment_date', '<=', $date))),
            TrashedFilter::make()->visible(fn (): bool => Filament::auth()->user()?->hasRole('hoa_admin') ?? false),
        ])->recordActions([
            Action::make('downloadProof')
                ->label(__('Proof'))
                ->url(fn (Payment $record): ?string => filled($record->proof_path) ? route('portal.payments.proof.download', $record) : null)
                ->openUrlInNewTab()
                ->visible(fn (Payment $record): bool => filled($record->proof_path)),
            Action::make('approveProof')
                ->label(__('Approve proof'))
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (Payment $record): bool => $record->review_status === 'Pending')
                ->action(function (Payment $record): void {
                    $actor = app(AuthenticatedActor::class)->user();
                    abort_unless($actor instanceof User, 403);
                    app(ReviewPaymentProof::class)->approve($record, $actor);
                }),
            Action::make('rejectProof')
                ->label(__('Reject proof'))
                ->color('danger')
                ->requiresConfirmation()
                ->schema([
                    Textarea::make('review_notes')->label(__('Rejection reason'))->required()->maxLength(1000),
                ])
                ->visible(fn (Payment $record): bool => $record->review_status === 'Pending')
                ->action(function (Payment $record, array $data): void {
                    $actor = app(AuthenticatedActor::class)->user();
                    abort_unless($actor instanceof User, 403);
                    app(ReviewPaymentProof::class)->reject($record, $actor, $data['review_notes']);
                }),
            ...ReceiptActions::make(),
            EditAction::make(),
            Action::make('deletePayment')
                ->label(__('Delete'))->icon('heroicon-o-trash')->color('danger')->requiresConfirmation()
                ->visible(fn (Payment $record): bool => ! $record->trashed() && (Filament::auth()->user()?->hasRole('hoa_admin') ?? false))
                ->action(function (Payment $record): void {
                    $actor = app(AuthenticatedActor::class)->user();
                    abort_unless($actor instanceof User, 403);
                    app(ChangePaymentDeletionState::class)->delete($record, $actor);
                }),
            Action::make('restorePayment')
                ->label(__('Restore'))->icon('heroicon-o-arrow-uturn-left')->requiresConfirmation()
                ->visible(fn (Payment $record): bool => $record->trashed() && (Filament::auth()->user()?->hasRole('hoa_admin') ?? false))
                ->action(function (Payment $record): void {
                    $actor = app(AuthenticatedActor::class)->user();
                    abort_unless($actor instanceof User, 403);
                    app(ChangePaymentDeletionState::class)->restore($record, $actor);
                }),
        ])->toolbarActions([
            BulkActionGroup::make([
                DeleteBulkAction::make()->using(function (Collection $records): void {
                    $actor = app(AuthenticatedActor::class)->user();
                    abort_unless($actor instanceof User, 403);
                    foreach ($records as $record) {
                        app(ChangePaymentDeletionState::class)->delete($record, $actor);
                    }
                }),
                RestoreBulkAction::make()->using(function (Collection $records): void {
                    $actor = app(AuthenticatedActor::class)->user();
                    abort_unless($actor instanceof User, 403);
                    foreach ($records as $record) {
                        app(ChangePaymentDeletionState::class)->restore($record, $actor);
                    }
                }),
            ])
                ->visible(fn (): bool => Filament::getCurrentPanel()?->getId() === 'admin'),
        ]);
    }
}
