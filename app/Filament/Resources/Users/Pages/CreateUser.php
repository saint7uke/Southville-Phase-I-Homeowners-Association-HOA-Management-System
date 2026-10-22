<?php

namespace App\Filament\Resources\Users\Pages;

use App\Actions\Users\CreatePendingUser;
use App\Actions\Users\InviteManagedUser;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Support\AuthenticatedActor;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        $actor = app(AuthenticatedActor::class)->user();

        if (! $actor instanceof User) {
            throw new AuthorizationException;
        }

        $invite = (bool) ($data['send_invitation'] ?? false);
        unset($data['send_invitation']);

        return $invite
            ? app(InviteManagedUser::class)->handle($data, $actor)
            : app(CreatePendingUser::class)->handle($data, $actor);
    }
}
