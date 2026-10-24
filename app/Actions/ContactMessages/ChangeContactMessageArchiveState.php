<?php

declare(strict_types=1);

namespace App\Actions\ContactMessages;

use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class ChangeContactMessageArchiveState
{
    public function archive(ContactMessage $message, User $actor): void
    {
        Gate::forUser($actor)->authorize('delete', $message);
        DB::transaction(function () use ($message, $actor): void {
            $current = ContactMessage::query()->lockForUpdate()->findOrFail($message->id);
            Gate::forUser($actor)->authorize('delete', $current);
            $current->delete();
        });
    }

    public function restore(ContactMessage $message, User $actor): void
    {
        Gate::forUser($actor)->authorize('restore', $message);
        DB::transaction(function () use ($message, $actor): void {
            $current = ContactMessage::withTrashed()->lockForUpdate()->findOrFail($message->id);
            Gate::forUser($actor)->authorize('restore', $current);
            $current->restore();
        });
    }
}
