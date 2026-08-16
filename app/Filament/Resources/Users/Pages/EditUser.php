<?php

namespace App\Filament\Resources\Users\Pages;

use App\Actions\Users\ChangeAccountStatus;
use App\Enums\UserAccountStatus;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Support\AuthenticatedActor;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof User) {
            return parent::handleRecordUpdate($record, $data);
        }

        $requestedStatus = UserAccountStatus::from((string) $data['account_status']);
        $reason = $requestedStatus === UserAccountStatus::Suspended
            ? ($data['suspension_reason'] ?? null)
            : ($data['rejection_reason'] ?? null);
        unset($data['account_status'], $data['rejection_reason'], $data['suspension_reason']);

        $actor = app(AuthenticatedActor::class)->user();

        if ($actor === null) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($record, $data, $requestedStatus, $reason, $actor): User {
            $record->update($data);

            if ($record->account_status !== $requestedStatus->value) {
                app(ChangeAccountStatus::class)->handle($record, $requestedStatus, $actor, is_string($reason) ? $reason : null);
            }

            return $record->refresh();
        });
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
