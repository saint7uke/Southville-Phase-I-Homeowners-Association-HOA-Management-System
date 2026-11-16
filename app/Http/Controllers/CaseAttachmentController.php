<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CaseAttachment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CaseAttachmentController extends Controller
{
    public function __invoke(CaseAttachment $caseAttachment): StreamedResponse
    {
        $this->authorizeAttachment($caseAttachment);

        return Storage::disk($caseAttachment->disk)->download($caseAttachment->path, $caseAttachment->original_name, [
            'Content-Type' => $caseAttachment->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function preview(CaseAttachment $caseAttachment): StreamedResponse
    {
        $this->authorizeAttachment($caseAttachment);
        abort_unless(in_array($caseAttachment->mime_type, ['image/jpeg', 'image/png'], true), 404);
        $disk = Storage::disk($caseAttachment->disk);
        abort_unless($disk->size($caseAttachment->path) <= 5 * 1024 * 1024, 404);
        $contents = $disk->get($caseAttachment->path);
        $image = @getimagesizefromstring($contents);
        abort_unless($image !== false && in_array($image[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)
            && $image['mime'] === $caseAttachment->mime_type, 404);

        return response()->stream(static function () use ($contents): void {
            echo $contents;
        }, 200, [
            'Content-Type' => $image['mime'],
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function authorizeAttachment(CaseAttachment $caseAttachment): void
    {
        $caseAttachment->loadMissing(['complaint.homeowner', 'serviceRequest.homeowner']);
        $record = $caseAttachment->complaint ?? $caseAttachment->serviceRequest;
        abort_unless($record !== null && request()->user()?->can('view', $record), 404);
        abort_unless(Storage::disk($caseAttachment->disk)->exists($caseAttachment->path), 404);
    }
}
