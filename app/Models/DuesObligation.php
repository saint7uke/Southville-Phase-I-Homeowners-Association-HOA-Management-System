<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\PropertySnapshot;
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
        'property_snapshot',
        'property_snapshot_needs_review',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'amount_due' => 'decimal:2',
            'penalty_amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'property_snapshot' => 'array',
            'property_snapshot_needs_review' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        self::creating(function (self $obligation): void {
            if ($obligation->property_snapshot === null && $obligation->homeowner_id !== null) {
                $homeowner = Homeowner::withTrashed()->find($obligation->homeowner_id);
                if ($homeowner !== null) {
                    $obligation->property_snapshot = PropertySnapshot::from($homeowner);
                    $obligation->property_snapshot_needs_review = false;
                }
            }
        });

        self::updating(function (self $obligation): void {
            if ($obligation->isDirty(['property_snapshot', 'property_snapshot_needs_review'])) {
                throw new \LogicException('Obligation property snapshots are immutable after creation.');
            }
        });
    }

    public function getPropertyAddressAttribute(): string
    {
        return PropertySnapshot::display($this->property_snapshot, $this->homeowner);
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
