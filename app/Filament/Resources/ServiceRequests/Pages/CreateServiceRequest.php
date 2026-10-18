<?php

namespace App\Filament\Resources\ServiceRequests\Pages;

use App\Actions\ServiceRequests\CreateServiceRequest as CreateServiceRequestAction;
use App\Filament\Resources\ServiceRequests\ServiceRequestResource;
use App\Models\Homeowner;
use App\Models\User;
use App\Support\AuthenticatedActor;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateServiceRequest extends CreateRecord
{
    protected static string $resource = ServiceRequestResource::class;

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->requiresConfirmation()
            ->modalHeading('Review and create service request')
            ->modalDescription('Confirm the resident, request details, assignment, and attachments before creating this service request.');
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        $actor = app(AuthenticatedActor::class)->user();
        if (! $actor instanceof User) {
            throw new AuthorizationException;
        }

        $homeowner = Homeowner::withTrashed()->find($data['homeowner_id'] ?? null);
        if (! $homeowner instanceof Homeowner) {
            throw ValidationException::withMessages(['homeowner_id' => 'Select a current resident.']);
        }

        $attachments = $data['attachments'] ?? [];
        unset($data['homeowner_id'], $data['attachments'], $data['initial_status']);

        try {
            return app(CreateServiceRequestAction::class)->handle($homeowner, $data, $attachments, $actor);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()
                ->danger()
                ->title('Service request could not be created')
                ->body('Nothing was saved. Please review the form and try again.')
                ->send();
            $this->halt(shouldRollbackDatabaseTransaction: true);
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Service request created successfully';
    }
}
