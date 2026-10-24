<?php

declare(strict_types=1);

namespace App\Actions\ContactMessages;

use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class UpdateContactMessageStatus
{
    private const TRANSITIONS = [
        'New' => ['In Progress'],
        'In Progress' => ['Awaiting Resident', 'Closed'],
        'Awaiting Resident' => ['In Progress', 'Closed'],
        'Closed' => [],
    ];

    public function handle(ContactMessage $message, string $status, User $actor, ?string $expectedUpdatedAt = null): ContactMessage
    {
        Gate::forUser($actor)->authorize('update', $message);
        Validator::make(['status' => $status], ['status' => ['required', Rule::in(ContactMessage::STATUSES)]])->validate();

        return DB::transaction(function () use ($message, $status, $actor, $expectedUpdatedAt): ContactMessage {
            $current = ContactMessage::query()->lockForUpdate()->findOrFail($message->id);
            Gate::forUser($actor)->authorize('update', $current);
            if ($expectedUpdatedAt !== null && $current->updated_at?->toISOString() !== $expectedUpdatedAt) {
                throw ValidationException::withMessages(['status' => __('This inquiry changed after you opened it. Reload before continuing.')]);
            }
            if (! in_array($status, self::TRANSITIONS[$current->status] ?? [], true)) {
                throw ValidationException::withMessages(['status' => __('The selected inquiry status transition is not allowed.')]);
            }

            $now = now();
            $current->forceFill([
                'status' => $status,
                'first_handled_by' => $current->first_handled_by ?? $actor->id,
                'first_handled_at' => $current->first_handled_at ?? $now,
                'handled_by' => $actor->id,
                'handled_at' => $now,
                'closed_at' => $status === 'Closed' ? $now : null,
            ])->save();

            return $current->refresh();
        });
    }
}
