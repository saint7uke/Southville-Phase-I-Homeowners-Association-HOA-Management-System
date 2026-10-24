<?php

declare(strict_types=1);

namespace App\Actions\ContactMessages;

use App\Models\ContactMessage;
use App\Models\ContactMessageActivity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class AddContactMessageNote
{
    public function handle(ContactMessage $message, string $body, User $actor): ContactMessageActivity
    {
        Gate::forUser($actor)->authorize('update', $message);
        $body = Validator::make(['body' => $body], ['body' => ['required', 'string', 'max:5000']])->validate()['body'];

        return DB::transaction(function () use ($message, $body, $actor): ContactMessageActivity {
            $current = ContactMessage::query()->lockForUpdate()->findOrFail($message->id);
            Gate::forUser($actor)->authorize('update', $current);
            if ($current->status === 'Closed') {
                throw ValidationException::withMessages(['body' => __('Reopen this inquiry before adding a note.')]);
            }
            $now = now();
            $current->forceFill([
                'first_handled_by' => $current->first_handled_by ?? $actor->id,
                'first_handled_at' => $current->first_handled_at ?? $now,
                'handled_by' => $actor->id,
                'handled_at' => $now,
            ])->save();

            return $current->activities()->create(['author_id' => $actor->id, 'type' => 'internal_note', 'body' => $body]);
        });
    }
}
