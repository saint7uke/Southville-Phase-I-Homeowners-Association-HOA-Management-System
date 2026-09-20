<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\AuthenticatedActor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ServiceRequest extends Model
{
    use HasFactory, SoftDeletes;

    public const CERTIFICATE_TYPES = [
        'Certificate of Residency',
        'Certificate of Good Standing',
        'Community Clearance',
    ];

    public const TYPES = [
        ...self::CERTIFICATE_TYPES,
        'Repair/Maintenance',
        'Other',
    ];

    protected $fillable = ['homeowner_id', 'ticket_number', 'request_type', 'subject', 'details', 'status', 'admin_remarks', 'document_output', 'handled_by', 'completed_at'];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
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

    public function certificate(): HasOne
    {
        return $this->hasOne(Certificate::class);
    }

    protected static function booted(): void
    {
        self::creating(fn (ServiceRequest $request) => $request->ticket_number ??= 'REQ-'.now()->format('Y').'-'.Str::upper(Str::random(8)));
        self::updating(function (ServiceRequest $request): void {
            if (! $request->isDirty('status')) {
                return;
            }

            $allowed = ['Pending' => ['Processing', 'Rejected'], 'Processing' => ['Approved', 'Rejected'], 'Approved' => ['Completed'], 'Completed' => [], 'Rejected' => []];
            $from = (string) $request->getOriginal('status');

            if (! in_array($request->status, $allowed[$from] ?? [], true)) {
                throw ValidationException::withMessages(['status' => "Status cannot move from {$from} to {$request->status}."]);
            }

            if ($request->status === 'Rejected' && blank($request->admin_remarks)) {
                throw ValidationException::withMessages(['admin_remarks' => 'Response notes are required when rejecting a request.']);
            }

            $request->handled_by ??= app(AuthenticatedActor::class)->id();
            if ($request->status === 'Completed') {
                $request->completed_at ??= now();
            }
        });
    }
}
