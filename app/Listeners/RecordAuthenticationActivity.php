<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\PanelId;
use App\Models\AuditLog;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class RecordAuthenticationActivity
{
    public function __construct(private readonly Request $request) {}

    public function handle(Login|Failed|Logout|PasswordReset $event): void
    {
        $guard = property_exists($event, 'guard')
            ? $event->guard
            : (Filament::getCurrentPanel()?->getAuthGuard() ?? (string) config('auth.defaults.guard', 'web'));
        $panel = PanelId::fromGuard($guard);
        $user = $event->user instanceof User ? $event->user : null;

        if ($event instanceof PasswordReset && $user !== null) {
            DB::transaction(function () use ($user): void {
                $user->forceFill([
                    'password_changed_at' => now(),
                    'remember_token' => Str::random(60),
                ])->saveQuietly();
                DB::table((string) config('session.table', 'sessions'))
                    ->where('user_id', $user->id)
                    ->delete();
            });
        }

        AuditLog::query()->create([
            'user_id' => $user?->id,
            'action' => match (true) {
                $event instanceof Login => 'Auth.login',
                $event instanceof Failed => 'Auth.failed',
                $event instanceof Logout => 'Auth.logout',
                default => 'Auth.password_reset',
            },
            'auditable_type' => $user?->getMorphClass(),
            'auditable_id' => $user?->id,
            'old_values' => null,
            'new_values' => ['panel' => $panel?->value ?? $guard],
            'ip_address' => $this->request->ip(),
            'user_agent' => mb_substr((string) $this->request->userAgent(), 0, 500),
        ]);
    }
}
