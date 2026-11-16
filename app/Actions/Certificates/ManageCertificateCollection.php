<?php

declare(strict_types=1);

namespace App\Actions\Certificates;

use App\Models\Certificate;
use App\Models\User;
use App\Support\OperationalAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ManageCertificateCollection
{
    public function handle(int $id, string $operation, User $actor): Certificate
    {
        OperationalAccess::authorize($actor, 'manage_requests');

        return DB::transaction(function () use ($id, $operation, $actor): Certificate {
            $record = Certificate::lockForUpdate()->findOrFail($id);
            Gate::forUser($actor)->authorize('update', $record);
            if ($record->status !== 'Issued' || ($record->expires_at !== null && $record->expires_at->isPast())) {
                throw ValidationException::withMessages(['status' => __('Only a valid issued certificate can be prepared for collection.')]);
            }
            if ($operation === 'ready' && $record->ready_for_pickup_at === null && $record->collected_at === null) {
                $record->forceFill(['ready_for_pickup_at' => now(), 'ready_for_pickup_by' => $actor->id])->save();
            } elseif ($operation === 'collect' && $record->ready_for_pickup_at !== null && $record->collected_at === null) {
                $record->forceFill(['collected_at' => now(), 'collected_by' => $actor->id])->save();
            } else {
                throw ValidationException::withMessages(['status' => __('The collection status changed. Refresh the record before continuing.')]);
            }

            return $record;
        });
    }
}
