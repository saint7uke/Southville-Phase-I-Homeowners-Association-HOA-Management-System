<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\AuthenticatedActor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class Complaint extends Model
{
    use HasFactory, SoftDeletes;

    private bool $isReopening = false;

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

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    public function reopen(): bool
    {
        if ($this->status !== 'Resolved' || $this->trashed()) {
            throw ValidationException::withMessages(['status' => __('Only a resolved complaint can be reopened.')]);
        }

        $this->isReopening = true;

        try {
            return $this->forceFill([
                'status' => 'Under Review',
                'resolved_at' => null,
            ])->save();
        } finally {
            $this->isReopening = false;
        }
    }

    protected function performUpdate(Builder $query): bool
    {
        // Protect non-Filament Eloquent callers too: checking a loaded original
        // status alone cannot prevent another writer from finalizing the record.
        return $this->getConnection()->transaction(function () use ($query): bool {
            $current = self::withTrashed()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();
            if ($current->status !== $this->getRawOriginal('status')) {
                throw ValidationException::withMessages(['status' => __('This complaint changed while you were editing. Reload it before making changes.')]);
            }

            return parent::performUpdate($query);
        });
    }

    protected static function booted(): void
    {
        self::creating(fn (Complaint $complaint) => $complaint->ticket_number ??= 'CMP-'.now()->format('Y').'-'.Str::upper(Str::random(8)));
        self::deleting(function (Complaint $complaint): void {
            if (in_array($complaint->status, ['Resolved', 'Rejected', 'Closed', 'Dismissed'], true)) {
                throw ValidationException::withMessages(['status' => __('Finalized complaints cannot be deleted.')]);
            }
        });
        self::updating(function (Complaint $complaint): void {
            $from = (string) $complaint->getOriginal('status');

            if (in_array($from, ['Resolved', 'Rejected', 'Closed', 'Dismissed'], true)) {
                if (! $complaint->isReopening) {
                    throw ValidationException::withMessages(['status' => __('Finalized complaints are read-only. Reopen a resolved complaint before making changes.')]);
                }

                $unexpectedChanges = array_diff(array_keys($complaint->getDirty()), ['status', 'resolved_at', 'updated_at']);
                if ($from !== 'Resolved' || $complaint->status !== 'Under Review' || $complaint->resolved_at !== null || $unexpectedChanges !== []) {
                    throw ValidationException::withMessages(['status' => __('The complaint could not be reopened safely.')]);
                }

                return;
            }

            if (! $complaint->isDirty('status')) {
                return;
            }

            $allowed = [
                'Pending' => ['Under Review', 'Rejected', 'Dismissed'],
                'Under Review' => ['Resolved', 'Rejected', 'Closed', 'Dismissed'],
                'Resolved' => [],
                'Rejected' => [],
                'Closed' => [],
                'Dismissed' => [],
            ];

            if (! in_array($complaint->status, $allowed[$from] ?? [], true)) {
                throw ValidationException::withMessages(['status' => "Status cannot move from {$from} to {$complaint->status}."]);
            }

            if (in_array($complaint->status, ['Resolved', 'Rejected', 'Closed', 'Dismissed'], true) && blank($complaint->admin_remarks)) {
                throw ValidationException::withMessages(['admin_remarks' => __('Resolution notes are required for a terminal complaint status.')]);
            }

            $complaint->handled_by ??= app(AuthenticatedActor::class)->id();
            if (in_array($complaint->status, ['Resolved', 'Rejected', 'Closed', 'Dismissed'], true)) {
                $complaint->resolved_at ??= now();
            }
        });
    }
}
