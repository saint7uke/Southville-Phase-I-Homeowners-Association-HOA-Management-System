<?php

declare(strict_types=1);

namespace App\Actions\Projects;

use App\Models\Project;
use App\Models\User;
use App\Support\OperationalAccess;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ManageProject
{
    public function save(?int $id, array $data, array $images, User $actor): Project
    {
        OperationalAccess::authorize($actor, 'manage_projects', true);
        foreach (['title', 'description'] as $key) {
            $data[$key] = trim((string) ($data[$key] ?? ''));
        }
        $validated = Validator::make([...$data, 'images' => $images], [
            'title' => ['required', 'string', 'max:160', 'not_regex:/[\x00-\x1F\x7F]/u'],
            'description' => ['required', 'string', 'max:10000'], 'project_date' => ['required', 'date', 'before_or_equal:today'],
            'completion_status' => ['required', Rule::in(['Planned', 'In Progress', 'Completed'])], 'publish' => ['required', 'boolean'],
            'images' => ['array', 'max:5'], 'images.*' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=100,min_height=100,max_width=6000,max_height=6000'],
            'created_by' => ['prohibited'], 'published_at' => ['prohibited'],
        ])->validate();
        if ($validated['publish'] && $validated['completion_status'] !== 'Completed') {
            throw ValidationException::withMessages(['publish' => __('Only completed projects may be published.')]);
        }
        $stored = [];
        try {
            return DB::transaction(function () use ($id, $validated, $images, $actor, &$stored): Project {
                $project = $id ? Project::query()->lockForUpdate()->findOrFail($id) : new Project;
                if ($project->images()->count() + count($images) > 5) {
                    throw ValidationException::withMessages(['images' => __('A project may contain at most five images.')]);
                }
                $project->fill(Arr::only($validated, ['title', 'description', 'project_date', 'completion_status']));
                $project->published_at = $validated['publish'] ? ($project->published_at ?? now()) : null;
                if (! $project->exists) {
                    $project->created_by = $actor->id;
                }
                $project->save();
                foreach ($images as $upload) {
                    if (! $upload->isValid()) {
                        throw ValidationException::withMessages(['images' => __('The image upload did not complete. Please upload it again.')]);
                    }
                    $source = @imagecreatefromstring(file_get_contents($upload->getRealPath()));
                    if ($source === false) {
                        throw ValidationException::withMessages(['images' => __('This image could not be decoded. Please choose another image.')]);
                    }
                    try {
                        ob_start();
                        imagepng($source);
                        $bytes = ob_get_clean();
                    } finally {
                        imagedestroy($source);
                    }
                    // Re-encoding strips EXIF and all original metadata; images never enter the public disk.
                    $path = 'projects/'.$project->id.'/'.Str::uuid().'.png';
                    $stored[] = $path;
                    if (! is_string($bytes) || ! Storage::disk('local')->put($path, $bytes)) {
                        throw ValidationException::withMessages(['images' => __('The image could not be stored. Please try again.')]);
                    }
                    $project->images()->create(['path' => $path, 'mime_type' => 'image/png', 'alt_text' => $project->title]);
                }

                return $project;
            }, 1);
        } catch (\Throwable $exception) {
            foreach ($stored as $path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }
    }

    public function archive(int $id, bool $restore, User $actor): void
    {
        OperationalAccess::authorize($actor, 'manage_projects', true);
        DB::transaction(function () use ($id, $restore): void {
            $project = Project::withTrashed()->lockForUpdate()->findOrFail($id);
            if ($restore) {
                $project->published_at = null;
                $project->restore();
                $project->save();
            } else {
                $project->delete();
            }
        });
    }
}
