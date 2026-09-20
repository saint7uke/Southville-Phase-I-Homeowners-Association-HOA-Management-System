<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\AuthenticatedActor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        return $this->belongsTo(Homeowner::class)->withTrashed();
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by')->withTrashed();
    }

    public function caseAttachments(): HasMany
    {
        return $this->hasMany(CaseAttachment::class);
    }

    protected static function booted(): void
    {
        self::creating(fn (Complaint $complaint) => $complaint->ticket_number ??= 'CMP-'.now()->format('Y').'-'.Str::upper(Str::random(8)));
        self::updating(function (Complaint $complaint): void {
            if (! $complaint->isDirty('status')) {
                return;
            }

            $allowed = [
                'Pending' => ['Under Review', 'Rejected', 'Dismissed'],
                'Under Review' => ['Resolved', 'Rejected', 'Closed', 'Dismissed'],
                'Resolved' => ['Closed'],
                'Rejected' => [],
                'Closed' => [],
                'Dismissed' => [],
            ];
            $from = (string) $complaint->getOriginal('status');

            if (! in_array($complaint->status, $allowed[$from] ?? [], true)) {
                throw ValidationException::withMessages(['status' => "Status cannot move from {$from} to {$complaint->status}."]);
            }

            if (in_array($complaint->status, ['Resolved', 'Rejected', 'Closed', 'Dismissed'], true) && blank($complaint->admin_remarks)) {
                throw ValidationException::withMessages(['admin_remarks' => 'Resolution notes are required for a terminal complaint status.']);
            }

            $complaint->handled_by ??= app(AuthenticatedActor::class)->id();
            if (in_array($complaint->status, ['Resolved', 'Rejected', 'Closed', 'Dismissed'], true)) {
                $complaint->resolved_at ??= now();
            }
        });
    }
}
