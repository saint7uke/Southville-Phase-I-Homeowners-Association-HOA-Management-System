<?php

namespace App\Filament\Resources\ServiceRequests\Pages;

use App\Actions\ServiceRequests\UpdateServiceRequest;
use App\Filament\Concerns\MapsDomainValidation;
use App\Filament\Resources\ServiceRequests\ServiceRequestResource;
use App\Support\AuthenticatedActor;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditServiceRequest extends EditRecord
{
    use MapsDomainValidation;

    protected static string $resource = ServiceRequestResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $this->runValidatedDomainAction(fn () => app(UpdateServiceRequest::class)->handle($record, $data, app(AuthenticatedActor::class)->user()), 'data');

        // The action updates a separately locked instance; refresh the instance bound to the form.
        return $record->refresh();
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->visible(fn (): bool => Filament::getCurrentPanel()?->getId() === 'admin'),
            RestoreAction::make()->visible(fn (): bool => Filament::getCurrentPanel()?->getId() === 'admin'),
        ];
    }
}
