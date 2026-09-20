<?php

namespace App\Filament\Resources\Certificates\Pages;

use App\Actions\Certificates\RevokeCertificate;
use App\Filament\Resources\Certificates\CertificateResource;
use App\Models\Certificate;
use App\Models\User;
use App\Support\AuthenticatedActor;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Auth\Access\AuthorizationException;

class EditCertificate extends EditRecord
{
    protected static string $resource = CertificateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('revoke')
                ->color('danger')
                ->requiresConfirmation()
                ->form([Textarea::make('reason')->required()->maxLength(500)])
                ->visible(fn (Certificate $record): bool => Filament::getCurrentPanel()?->getId() === 'admin' && $record->status === 'Issued')
                ->action(function (Certificate $record, array $data): void {
                    $actor = app(AuthenticatedActor::class)->user();
                    if (! $actor instanceof User) {
                        throw new AuthorizationException;
                    }
                    app(RevokeCertificate::class)->handle($record, $actor, $data['reason']);
                }),
            DeleteAction::make()->visible(fn (): bool => Filament::getCurrentPanel()?->getId() === 'admin'),
            RestoreAction::make()->visible(fn (): bool => Filament::getCurrentPanel()?->getId() === 'admin'),
        ];
    }
}
