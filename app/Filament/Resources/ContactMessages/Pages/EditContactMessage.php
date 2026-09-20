<?php

namespace App\Filament\Resources\ContactMessages\Pages;

use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Models\ContactMessage;
use App\Models\User;
use App\Support\AuthenticatedActor;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditContactMessage extends EditRecord
{
    protected static string $resource = ContactMessageResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if ($record instanceof ContactMessage && $record->status !== $data['status']) {
            $actor = app(AuthenticatedActor::class)->user();
            if ($actor instanceof User) {
                $data['handled_by'] = $actor->id;
                $data['handled_at'] = now();
            }
        }

        return parent::handleRecordUpdate($record, $data);
    }
}
