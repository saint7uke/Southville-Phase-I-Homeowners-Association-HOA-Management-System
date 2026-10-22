<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

final class Certificate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['homeowner_id', 'service_request_id', 'certificate_type_id', 'certificate_number', 'file_path', 'type', 'purpose', 'status', 'issued_at', 'expires_at', 'issued_by', 'revoked_at', 'revoked_by', 'revocation_reason', 'certificate_template_id', 'document_snapshot'];

    protected function casts(): array
    {
        return ['issued_at' => 'datetime', 'expires_at' => 'datetime', 'revoked_at' => 'datetime', 'document_snapshot' => 'array'];
    }

    protected static function booted(): void
    {
        self::updating(function (self $certificate): void {
            if ($certificate->isDirty(['homeowner_id', 'service_request_id', 'certificate_type_id', 'certificate_number', 'type', 'purpose', 'certificate_template_id', 'document_snapshot', 'issued_at', 'expires_at', 'issued_by', 'issuance_key'])
                || ($certificate->isDirty('file_path') && filled($certificate->getOriginal('file_path')))) {
                throw ValidationException::withMessages(['type' => __('Issued document identity and content cannot be changed. Revoke and issue a replacement instead.')]);
            }
        });
    }

    public function homeowner(): BelongsTo
    {
        return $this->belongsTo(Homeowner::class)->withTrashed();
    }

    public function certificateType(): BelongsTo
    {
        return $this->belongsTo(CertificateType::class);
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
