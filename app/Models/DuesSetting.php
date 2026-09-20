<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class DuesSetting extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'amount', 'frequency', 'description', 'due_day', 'starts_on', 'ends_on', 'is_active', 'created_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'due_day' => 'integer', 'starts_on' => 'date', 'ends_on' => 'date', 'is_active' => 'boolean'];
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function obligations(): HasMany
    {
        return $this->hasMany(DuesObligation::class);
    }
}
