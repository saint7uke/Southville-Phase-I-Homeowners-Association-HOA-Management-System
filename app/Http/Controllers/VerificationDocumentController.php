<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Homeowner;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

final class VerificationDocumentController extends Controller
{
    public function __invoke(Homeowner $homeowner): Response
    {
        abort_unless(request()->user()?->can('manage_homeowners'), 404);

        $path = $homeowner->verification_document_path;
        abort_unless(
            is_string($path)
            && str_starts_with($path, 'verification_documents/')
            && ! str_contains($path, '..'),
            404,
        );

        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        $mimeType = $disk->mimeType($path) ?: 'application/octet-stream';
        abort_unless(in_array($mimeType, ['application/pdf', 'image/jpeg', 'image/png'], true), 404);

        return response($disk->get($path), 200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="'.addslashes(basename($path)).'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
