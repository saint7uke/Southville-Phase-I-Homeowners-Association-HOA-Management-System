<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class DuesObligation extends Model
{
    use HasFactory;

    protected $fillable = [
        'homeowner_id',
        'dues_setting_id',
        'billing_year',
        'billing_month',
        'due_date',
        'amount_due',
        'penalty_amount',
        'amount_paid',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'amount_due' => 'decimal:2',
            'penalty_amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
        ];
    }

    public function homeowner(): BelongsTo
    {
        return $this->belongsTo(Homeowner::class)->withTrashed();
    }

    public function duesSetting(): BelongsTo
    {
        return $this->belongsTo(DuesSetting::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
