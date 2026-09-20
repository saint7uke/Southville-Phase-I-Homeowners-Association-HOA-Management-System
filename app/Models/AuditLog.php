<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

final class AuditLog extends Model
{
    protected $fillable = ['user_id', 'panel', 'action', 'auditable_type', 'auditable_id', 'old_values', 'new_values', 'ip_address', 'user_agent'];

    protected function casts(): array
    {
        return ['old_values' => 'array', 'new_values' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('Audit log entries are immutable.'));
        self::deleting(fn () => throw new LogicException('Audit log entries are immutable.'));
    }
}
