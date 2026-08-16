<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ServiceRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['homeowner_id', 'ticket_number', 'request_type', 'details', 'status', 'admin_remarks', 'document_output', 'handled_by', 'completed_at'];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
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
        self::creating(fn (ServiceRequest $request) => $request->ticket_number ??= 'REQ-'.now()->format('Y').'-'.Str::upper(Str::random(8)));
        self::updating(function (ServiceRequest $request): void {
            if (! $request->isDirty('status')) {
                return;
            }

            $allowed = ['Pending' => ['Processing', 'Rejected'], 'Processing' => ['Completed', 'Rejected'], 'Completed' => [], 'Rejected' => []];
            $from = (string) $request->getOriginal('status');

            if (! in_array($request->status, $allowed[$from] ?? [], true)) {
                throw ValidationException::withMessages(['status' => "Status cannot move from {$from} to {$request->status}."]);
            }

            $request->handled_by ??= auth()->id();
            if ($request->status === 'Completed') {
                $request->completed_at ??= now();
            }
        });
    }
}
