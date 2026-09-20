<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ReportExport extends Model
{
    protected $fillable = [
        'user_id', 'panel', 'report', 'format', 'filters', 'status', 'file_path',
        'failure_reason', 'completed_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
