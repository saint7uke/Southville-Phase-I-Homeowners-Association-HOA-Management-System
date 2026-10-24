<?php

declare(strict_types=1);

namespace App\Actions\ContactMessages;

use App\Jobs\SendContactMessageReply as SendContactMessageReplyJob;
use App\Models\ContactMessage;
use App\Models\ContactMessageActivity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class SendContactMessageReply
{
    public function handle(ContactMessage $message, array $data, User $actor): ContactMessageActivity
    {
        Gate::forUser($actor)->authorize('update', $message);
        $validated = Validator::make($data, [
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:10000'],
            'idempotency_token' => ['required', 'uuid'],
        ])->validate();

        return DB::transaction(function () use ($message, $validated, $actor): ContactMessageActivity {
            if ($existing = ContactMessageActivity::query()->where('idempotency_token', $validated['idempotency_token'])->first()) {
                if ($existing->contact_message_id === $message->id && $existing->author_id === $actor->id) {
                    return $existing;
                }
                throw ValidationException::withMessages(['body' => __('This reply submission token has already been used.')]);
            }

            $current = ContactMessage::query()->lockForUpdate()->findOrFail($message->id);
            Gate::forUser($actor)->authorize('update', $current);
            if ($current->status === 'Closed') {
                throw ValidationException::withMessages(['body' => __('Reopen this inquiry before sending another reply.')]);
            }

            $now = now();
            $current->forceFill([
                'status' => $current->status === 'New' ? 'In Progress' : $current->status,
                'first_handled_by' => $current->first_handled_by ?? $actor->id,
                'first_handled_at' => $current->first_handled_at ?? $now,
                'handled_by' => $actor->id,
                'handled_at' => $now,
            ])->save();
            $activity = $current->activities()->create([
                'author_id' => $actor->id,
                'type' => 'outbound_reply',
                'recipient_email' => $current->email,
                'subject' => $validated['subject'],
                'body' => $validated['body'],
                'idempotency_token' => $validated['idempotency_token'],
                'delivery_status' => 'Queued',
            ]);
            SendContactMessageReplyJob::dispatch($activity->id)->afterCommit();

            return $activity;
        });
    }
}
