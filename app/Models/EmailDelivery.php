<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class EmailDelivery extends Model
{
    protected $guarded = [];

    protected $hidden = ['payload'];

    protected function casts(): array
    {
        return ['payload' => 'encrypted', 'started_at' => 'datetime', 'accepted_at' => 'datetime', 'failed_at' => 'datetime'];
    }
}
