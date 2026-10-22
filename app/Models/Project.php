<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Project extends Model
{
    use SoftDeletes;

    protected $fillable = ['title', 'description', 'project_date', 'completion_status', 'published_at', 'created_by'];

    protected function casts(): array
    {
        return ['project_date' => 'date', 'published_at' => 'datetime'];
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProjectImage::class);
    }

    public function scopePublished($query): void
    {
        $query->where('completion_status', 'Completed')->whereNotNull('published_at')->where('published_at', '<=', now());
    }
}
