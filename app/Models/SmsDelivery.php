<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class SmsDelivery extends Model
{
    protected $guarded = [];

    protected $hidden = ['phone', 'body', 'context', 'phone_hash'];

    protected function casts(): array
    {
        return ['user_ids' => 'array', 'context' => 'encrypted:array', 'phone' => 'encrypted', 'body' => 'encrypted', 'accepted_at' => 'datetime', 'sent_at' => 'datetime', 'delivered_at' => 'datetime', 'provider_updated_at' => 'datetime'];
    }
}
