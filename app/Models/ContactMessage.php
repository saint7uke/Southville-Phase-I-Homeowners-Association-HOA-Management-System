<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ContactMessage extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'email', 'phone', 'subject', 'message', 'status', 'handled_by', 'handled_at', 'ip_address'];

    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by')->withTrashed();
    }
}
