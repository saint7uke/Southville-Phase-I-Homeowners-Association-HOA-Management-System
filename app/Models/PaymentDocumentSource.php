<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

final class PaymentDocumentSource extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['manifest' => 'array', 'approved_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        self::updating(fn () => throw ValidationException::withMessages(['type' => __('Approved document sources are immutable.')]));
        self::deleting(fn () => throw ValidationException::withMessages(['type' => __('Approved document sources cannot be deleted.')]));
    }
}
