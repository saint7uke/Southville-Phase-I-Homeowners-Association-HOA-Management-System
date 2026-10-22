<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class HouseholdMember extends Model
{
    use SoftDeletes;

    protected $fillable = ['household_id', 'homeowner_id', 'current_homeowner_id', 'full_name', 'relationship', 'date_of_birth', 'sex', 'residency_status', 'ended_at'];

    protected function casts(): array
    {
        return ['date_of_birth' => 'date', 'ended_at' => 'datetime'];
    }

    public function household()
    {
        return $this->belongsTo(Household::class)->withTrashed();
    }

    public function homeowner()
    {
        return $this->belongsTo(Homeowner::class)->withTrashed();
    }
}
