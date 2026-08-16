<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\AuditLog;
use App\Support\AuthenticatedActor;
use Illuminate\Database\Eloquent\Model;

final class AuditObserver
{
    public function __construct(private readonly AuthenticatedActor $actor) {}

    public function created(Model $model): void
    {
        $this->write('created', $model, null, $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $this->write('updated', $model, $model->getOriginal(), $model->getChanges());
    }

    public function deleted(Model $model): void
    {
        $this->write('deleted', $model, $model->getOriginal(), null);
    }

    public function restored(Model $model): void
    {
        $this->write('restored', $model, null, $model->getAttributes());
    }

    /** @param array<string, mixed>|null $old @param array<string, mixed>|null $new */
    private function write(string $action, Model $model, ?array $old, ?array $new): void
    {
        $redact = static fn (?array $values): ?array => $values === null ? null : collect($values)->except(['password', 'remember_token'])->all();
        AuditLog::query()->create([
            'user_id' => $this->actor->id(), 'action' => class_basename($model).'.'.$action,
            'auditable_type' => $model::class, 'auditable_id' => $model->getKey(),
            'old_values' => $redact($old), 'new_values' => $redact($new),
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
            'user_agent' => app()->runningInConsole() ? null : mb_substr((string) request()->userAgent(), 0, 500),
        ]);
    }
}
