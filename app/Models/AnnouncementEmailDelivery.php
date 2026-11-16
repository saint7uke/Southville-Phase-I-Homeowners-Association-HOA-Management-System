<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AnnouncementEmailDelivery extends Model
{
    protected $fillable = [
        'blast_id', 'announcement_id', 'user_id', 'status', 'queued_at',
        'sent_at', 'failed_at', 'failure_code',
    ];

    protected function casts(): array
    {
        return ['queued_at' => 'datetime', 'sent_at' => 'datetime', 'failed_at' => 'datetime'];
    }

    public function blast(): BelongsTo
    {
        return $this->belongsTo(AnnouncementEmailBlast::class, 'blast_id');
    }

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }
}
