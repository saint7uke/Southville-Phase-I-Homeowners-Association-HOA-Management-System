<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Actions\Payments\RecordPayment;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\User;
use App\Support\AuthenticatedActor;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

class CreatePayment extends CreateRecord
{
    protected static string $resource = PaymentResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        $actor = app(AuthenticatedActor::class)->user();
        if (! $actor instanceof User) {
            throw new AuthorizationException;
        }

        return app(RecordPayment::class)->handle($data, $actor);
    }
}
