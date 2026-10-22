<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\PropertyIdentity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Household extends Model
{
    use SoftDeletes;

    protected $fillable = ['identifier', 'address_key', 'house_number', 'street', 'block', 'lot', 'phase', 'phase_suffix', 'registered_homeowner_id', 'head_member_id', 'contact_number', 'occupancy', 'needs_review'];

    protected static function booted(): void
    {
        self::saving(function (self $household): void {
            $household->phase_suffix = PropertyIdentity::suffix($household->phase_suffix);
            $household->property_key = PropertyIdentity::confirmedKey($household);
        });
    }

    protected function casts(): array
    {
        return ['needs_review' => 'boolean'];
    }

    public function members()
    {
        return $this->hasMany(HouseholdMember::class);
    }

    public function registeredHomeowner()
    {
        return $this->belongsTo(Homeowner::class, 'registered_homeowner_id')->withTrashed();
    }

    public function head()
    {
        return $this->belongsTo(HouseholdMember::class, 'head_member_id')->withTrashed();
    }

    public function getFullAddressAttribute(): string
    {
        return PropertyIdentity::address($this);
    }
}
