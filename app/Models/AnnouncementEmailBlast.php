<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class AnnouncementEmailBlast extends Model
{
    protected $fillable = ['announcement_id', 'sent_by', 'recipient_count', 'queued_at'];

    protected function casts(): array
    {
        return ['queued_at' => 'datetime', 'recipient_count' => 'integer'];
    }

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(AnnouncementEmailDelivery::class, 'blast_id');
    }
}
