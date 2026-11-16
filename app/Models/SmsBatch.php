<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class SmsBatch extends Model
{
    protected $guarded = [];

    protected $hidden = [];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'next_attempt_at' => 'datetime', 'started_at' => 'datetime', 'accepted_at' => 'datetime', 'last_checked_at' => 'datetime'];
    }
}
