<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

final class PaymentDocumentPreview extends Model
{
    use Prunable;

    protected $primaryKey = 'token_hash';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'expires_at' => 'datetime'];
    }

    public function prunable(): Builder
    {
        return self::where('expires_at', '<', now()->subDay());
    }
}
