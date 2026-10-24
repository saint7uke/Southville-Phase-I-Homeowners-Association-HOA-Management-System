<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use LogicException;

final class ContactMessage extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUSES = ['New', 'In Progress', 'Awaiting Resident', 'Closed'];

    protected $fillable = ['name', 'email', 'phone', 'subject', 'message', 'status', 'first_handled_by', 'first_handled_at', 'handled_by', 'handled_at', 'closed_at', 'ip_address'];

    protected function casts(): array
    {
        return ['first_handled_at' => 'datetime', 'handled_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by')->withTrashed();
    }

    public function firstHandler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'first_handled_by')->withTrashed();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ContactMessageActivity::class)->oldest();
    }

    public function lastActivity(): HasOne
    {
        return $this->hasOne(ContactMessageActivity::class)->latestOfMany();
    }

    protected static function booted(): void
    {
        self::creating(function (self $message): void {
            $message->reference ??= 'INQ-'.Str::upper(Str::random(12));
        });
        self::updating(function (self $message): void {
            if ($message->isDirty('reference')) {
                throw new LogicException('Inquiry references are immutable.');
            }
        });
    }
}
