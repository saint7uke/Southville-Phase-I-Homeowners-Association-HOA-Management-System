<?php

namespace App\Filament\Resources\Users\Pages;

use App\Actions\Users\CreatePendingUser;
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

        return app(CreatePendingUser::class)->handle($data, $actor);
    }
}
