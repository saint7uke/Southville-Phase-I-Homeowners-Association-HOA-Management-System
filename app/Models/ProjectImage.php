<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProjectImage extends Model
{
    protected $fillable = ['project_id', 'path', 'mime_type', 'alt_text'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
