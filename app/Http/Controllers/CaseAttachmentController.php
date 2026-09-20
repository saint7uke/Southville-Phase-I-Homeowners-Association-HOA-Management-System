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
        $caseAttachment->loadMissing(['complaint.homeowner', 'serviceRequest.homeowner']);
        $record = $caseAttachment->complaint ?? $caseAttachment->serviceRequest;
        abort_unless($record !== null && request()->user()?->can('view', $record), 404);
        abort_unless(Storage::disk($caseAttachment->disk)->exists($caseAttachment->path), 404);

        return Storage::disk($caseAttachment->disk)->download($caseAttachment->path, $caseAttachment->original_name, [
            'Content-Type' => $caseAttachment->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
