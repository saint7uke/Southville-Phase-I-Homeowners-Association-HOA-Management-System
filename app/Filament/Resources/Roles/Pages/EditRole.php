<?php

declare(strict_types=1);

namespace App\Filament\Resources\Roles\Pages;

use App\Actions\Roles\UpdateRolePermissions;
use App\Filament\Concerns\HasSpecificFeedback;
use App\Filament\Resources\Roles\RoleResource;
use App\Models\Role;
use App\Models\User;
use App\Support\AuthenticatedActor;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

final class EditRole extends EditRecord
{
    use HasSpecificFeedback;

    protected static string $resource = RoleResource::class;

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()->requiresConfirmation()->modalHeading(__('Confirm permission changes'));
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $actor = app(AuthenticatedActor::class)->user();
        if (! $record instanceof Role || ! $actor instanceof User) {
            throw new AuthorizationException;
        }

        return app(UpdateRolePermissions::class)->handle($record, $data['permission_names'] ?? [], $actor);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['permission_names'] = $this->record->permissions()->pluck('name')->all();

        return $data;
    }
}
