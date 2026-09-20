<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\AuthenticatedActor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ChangeUserRole
{
    public function __construct(private readonly AuthenticatedActor $actor) {}

    public function handle(User $user, string $role, User $administrator): User
    {
        Gate::forUser($administrator)->authorize('update', $user);
        Validator::make(['role' => $role], ['role' => ['required', Rule::in(['hoa_admin', 'hoa_staff', 'homeowner'])]])->validate();

        return DB::transaction(function () use ($user, $role, $administrator): User {
            $activeAdmins = User::query()->role('hoa_admin')->where('account_status', 'Active')->orderBy('users.id')->lockForUpdate()->get();
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            $oldRole = $locked->roles()->value('name');

            if ($oldRole === $role) {
                return $locked;
            }

            if ($locked->is($administrator)) {
                throw ValidationException::withMessages(['role' => 'You cannot change your own administrator role.']);
            }

            if ($oldRole === 'hoa_admin' && $locked->account_status === 'Active' && $activeAdmins->count() <= 1) {
                throw ValidationException::withMessages(['role' => 'At least one active HOA administrator is required.']);
            }

            if ($role === 'homeowner' && ! $locked->homeowner()->withTrashed()->exists()) {
                throw ValidationException::withMessages(['role' => 'Create a complete homeowner record before assigning the Homeowner role.']);
            }

            $locked->syncRoles([$role]);
            AuditLog::query()->create([
                'user_id' => $administrator->id,
                'panel' => $this->actor->panel(),
                'action' => 'User.role_changed',
                'auditable_type' => $locked->getMorphClass(),
                'auditable_id' => $locked->id,
                'old_values' => ['role' => $oldRole],
                'new_values' => ['role' => $role],
                'ip_address' => app()->runningInConsole() ? null : request()->ip(),
                'user_agent' => app()->runningInConsole() ? null : mb_substr((string) request()->userAgent(), 0, 500),
            ]);

            return $locked->refresh();
        });
    }
}
