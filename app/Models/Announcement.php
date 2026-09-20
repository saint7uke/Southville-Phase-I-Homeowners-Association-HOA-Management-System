<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\AuthenticatedActor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class Announcement extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The four brief-defined categories are listed first. Legacy values remain
     * selectable so existing installations can edit historical announcements.
     *
     * @var list<string>
     */
    public const CATEGORIES = [
        'General',
        'Urgent',
        'Event',
        'Reminder',
        'Maintenance',
        'Emergency',
        'Financial',
    ];

    protected $fillable = ['title', 'content', 'banner_image', 'category', 'audience', 'status', 'published_at', 'expires_at', 'created_by'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function readers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'announcement_reads')->withPivot('read_at');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', 'Published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where(fn (Builder $query): Builder => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function scopeVisibleToPublic(Builder $query): Builder
    {
        return $query->published()->where('audience', 'Public');
    }

    public function scopeVisibleToResidents(Builder $query): Builder
    {
        return $query->published()->whereIn('audience', ['Public', 'Residents']);
    }

    protected static function booted(): void
    {
        self::creating(fn (Announcement $announcement) => $announcement->created_by ??= app(AuthenticatedActor::class)->id());
        self::saving(function (Announcement $announcement): void {
            if ($announcement->status === 'Published') {
                $announcement->published_at ??= now();
            }

            if ($announcement->expires_at !== null && $announcement->published_at !== null && $announcement->expires_at->lessThanOrEqualTo($announcement->published_at)) {
                throw ValidationException::withMessages([
                    'expires_at' => __('The expiry time must be after the publication time.'),
                ]);
            }
        });
        self::updated(function (Announcement $announcement): void {
            if (! $announcement->wasChanged('banner_image')) {
                return;
            }

            $previousPath = $announcement->getOriginal('banner_image');
            if (is_string($previousPath) && $previousPath !== '' && $previousPath !== $announcement->banner_image) {
                DB::afterCommit(fn () => Storage::disk('public')->delete($previousPath));
            }
        });
        self::forceDeleted(function (Announcement $announcement): void {
            if (filled($announcement->banner_image)) {
                Storage::disk('public')->delete($announcement->banner_image);
            }
        });
    }
}
