<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\PropertyAddress;
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
        'landlord_name',
        'landlord_contact',
        'landlord_homeowner_id',
        'lease_start_date',
        'lease_end_date',
        'verification_document_path',
    ];

    protected $appends = ['full_address', 'landlord_relationship_status'];

    protected function casts(): array
    {
        return [
            'residency_date' => 'date',
            'lease_start_date' => 'date',
            'lease_end_date' => 'date',
            'profile_photo_size' => 'integer',
            'profile_photo_uploaded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        self::saving(function (self $homeowner): void {
            $homeowner->address_key = PropertyAddress::key([
                'house_number' => $homeowner->house_number,
                'street' => $homeowner->street,
                'block' => $homeowner->block,
                'lot' => $homeowner->lot,
                'phase' => $homeowner->phase,
            ]);

            if ($homeowner->landlord_homeowner_id !== null) {
                if ($homeowner->exists && $homeowner->landlord_homeowner_id === $homeowner->id) {
                    throw ValidationException::withMessages([
                        'landlord_homeowner_id' => __('A homeowner cannot be their own landlord.'),
                    ]);
                }

                $landlord = self::query()->find($homeowner->landlord_homeowner_id);
                if ($homeowner->ownership_type !== 'Tenant'
                    || $landlord === null
                    || ! in_array($landlord->ownership_type, ['Owner', 'Co-owner'], true)
                    || $landlord->address_key !== $homeowner->address_key) {
                    throw ValidationException::withMessages([
                        'landlord_homeowner_id' => __('The landlord must be an Owner or Co-owner registered at the same address.'),
                    ]);
                }

                $landlord->loadMissing('user');
                if (($homeowner->isDirty('landlord_homeowner_id') || ! $homeowner->exists)
                    && ($landlord->status !== 'Active' || $landlord->user?->account_status !== 'Active')) {
                    throw ValidationException::withMessages([
                        'landlord_homeowner_id' => __('Only an active Owner or Co-owner can be linked as a landlord.'),
                    ]);
                }

                if ($homeowner->isDirty('landlord_homeowner_id') || blank($homeowner->landlord_name)) {
                    $homeowner->landlord_name = $landlord->user?->full_name;
                    $homeowner->landlord_contact = $landlord->user?->contact_number;
                }
            }
        });

        self::updating(function (self $homeowner): void {
            if ($homeowner->isDirty('user_id')) {
                throw ValidationException::withMessages([
                    'user_id' => __('A homeowner record cannot be reassigned to another account.'),
                ]);
            }

            if (($homeowner->isDirty('address_key') || $homeowner->isDirty('ownership_type'))
                && $homeowner->tenants()->exists()
                && ($homeowner->ownership_type === 'Tenant'
                    || $homeowner->tenants()->where('address_key', '!=', $homeowner->address_key)->exists())) {
                throw ValidationException::withMessages([
                    'address' => __('Unlink or reassign this homeowner\'s tenants before changing the property address or occupancy type.'),
                ]);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(self::class, 'landlord_homeowner_id')->withTrashed();
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(self::class, 'landlord_homeowner_id');
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

    public function getLandlordRelationshipStatusAttribute(): string
    {
        if ($this->ownership_type !== 'Tenant') {
            return 'Not applicable';
        }

        return $this->landlord_homeowner_id === null ? 'Needs landlord review' : 'Linked';
    }
}
