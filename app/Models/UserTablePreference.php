<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class UserTablePreference extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['columns' => 'array'];
    }
}
