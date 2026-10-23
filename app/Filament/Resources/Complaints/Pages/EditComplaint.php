<?php

namespace App\Filament\Resources\Complaints\Pages;

use App\Actions\Complaints\UpdateComplaint;
use App\Filament\Concerns\MapsDomainValidation;
use App\Filament\Resources\Complaints\ComplaintResource;
use App\Support\AuthenticatedActor;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditComplaint extends EditRecord
{
    use MapsDomainValidation;

    protected static string $resource = ComplaintResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        // Disabled controls are not dehydrated. Include raw state for forgery checks;
        // the action allowlists writes against the freshly locked complaint.
        $data += array_intersect_key($this->data ?? [], array_flip(['homeowner_id', 'subject', 'category', 'description', 'handled_by']));
        $this->runValidatedDomainAction(fn () => app(UpdateComplaint::class)->handle($record, $data, app(AuthenticatedActor::class)->user()), 'data');

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
