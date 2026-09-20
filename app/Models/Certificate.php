<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Certificate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['homeowner_id', 'service_request_id', 'certificate_number', 'file_path', 'type', 'purpose', 'status', 'issued_at', 'expires_at', 'issued_by', 'revoked_at', 'revoked_by', 'revocation_reason'];

    protected function casts(): array
    {
        return ['issued_at' => 'datetime', 'expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function homeowner(): BelongsTo
    {
        return $this->belongsTo(Homeowner::class)->withTrashed();
    }

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by')->withTrashed();
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by')->withTrashed();
    }
}
