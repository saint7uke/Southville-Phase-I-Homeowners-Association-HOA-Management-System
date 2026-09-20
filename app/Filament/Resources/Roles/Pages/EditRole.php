<?php

declare(strict_types=1);

namespace App\Filament\Resources\Roles\Pages;

use App\Actions\Roles\UpdateRolePermissions;
use App\Filament\Resources\Roles\RoleResource;
use App\Models\Role;
use App\Models\User;
use App\Support\AuthenticatedActor;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

final class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

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
