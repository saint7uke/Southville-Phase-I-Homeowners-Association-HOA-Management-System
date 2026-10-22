<?php

declare(strict_types=1);

namespace App\Actions\Dues;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\AuthenticatedActor;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

final class GenerateCurrentMonthDues
{
    public function handle(User $actor): int
    {
        if ($actor->trashed() || $actor->account_status !== 'Active'
            || ! $actor->hasAnyRole(['hoa_admin', 'hoa_staff']) || ! $actor->can('manage_dues')) {
            throw new AuthorizationException;
        }

        $lock = Cache::lock('dues-generation:'.today()->format('Y-m'), 600);
        if (! $lock->get()) {
            throw ValidationException::withMessages(['dues_obligation_id' => __('Dues generation is already running. Please refresh the list shortly.')]);
        }

        try {
            $created = app(GenerateMonthlyObligations::class)->handle();
            AuditLog::query()->create([
                'user_id' => $actor->id,
                'panel' => app(AuthenticatedActor::class)->panel(),
                'action' => 'Dues.current_month_generated',
                'new_values' => ['billing_period' => today()->format('Y-m'), 'created' => $created],
                'ip_address' => app()->runningInConsole() ? null : request()->ip(),
            ]);

            return $created;
        } finally {
            $lock->release();
        }
    }
}
