<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

final class Homeowner extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'house_number',
        'street',
        'block',
        'lot',
        'phase',
        'residency_date',
        'ownership_type',
        'emergency_contact_name',
        'emergency_contact_number',
        'status',
        'profile_photo',
        'profile_photo_disk',
        'profile_photo_original_name',
        'profile_photo_mime_type',
        'profile_photo_size',
        'profile_photo_uploaded_at',
    ];

    protected $appends = ['full_address'];

    protected function casts(): array
    {
        return [
            'residency_date' => 'date',
            'profile_photo_size' => 'integer',
            'profile_photo_uploaded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        self::updating(function (self $homeowner): void {
            if ($homeowner->isDirty('user_id')) {
                throw ValidationException::withMessages([
                    'user_id' => __('A homeowner record cannot be reassigned to another account.'),
                ]);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function duesObligations(): HasMany
    {
        return $this->hasMany(DuesObligation::class);
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }

    public function getFullAddressAttribute(): string
    {
        $property = collect([
            filled($this->block) ? "Block {$this->block}" : null,
            filled($this->lot) ? "Lot {$this->lot}" : null,
            $this->house_number,
            $this->street,
        ])->filter()->implode(' ');

        return $property.', '.($this->phase ?: 'Southville Phase I').', Brgy. Inocencio';
    }
}
