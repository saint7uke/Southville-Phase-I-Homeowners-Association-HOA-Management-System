<?php

namespace App\Http\Controllers;

use App\Models\ProjectImage;
use App\Support\OperationalAccess;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

final class ProjectImageController extends Controller
{
    public function __invoke(ProjectImage $image)
    {
        $public = $image->project()->published()->exists();
        $manager = false;
        foreach (['admin', 'staff', 'homeowner', 'web'] as $guard) {
            if (OperationalAccess::allows(Auth::guard($guard)->user(), 'manage_projects', true)) {
                $manager = true;
                break;
            }
        }
        abort_unless($public || $manager, 404);
        abort_unless(Storage::disk('local')->exists($image->path), 404);

        return Storage::disk('local')->response($image->path, null, ['Content-Type' => $image->mime_type, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
