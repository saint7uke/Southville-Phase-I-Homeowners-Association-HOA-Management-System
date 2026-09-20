<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Homeowner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class HomeownerProfilePhotoController extends Controller
{
    public function __invoke(Request $request, Homeowner $homeowner): StreamedResponse
    {
        Gate::forUser($request->user())->authorize('view', $homeowner);

        abort_unless(filled($homeowner->profile_photo), 404);
        $disk = $homeowner->profile_photo_disk ?: 'local';
        abort_unless(Storage::disk($disk)->exists($homeowner->profile_photo), 404);

        $extension = match ($homeowner->profile_photo_mime_type) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        return Storage::disk($disk)->response(
            $homeowner->profile_photo,
            "profile-photo.{$extension}",
            [
                'Content-Type' => $homeowner->profile_photo_mime_type ?: 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ],
            'inline',
        );
    }
}
