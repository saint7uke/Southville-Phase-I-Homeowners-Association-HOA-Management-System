<?php

declare(strict_types=1);

namespace App\Actions\Complaints;

use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\User;
use App\Support\AuthenticatedActor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

final class ReopenComplaint
{
    public function __construct(private readonly AuthenticatedActor $authenticatedActor) {}

    public function handle(Complaint $complaint, User $actor, string $reason): Complaint
    {
        Gate::forUser($actor)->authorize('reopen', $complaint);

        $validated = Validator::make(
            ['reason' => trim($reason)],
            ['reason' => ['required', 'string', 'min:10', 'max:1000']],
            [
                'reason.required' => 'Explain why this complaint needs to be reopened.',
                'reason.min' => 'The reopen reason must be at least 10 characters.',
                'reason.max' => 'The reopen reason may not exceed 1,000 characters.',
            ],
        )->validate();

        return DB::transaction(function () use ($complaint, $actor, $validated): Complaint {
            $lockedComplaint = Complaint::withTrashed()->lockForUpdate()->findOrFail($complaint->getKey());
            Gate::forUser($actor)->authorize('reopen', $lockedComplaint);

            $previousResolvedAt = $lockedComplaint->resolved_at?->toISOString();
            $lockedComplaint->reopen();

            AuditLog::query()->create([
                'user_id' => $actor->id,
                'panel' => $this->authenticatedActor->panel(),
                'action' => 'Complaint.reopened',
                'auditable_type' => Complaint::class,
                'auditable_id' => $lockedComplaint->id,
                'old_values' => [
                    'status' => 'Resolved',
                    'resolved_at' => $previousResolvedAt,
                ],
                'new_values' => [
                    'status' => 'Under Review',
                    'resolved_at' => null,
                    'reason' => $validated['reason'],
                ],
                'ip_address' => app()->runningInConsole() ? null : request()->ip(),
                'user_agent' => app()->runningInConsole() ? null : mb_substr((string) request()->userAgent(), 0, 500),
            ]);

            return $lockedComplaint->refresh();
        });
    }
}
