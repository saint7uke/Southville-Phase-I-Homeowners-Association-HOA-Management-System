<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ContactMessageActivity extends Model
{
    protected $fillable = [
        'contact_message_id', 'author_id', 'type', 'recipient_email', 'subject', 'body',
        'idempotency_token', 'delivery_status', 'sent_at', 'failed_at', 'failure_code',
    ];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'failed_at' => 'datetime'];
    }

    public function contactMessage(): BelongsTo
    {
        return $this->belongsTo(ContactMessage::class)->withTrashed();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id')->withTrashed();
    }
}
