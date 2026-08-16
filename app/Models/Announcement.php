<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Announcement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['title', 'content', 'banner_image', 'category', 'status', 'published_at', 'created_by'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'Published')->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    protected static function booted(): void
    {
        self::creating(fn (Announcement $announcement) => $announcement->created_by ??= auth()->id());
        self::saving(function (Announcement $announcement): void {
            if ($announcement->status === 'Published') {
                $announcement->published_at ??= now();
            }
        });
    }
}
