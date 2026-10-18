<?php

declare(strict_types=1);

namespace App\Filament\Resources\Complaints\Pages;

use App\Actions\Complaints\CreateComplaint as CreateComplaintAction;
use App\Filament\Resources\Complaints\ComplaintResource;
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

class CreateComplaint extends CreateRecord
{
    protected static string $resource = ComplaintResource::class;

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->requiresConfirmation()
            ->modalHeading('Review and create complaint')
            ->modalDescription('Confirm the resident, complaint details, priority, assignment, and attachments before creating this complaint.');
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        $actor = app(AuthenticatedActor::class)->user();
        if (! $actor instanceof User) {
            throw new AuthorizationException;
        }

        $homeowner = Homeowner::query()->find($data['homeowner_id'] ?? null);
        if (! $homeowner instanceof Homeowner) {
            throw ValidationException::withMessages(['homeowner_id' => 'Select a current resident.']);
        }

        $attachments = $data['attachments'] ?? [];
        unset($data['homeowner_id'], $data['attachments'], $data['initial_status']);

        try {
            return app(CreateComplaintAction::class)->handle($homeowner, $data, $attachments, $actor);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()
                ->danger()
                ->title('Complaint could not be created')
                ->body('Nothing was saved. Please review the form and try again.')
                ->send();
            $this->halt(shouldRollbackDatabaseTransaction: true);
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Complaint created successfully';
    }
}
