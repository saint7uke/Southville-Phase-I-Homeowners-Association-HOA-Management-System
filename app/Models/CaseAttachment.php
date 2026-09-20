<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CaseAttachment extends Model
{
    protected $fillable = ['complaint_id', 'service_request_id', 'disk', 'path', 'original_name', 'mime_type', 'size_bytes', 'uploaded_by'];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer'];
    }

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }
}
