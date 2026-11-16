<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class SmsContactPreference extends Model
{
    protected $guarded = [];

    protected $hidden = [];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime', 'consented_at' => 'datetime', 'transactional_opt_in' => 'boolean', 'announcement_opt_in' => 'boolean'];
    }
}
