<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Homeowner extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['user_id', 'house_number', 'street', 'block', 'lot', 'residency_date', 'ownership_type', 'status', 'profile_photo'];

    protected $appends = ['full_address'];

    protected function casts(): array
    {
        return ['residency_date' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
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

        return $property.', Southville Phase I, Brgy. Inocencio';
    }
}
