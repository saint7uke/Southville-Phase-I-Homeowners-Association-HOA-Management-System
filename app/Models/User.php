<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

final class User extends Authenticatable implements FilamentUser, MustVerifyEmailContract
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected string $guard_name = 'web';

    protected $fillable = [
        'first_name', 'middle_name', 'last_name', 'suffix', 'sex', 'contact_number',
        'date_of_birth', 'email', 'password',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $appends = ['age', 'full_name'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'date_of_birth' => 'date',
            'approved_at' => 'datetime',
            'last_login_at' => 'datetime',
            'suspended_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function homeowner(): HasOne
    {
        return $this->hasOne(Homeowner::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(self::class, 'approved_by')->withTrashed();
    }

    public function suspender(): BelongsTo
    {
        return $this->belongsTo(self::class, 'suspended_by')->withTrashed();
    }

    public function getFullNameAttribute(): string
    {
        return collect([$this->first_name, $this->middle_name, $this->last_name, $this->suffix])
            ->filter(fn (?string $part): bool => filled($part) && $part !== 'N/A')
            ->implode(' ');
    }

    public function getNameAttribute(): string
    {
        return $this->full_name;
    }

    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth?->age;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->account_status !== 'Active') {
            return false;
        }

        return match ($panel->getId()) {
            'admin' => $this->hasRole('hoa_admin'),
            'staff' => $this->hasRole('hoa_staff'),
            'homeowner' => $this->hasRole('homeowner'),
            default => false,
        };
    }

    public function normalizeIdentity(): void
    {
        $this->email = Str::lower(trim($this->email));
    }

    protected static function booted(): void
    {
        self::saving(function (User $user): void {
            $user->normalizeIdentity();
            if ($user->exists && $user->isDirty('email')) {
                $user->email_verified_at = null;
            }
        });
    }
}
