<?php

namespace App\Filament\Resources\Homeowners\Pages;

use App\Actions\Homeowners\ConfirmPropertyPhase;
use App\Filament\Concerns\MapsDomainValidation;
use App\Filament\Resources\Homeowners\HomeownerResource;
use App\Services\PropertyAddressAvailability;
use App\Support\AuthenticatedActor;
use App\Support\PropertyIdentity;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class EditHomeowner extends EditRecord
{
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
        unset($data['phase_suffix'], $data['property_key']);

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

        return app(PropertyAddressAvailability::class)->withAddressLock($address, $record->id, function () use ($record, $data, $userData): Model {
            $record = $record->newQuery()->lockForUpdate()->findOrFail($record->id);
            $record->user->update($userData);
            $record->update($data);

            return $record->refresh();
        }, assertAvailable: $checkAvailability);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('confirmPropertyPhase')->label(__('Confirm property phase'))
                ->visible(fn (): bool => ! $this->record->trashed() && app(AuthenticatedActor::class)->user()?->can('manage_homeowners'))
                ->schema([TextInput::make('phase_suffix')->label(__('Phase suffix'))->prefix('Phase 1 -')->required()->maxLength(10)->regex('/^[A-Za-z0-9]{1,10}$/D')])
                ->requiresConfirmation()
                ->action(function (array $data): void {
                    $this->runValidatedDomainAction(fn () => app(ConfirmPropertyPhase::class)->handle($this->record, $data['phase_suffix'], app(AuthenticatedActor::class)->user()));
                    $this->refreshFormData(['phase_suffix']);
                    Notification::make()->success()->title(__('Property phase confirmed.'))->send();
                }),
            DeleteAction::make()->visible(fn (): bool => Filament::getCurrentPanel()?->getId() === 'admin'),
            RestoreAction::make()->visible(fn (): bool => Filament::getCurrentPanel()?->getId() === 'admin'),
        ];
    }
}
