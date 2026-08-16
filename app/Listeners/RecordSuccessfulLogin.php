<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\PanelId;
use App\Enums\UserAccountStatus;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;

final class RecordSuccessfulLogin
{
    public function __construct(private readonly Request $request) {}

    public function handle(Login $event): void
    {
        $panel = PanelId::fromGuard($event->guard);

        if ($panel === null || ! $event->user instanceof User) {
            return;
        }

        if (! $this->canUseGuard($event->user, $event->guard)) {
            return;
        }

        $event->user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $this->request->ip(),
            'last_login_panel' => $panel->value,
        ])->saveQuietly();
    }

    private function canUseGuard(User $user, string $guard): bool
    {
        if ($guard === 'web') {
            return $user->hasRole('homeowner') && in_array($user->account_status, [
                UserAccountStatus::Active->value,
                UserAccountStatus::Pending->value,
                UserAccountStatus::Rejected->value,
            ], true);
        }

        $role = match ($guard) {
            'admin' => 'hoa_admin',
            'staff' => 'hoa_staff',
            'homeowner' => 'homeowner',
            default => null,
        };

        return $role !== null
            && $user->account_status === UserAccountStatus::Active->value
            && $user->hasRole($role);
    }
}
