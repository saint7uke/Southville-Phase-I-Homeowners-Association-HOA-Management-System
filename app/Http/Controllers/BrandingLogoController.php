<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class BrandingLogoController extends Controller
{
    public function __invoke(): StreamedResponse
    {
        $path = SystemSetting::current()->logo_path;
        abort_unless(is_string($path) && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
