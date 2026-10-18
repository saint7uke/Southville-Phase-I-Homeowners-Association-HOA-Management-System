<?php

namespace App\Filament\Resources\DuesSettings\Pages;

use App\Actions\Dues\SaveDuesSetting;
use App\Filament\Resources\DuesSettings\DuesSettingResource;
use App\Models\User;
use App\Support\AuthenticatedActor;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

class EditDuesSetting extends EditRecord
{
    protected static string $resource = DuesSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->visible(fn (): bool => Filament::getCurrentPanel()?->getId() === 'admin'),
        ];
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $actor = app(AuthenticatedActor::class)->user();
        if (! $actor instanceof User) {
            throw new AuthorizationException;
        }

        return app(SaveDuesSetting::class)->update($record, $data, $actor);
    }
}
