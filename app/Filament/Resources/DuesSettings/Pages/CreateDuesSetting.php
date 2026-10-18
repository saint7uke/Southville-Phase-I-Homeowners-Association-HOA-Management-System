<?php

namespace App\Filament\Resources\DuesSettings\Pages;

use App\Actions\Dues\SaveDuesSetting;
use App\Filament\Resources\DuesSettings\DuesSettingResource;
use App\Models\User;
use App\Support\AuthenticatedActor;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

class CreateDuesSetting extends CreateRecord
{
    protected static string $resource = DuesSettingResource::class;

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->requiresConfirmation()
            ->modalHeading('Create dues schedule?')
            ->modalDescription('Review the amount and effective billing period. Financial terms become locked after obligations are generated.');
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        $actor = app(AuthenticatedActor::class)->user();
        if (! $actor instanceof User) {
            throw new AuthorizationException;
        }

        return app(SaveDuesSetting::class)->create($data, $actor);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Dues schedule created successfully';
    }
}
