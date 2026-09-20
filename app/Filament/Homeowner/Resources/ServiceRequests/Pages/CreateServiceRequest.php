<?php

declare(strict_types=1);

namespace App\Filament\Homeowner\Resources\ServiceRequests\Pages;

use App\Actions\ServiceRequests\CreateServiceRequest as CreateServiceRequestAction;
use App\Filament\Homeowner\Resources\ServiceRequests\ServiceRequestResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateServiceRequest extends CreateRecord
{
    protected static string $resource = ServiceRequestResource::class;

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->requiresConfirmation()
            ->modalHeading('Review and submit request')
            ->modalDescription('Please review the request type, subject, description, and attachments before confirming.');
    }

    protected function handleRecordCreation(array $data): Model
    {
        $actor = Filament::auth()->user();
        abort_unless($actor instanceof User, 403);
        $homeowner = $actor->homeowner()->firstOrFail();
        $attachments = $data['attachments'] ?? [];
        unset($data['attachments']);

        return app(CreateServiceRequestAction::class)->handle($homeowner, $data, $attachments, $actor);
    }
}
