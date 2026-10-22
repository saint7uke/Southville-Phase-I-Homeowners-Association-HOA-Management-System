<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

final class CertificateTemplate extends Model
{
    protected $fillable = ['type', 'version', 'title', 'body', 'created_by'];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime', 'layout' => 'array'];
    }

    protected static function booted(): void
    {
        self::updating(function (self $template): void {
            if ($template->getOriginal('status') === 'Approved') {
                throw ValidationException::withMessages(['body' => __('Approved versions are immutable. Create a new draft.')]);
            }
        });
        self::deleting(fn () => throw ValidationException::withMessages(['body' => __('Template history cannot be deleted.')]));
    }
}
