<?php

declare(strict_types=1);

namespace App\Actions\Roles;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Support\AuthenticatedActor;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class UpdateRolePermissions
{
    private const ADMIN_REQUIRED = ['manage_users', 'manage_roles', 'manage_settings', 'view_audit_logs'];

    private const STAFF_FORBIDDEN = ['manage_users', 'manage_roles', 'manage_settings', 'view_audit_logs', 'delete_homeowners', 'delete_payments'];

    private const HOMEOWNER_ALLOWED = ['submit_own_complaint', 'view_own_complaints', 'submit_own_request', 'view_own_requests', 'view_own_payments', 'view_own_certificates', 'view_published_announcements', 'update_own_profile', 'submit_own_payment_proof'];

    public function __construct(private readonly AuthenticatedActor $actor) {}

    /** @param array<int, string> $permissions */
    public function handle(Role $role, array $permissions, User $administrator): Role
    {
        Gate::forUser($administrator)->authorize('update', $role);
        $validated = Validator::make(['permissions' => $permissions], [
            'permissions' => ['array', 'max:100'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')->where('guard_name', 'web')],
        ])->validate()['permissions'];
        $permissions = array_values(array_unique($validated));

        if ($role->name === 'hoa_admin' && array_diff(self::ADMIN_REQUIRED, $permissions) !== []) {
            throw ValidationException::withMessages(['permission_names' => 'The administrator role must retain all core administration permissions.']);
        }
        if ($role->name === 'hoa_staff' && array_intersect(self::STAFF_FORBIDDEN, $permissions) !== []) {
            throw ValidationException::withMessages(['permission_names' => 'The staff role cannot receive administrator-only permissions.']);
        }
        if ($role->name === 'homeowner' && array_diff($permissions, self::HOMEOWNER_ALLOWED) !== []) {
            throw ValidationException::withMessages(['permission_names' => 'The homeowner role can receive only self-service permissions.']);
        }

        $old = $role->permissions()->pluck('name')->sort()->values()->all();
        $role->syncPermissions($permissions);
        AuditLog::query()->create([
            'user_id' => $administrator->id,
            'panel' => $this->actor->panel(),
            'action' => 'Role.permissions_changed',
            'auditable_type' => $role->getMorphClass(),
            'auditable_id' => $role->id,
            'old_values' => ['permissions' => $old],
            'new_values' => ['permissions' => collect($permissions)->sort()->values()->all()],
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
            'user_agent' => app()->runningInConsole() ? null : mb_substr((string) request()->userAgent(), 0, 500),
        ]);

        return $role->refresh();
    }
}
