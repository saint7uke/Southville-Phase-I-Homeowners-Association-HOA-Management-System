<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

final class PaymentDocument extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }

    protected static function booted(): void
    {
        self::updating(fn () => throw ValidationException::withMessages(['payment_id' => __('Generated payment documents are immutable.')]));
        self::deleting(fn () => throw ValidationException::withMessages(['payment_id' => __('Generated payment documents cannot be deleted.')]));
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class)->withTrashed();
    }
}
