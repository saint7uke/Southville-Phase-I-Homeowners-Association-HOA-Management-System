<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Actions\Payments\RecordPayment;
use App\Filament\Concerns\HasSpecificFeedback;
use App\Filament\Resources\Payments\Actions\GenerateCurrentMonthDuesAction;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\Payments\Schemas\PaymentPayerSelect;
use App\Models\User;
use App\Support\AuthenticatedActor;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreatePayment extends CreateRecord
{
    use HasSpecificFeedback;

    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [GenerateCurrentMonthDuesAction::make()];
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->requiresConfirmation()
            ->modalHeading(__('Record this payment?'))
            ->modalDescription(__('Confirm the obligation, payer, amount, method, and reference before updating the property ledger.'));
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        $actor = app(AuthenticatedActor::class)->user();
        if (! $actor instanceof User) {
            throw new AuthorizationException;
        }

        try {
            return app(RecordPayment::class)->handle($data, $actor);
        } catch (ValidationException $exception) {
            if (isset($exception->errors()['payer_homeowner_id'])) {
                /** @var PaymentPayerSelect $payer */
                $payer = $this->form->getFlatFields()['payer_homeowner_id'];
                $payer->rejectSelection();
            }

            throw $exception;
        }
    }
}
