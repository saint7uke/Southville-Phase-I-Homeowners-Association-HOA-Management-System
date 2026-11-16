<?php

namespace App\Filament\Resources\Homeowners\Pages;

use App\Actions\Homeowners\ConfirmPropertyPhase;
use App\Filament\Concerns\HasIconOnlyRecordActions;
use App\Filament\Concerns\HasSpecificFeedback;
use App\Filament\Concerns\MapsDomainValidation;
use App\Filament\Resources\Homeowners\HomeownerResource;
use App\Services\PropertyAddressAvailability;
use App\Support\AuthenticatedActor;
use App\Support\PropertyIdentity;
use App\Support\PropertyPhase;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class EditHomeowner extends EditRecord
{
    use HasIconOnlyRecordActions;
    use HasSpecificFeedback;
    use MapsDomainValidation;

    protected static string $resource = HomeownerResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['user_email'] = $this->record->user->email;
        $data['user_contact_number'] = $this->record->user->contact_number;

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $userData = [
            'email' => $data['user_email'],
            'contact_number' => $data['user_contact_number'],
        ];
        unset($data['user_email'], $data['user_contact_number']);
        unset($data['street'], $data['house_number'], $data['phase'], $data['phase_suffix'], $data['property_key']);

        if (Filament::getCurrentPanel()?->getId() === 'staff') {
            $data = Arr::only($data, [
                'emergency_contact_name',
                'emergency_contact_number',
                'landlord_homeowner_id',
                'landlord_name',
                'landlord_contact',
                'lease_start_date',
                'lease_end_date',
            ]);
        }

        $address = [...$record->getAttributes(), ...$data];
        $checkAvailability = ! PropertyIdentity::same($address, $record) || ($address['ownership_type'] ?? null) !== $record->ownership_type;

        return $this->runValidatedDomainAction(fn (): Model => app(PropertyAddressAvailability::class)->withAddressLock($address, $record->id, function () use ($record, $data, $userData): Model {
            $record = $record->newQuery()->lockForUpdate()->findOrFail($record->id);
            $record->user->update($userData);
            $record->update($data);

            return $record->refresh();
        }, assertAvailable: $checkAvailability), 'data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('confirmPropertyPhase')->label(__('Confirm property phase'))
                ->visible(fn (): bool => ! $this->record->trashed() && app(AuthenticatedActor::class)->user()?->can('manage_homeowners'))
                ->schema([Select::make('phase_suffix')->label(__('Phase'))->options(PropertyPhase::options())->required()])
                ->requiresConfirmation()
                ->action(function (array $data): void {
                    $this->runValidatedDomainAction(fn () => app(ConfirmPropertyPhase::class)->handle($this->record, $data['phase_suffix'], app(AuthenticatedActor::class)->user()));
                    $this->refreshFormData(['phase_suffix']);
                    Notification::make()->success()->title(__('Homeowner Property Phase Confirmed Successfully!'))->send();
                }),
            DeleteAction::make()->visible(fn (): bool => Filament::getCurrentPanel()?->getId() === 'admin'),
            RestoreAction::make()->visible(fn (): bool => Filament::getCurrentPanel()?->getId() === 'admin'),
        ];
    }
}
