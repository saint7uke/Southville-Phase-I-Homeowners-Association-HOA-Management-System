<?php

declare(strict_types=1);

namespace App\Models;

use App\Notifications\HomeownerAccountStatusChanged;
use App\Support\AuthenticatedActor;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

final class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected string $guard_name = 'web';

    protected $fillable = [
        'first_name', 'middle_name', 'last_name', 'suffix', 'sex', 'contact_number',
        'date_of_birth', 'email', 'password', 'account_status', 'rejection_reason',
        'approved_at', 'approved_by',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $appends = ['full_name'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'date_of_birth' => 'date',
            'approved_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function homeowner(): HasOne
    {
        return $this->hasOne(Homeowner::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(self::class, 'approved_by');
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
            if ($user->isDirty('account_status') && $user->account_status === 'Active') {
                $user->approved_at ??= now();
                $user->approved_by ??= app(AuthenticatedActor::class)->id();
                $user->rejection_reason = null;
            }
        });
        self::saved(function (User $user): void {
            if ($user->wasChanged('account_status') && $user->homeowner) {
                $user->homeowner->update(['status' => $user->account_status === 'Active' ? 'Active' : 'Inactive']);
            }
            if ($user->wasChanged('account_status') && $user->hasRole('homeowner')) {
                $user->notify(new HomeownerAccountStatusChanged($user->account_status, $user->rejection_reason));
            }
        });
    }
}
