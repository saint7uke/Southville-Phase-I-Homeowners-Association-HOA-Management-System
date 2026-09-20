<?php

namespace App\Filament\Resources\Certificates\Pages;

use App\Actions\Certificates\IssueCertificate;
use App\Filament\Resources\Certificates\CertificateResource;
use App\Models\User;
use App\Support\AuthenticatedActor;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

class CreateCertificate extends CreateRecord
{
    protected static string $resource = CertificateResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        $actor = app(AuthenticatedActor::class)->user();
        if (! $actor instanceof User) {
            throw new AuthorizationException;
        }

        return app(IssueCertificate::class)->handle($data, $actor);
    }
}
