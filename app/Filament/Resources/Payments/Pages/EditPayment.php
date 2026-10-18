<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Actions\Payments\ChangePaymentDeletionState;
use App\Actions\Payments\UpdatePaymentMetadata;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\User;
use App\Support\AuthenticatedActor;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

class EditPayment extends EditRecord
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('deletePayment')->label('Delete')->color('danger')->requiresConfirmation()
                ->visible(fn (): bool => ! $this->record->trashed() && Filament::getCurrentPanel()?->getId() === 'admin')
                ->action(function (): void {
                    $actor = app(AuthenticatedActor::class)->user();
                    abort_unless($actor instanceof User, 403);
                    app(ChangePaymentDeletionState::class)->delete($this->record, $actor);
                    $this->redirect(PaymentResource::getUrl());
                }),
            Action::make('restorePayment')->label('Restore')->requiresConfirmation()
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
