<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class Complaint extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['homeowner_id', 'ticket_number', 'subject', 'description', 'attachment', 'attachment_name', 'category', 'priority', 'status', 'admin_remarks', 'handled_by', 'resolved_at'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function homeowner(): BelongsTo
    {
        return $this->belongsTo(Homeowner::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    protected static function booted(): void
    {
        self::creating(fn (Complaint $complaint) => $complaint->ticket_number ??= 'CMP-'.now()->format('Y').'-'.Str::upper(Str::random(8)));
        self::updating(function (Complaint $complaint): void {
            if (! $complaint->isDirty('status')) {
                return;
            }

            $allowed = ['Pending' => ['Under Review', 'Dismissed'], 'Under Review' => ['Resolved', 'Dismissed'], 'Resolved' => [], 'Dismissed' => []];
            $from = (string) $complaint->getOriginal('status');

            if (! in_array($complaint->status, $allowed[$from] ?? [], true)) {
                throw ValidationException::withMessages(['status' => "Status cannot move from {$from} to {$complaint->status}."]);
            }

            $complaint->handled_by ??= auth()->id();
            if ($complaint->status === 'Resolved') {
                $complaint->resolved_at ??= now();
            }
        });
    }
}
