<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class SmsVerification extends Model
{
    protected $guarded = [];

    protected $hidden = ['phone', 'code_hash', 'binding_hash'];

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['phone' => 'encrypted', 'expires_at' => 'datetime', 'verified_at' => 'datetime', 'proof_expires_at' => 'datetime', 'consumed_at' => 'datetime', 'invalidated_at' => 'datetime'];
    }
}
