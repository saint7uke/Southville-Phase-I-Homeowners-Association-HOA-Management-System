<?php

declare(strict_types=1);

namespace App\Actions\ContactMessages;

use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ReopenContactMessage
{
    public function handle(ContactMessage $message, string $reason, User $actor): ContactMessage
    {
        Gate::forUser($actor)->authorize('reopen', $message);
        $reason = Validator::make(['reason' => $reason], ['reason' => ['required', 'string', 'min:10', 'max:2000']])->validate()['reason'];

        return DB::transaction(function () use ($message, $reason, $actor): ContactMessage {
            $current = ContactMessage::query()->lockForUpdate()->findOrFail($message->id);
            Gate::forUser($actor)->authorize('reopen', $current);
            if ($current->status !== 'Closed') {
                throw ValidationException::withMessages(['reason' => __('Only a closed inquiry can be reopened.')]);
            }
            $current->forceFill(['status' => 'In Progress', 'handled_by' => $actor->id, 'handled_at' => now(), 'closed_at' => null])->save();
            $current->activities()->create(['author_id' => $actor->id, 'type' => 'reopen_reason', 'body' => $reason]);

            return $current->refresh();
        });
    }
}
