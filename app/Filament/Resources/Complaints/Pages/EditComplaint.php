<?php

namespace App\Filament\Resources\Complaints\Pages;

use App\Filament\Resources\Complaints\ComplaintResource;
use App\Models\User;
use App\Support\AuthenticatedActor;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class EditComplaint extends EditRecord
{
    protected static string $resource = ComplaintResource::class;

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $actor = app(AuthenticatedActor::class)->user();
        if (! $actor instanceof User) {
            throw new AuthorizationException;
        }

        if ($actor->hasRole('hoa_admin')) {
            return $data;
        }

        $protectedFields = ['homeowner_id', 'subject', 'category', 'description', 'handled_by'];
        foreach ($protectedFields as $field) {
            if (array_key_exists($field, $this->data) && (string) $this->data[$field] !== (string) $this->record->getAttribute($field)) {
                throw ValidationException::withMessages(["data.{$field}" => 'Assigned staff cannot change the resident\'s original complaint or assignment.']);
            }
        }

        $allowedStatuses = match ($this->record->status) {
            'Pending' => ['Pending', 'Under Review'],
            'Under Review' => ['Under Review', 'Resolved'],
            default => [],
        };
        if (! in_array($data['status'] ?? $this->record->status, $allowedStatuses, true)) {
            throw ValidationException::withMessages(['data.status' => __('Assigned staff may only move a pending complaint to Under Review or resolve a complaint that is already under review.')]);
        }

        return collect($data)->only(['priority', 'status', 'admin_remarks'])->all();
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->visible(fn (): bool => Filament::getCurrentPanel()?->getId() === 'admin'),
            RestoreAction::make()->visible(fn (): bool => Filament::getCurrentPanel()?->getId() === 'admin'),
        ];
    }
}
