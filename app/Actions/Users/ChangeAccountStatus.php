<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Enums\UserAccountStatus;
use App\Events\UserAccountStatusChanged;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ChangeAccountStatus
{
    /** @var array<string, list<UserAccountStatus>> */
    private const ALLOWED_TRANSITIONS = [
        'Pending' => [UserAccountStatus::Active, UserAccountStatus::Rejected],
        'Active' => [UserAccountStatus::Inactive, UserAccountStatus::Suspended],
        'Inactive' => [UserAccountStatus::Active],
        'Suspended' => [UserAccountStatus::Active],
        'Rejected' => [],
    ];

    public function handle(User $user, UserAccountStatus $to, User $actor, ?string $reason = null): User
    {
        Gate::forUser($actor)->authorize('update', $user);

        $reason = filled($reason) ? trim((string) $reason) : null;

        if ($actor->is($user) && $to !== UserAccountStatus::Active) {
            throw ValidationException::withMessages([
                'account_status' => 'You cannot deactivate or suspend your own account.',
            ]);
        }

        if ($to === UserAccountStatus::Rejected && $reason === null) {
            throw ValidationException::withMessages(['rejection_reason' => 'A rejection reason is required.']);
        }

        if ($to === UserAccountStatus::Suspended && $reason === null) {
            throw ValidationException::withMessages(['suspension_reason' => 'A suspension reason is required.']);
        }

        $updated = DB::transaction(function () use ($user, $actor, $to, $reason): User {
            $activeAdmins = collect();
            if ($to !== UserAccountStatus::Active) {
                $activeAdmins = User::query()
                    ->role('hoa_admin')
                    ->where('account_status', UserAccountStatus::Active->value)
                    ->orderBy('users.id')
                    ->lockForUpdate()
                    ->get();
            }

            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $from = UserAccountStatus::tryFrom((string) $lockedUser->account_status);

            if ($from === null || ! in_array($to, self::ALLOWED_TRANSITIONS[$from->value] ?? [], true)) {
                throw ValidationException::withMessages([
                    'account_status' => "Account status cannot move from {$lockedUser->account_status} to {$to->value}.",
                ]);
            }

            if ($lockedUser->hasRole('hoa_admin') && $to !== UserAccountStatus::Active && $activeAdmins->count() <= 1) {
                throw ValidationException::withMessages([
                    'account_status' => 'At least one active HOA administrator is required.',
                ]);
            }

            $attributes = [
                'account_status' => $to->value,
                'rejection_reason' => $to === UserAccountStatus::Rejected ? $reason : null,
                'suspended_at' => $to === UserAccountStatus::Suspended ? now() : null,
                'suspended_by' => $to === UserAccountStatus::Suspended ? $actor->id : null,
                'suspension_reason' => $to === UserAccountStatus::Suspended ? $reason : null,
            ];

            if ($to === UserAccountStatus::Active) {
                $attributes['approved_at'] = $lockedUser->approved_at ?? now();
                $attributes['approved_by'] = $lockedUser->approved_by ?? $actor->id;
            }

            $lockedUser->forceFill($attributes);
            if ($to !== UserAccountStatus::Active) {
                $lockedUser->setRememberToken(Str::random(60));
            }
            $lockedUser->save();

            if ($lockedUser->homeowner !== null && $lockedUser->homeowner->status !== 'Delinquent') {
                $lockedUser->homeowner->update([
                    'status' => $to === UserAccountStatus::Active ? 'Active' : 'Inactive',
                ]);
            }

            if ($to !== UserAccountStatus::Active) {
                DB::table((string) config('session.table', 'sessions'))
                    ->where('user_id', $lockedUser->id)
                    ->delete();
            }

            UserAccountStatusChanged::dispatch($lockedUser, $from, $to, $reason);

            return $lockedUser->refresh();
        });

        return $updated;
    }
}
