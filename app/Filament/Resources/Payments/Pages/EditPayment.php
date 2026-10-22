<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Actions\Payments\ChangePaymentDeletionState;
use App\Actions\Payments\ConfirmReceiptDetails;
use App\Actions\Payments\UpdatePaymentMetadata;
use App\Filament\Concerns\MapsDomainValidation;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\User;
use App\Support\AuthenticatedActor;
use App\Support\OperationalAccess;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

class EditPayment extends EditRecord
{
    use MapsDomainValidation;

    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('confirmReceiptDetails')->label(__('Confirm legacy receipt details'))->requiresConfirmation()
                ->visible(fn (): bool => $this->record->property_snapshot === null && ! $this->record->trashed() && OperationalAccess::allows(app(AuthenticatedActor::class)->user(), 'manage_payments', true))
                ->modalDescription(fn (): string => __('Confirm that the current registered block/lot and the existing recorded collector match the original payment evidence. No payment amounts or ledger records will change.').' '.$this->record->homeowner?->full_address.' | '.$this->record->recorder?->full_name)
                ->schema([Toggle::make('confirmed')->label(__('I verified the original payment evidence and collector authorization.'))->accepted()->required(),
                    Textarea::make('note')->label(__('Review evidence / note'))->required()->minLength(20)->maxLength(2000)])
                ->action(function (array $data): void {
                    $this->runValidatedDomainAction(fn () => app(ConfirmReceiptDetails::class)->handle($this->record, $data, app(AuthenticatedActor::class)->user()));
                    $this->record->refresh();
                    Notification::make()->success()->title(__('Receipt details confirmed.'))->send();
                }),
            Action::make('deletePayment')->label(__('Delete'))->color('danger')->requiresConfirmation()
                ->visible(fn (): bool => ! $this->record->trashed() && Filament::getCurrentPanel()?->getId() === 'admin')
                ->action(function (): void {
                    $actor = app(AuthenticatedActor::class)->user();
                    abort_unless($actor instanceof User, 403);
                    app(ChangePaymentDeletionState::class)->delete($this->record, $actor);
                    $this->redirect(PaymentResource::getUrl());
                }),
            Action::make('restorePayment')->label(__('Restore'))->requiresConfirmation()
                ->visible(fn (): bool => $this->record->trashed() && Filament::getCurrentPanel()?->getId() === 'admin')
                ->action(function (): void {
                    $actor = app(AuthenticatedActor::class)->user();
                    abort_unless($actor instanceof User, 403);
                    app(ChangePaymentDeletionState::class)->restore($this->record, $actor);
                }),
        ];
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $actor = app(AuthenticatedActor::class)->user();
        if (! $actor instanceof User) {
            throw new AuthorizationException;
        }

        return app(UpdatePaymentMetadata::class)->handle($record, $data, $actor);
    }
}
