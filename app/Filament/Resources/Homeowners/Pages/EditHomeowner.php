<?php

namespace App\Filament\Resources\Homeowners\Pages;

use App\Filament\Resources\Homeowners\HomeownerResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class EditHomeowner extends EditRecord
{
    protected static string $resource = HomeownerResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['user_email'] = $this->record->user->email;
        $data['user_contact_number'] = $this->record->user->contact_number;

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $userData = [
            'email' => $data['user_email'],
            'contact_number' => $data['user_contact_number'],
        ];
        unset($data['user_email'], $data['user_contact_number']);

        if (Filament::getCurrentPanel()?->getId() === 'staff') {
            $data = Arr::only($data, ['emergency_contact_name', 'emergency_contact_number']);
        }

        return DB::transaction(function () use ($record, $data, $userData): Model {
            $record->user->update($userData);
            $record->update($data);

            return $record->refresh();
        });
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->visible(fn (): bool => Filament::getCurrentPanel()?->getId() === 'admin'),
            RestoreAction::make()->visible(fn (): bool => Filament::getCurrentPanel()?->getId() === 'admin'),
        ];
    }
}
