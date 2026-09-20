<?php

declare(strict_types=1);

namespace App\Filament\Resources\Payments\Tables;

use App\Actions\Payments\ReviewPaymentProof;
use App\Models\Payment;
use App\Models\User;
use App\Support\AuthenticatedActor;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('homeowner.user.full_name')->label('Homeowner')->searchable(['first_name', 'last_name']),
            TextColumn::make('homeowner.full_address')->label('Property')->wrap()->toggleable(),
            TextColumn::make('or_number')->label('OR number')->searchable()->copyable(),
            TextColumn::make('duesSetting.name')->label('Dues type'),
            TextColumn::make('duesObligation.amount_due')->label('Amount due')->money('PHP')->placeholder('Legacy'),
            TextColumn::make('amount_paid')->money('PHP')->sortable(),
            TextColumn::make('balance')->money('PHP')->sortable(),
            TextColumn::make('payment_date')->date()->sortable(),
            TextColumn::make('payment_method')->badge(),
            TextColumn::make('status')->badge(),
            TextColumn::make('recorder.full_name')->label('Recorded by')->toggleable(),
        ])->defaultSort('payment_date', 'desc')->filters([
            SelectFilter::make('status')->options(['Pending' => 'Pending', 'Paid' => 'Paid', 'Partial' => 'Partial', 'Overdue' => 'Overdue', 'Rejected' => 'Rejected']),
            SelectFilter::make('dues_setting_id')->label('Dues schedule')->relationship('duesSetting', 'name')->searchable()->preload(),
            Filter::make('payment_date')->schema([
                DatePicker::make('from')->label('Paid from'),
                DatePicker::make('to')->label('Paid to')->afterOrEqual('from'),
            ])->query(fn (Builder $query, array $data): Builder => $query
                ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('payment_date', '>=', $date))
                ->when($data['to'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('payment_date', '<=', $date))),
            TrashedFilter::make()->visible(fn (): bool => Filament::auth()->user()?->hasRole('hoa_admin') ?? false),
        ])->recordActions([
            Action::make('downloadProof')
                ->label('Proof')
                ->url(fn (Payment $record): ?string => filled($record->proof_path) ? route('portal.payments.proof.download', $record) : null)
                ->openUrlInNewTab()
                ->visible(fn (Payment $record): bool => filled($record->proof_path)),
            Action::make('approveProof')
                ->label('Approve proof')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (Payment $record): bool => $record->review_status === 'Pending')
                ->action(function (Payment $record): void {
                    $actor = app(AuthenticatedActor::class)->user();
                    abort_unless($actor instanceof User, 403);
                    app(ReviewPaymentProof::class)->approve($record, $actor);
                }),
            Action::make('rejectProof')
                ->label('Reject proof')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (Payment $record): bool => $record->review_status === 'Pending')
                ->action(function (Payment $record): void {
                    $actor = app(AuthenticatedActor::class)->user();
                    abort_unless($actor instanceof User, 403);
                    app(ReviewPaymentProof::class)->reject($record, $actor);
                }),
            Action::make('receipt')
                ->label('Receipt')
                ->icon('heroicon-o-document-arrow-down')
                ->url(fn (Payment $record): string => route('payments.receipt.download', $record))
                ->openUrlInNewTab()
                ->visible(fn (Payment $record): bool => in_array($record->review_status, ['Approved', 'Recorded'], true) && in_array($record->status, ['Paid', 'Partial'], true)),
            EditAction::make(),
            DeleteAction::make()->visible(fn (): bool => Filament::auth()->user()?->hasRole('hoa_admin') ?? false),
            RestoreAction::make()->visible(fn (): bool => Filament::auth()->user()?->hasRole('hoa_admin') ?? false),
        ])->toolbarActions([
            BulkActionGroup::make([DeleteBulkAction::make(), RestoreBulkAction::make()])
                ->visible(fn (): bool => Filament::getCurrentPanel()?->getId() === 'admin'),
        ]);
    }
}
