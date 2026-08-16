<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ComplaintAttachmentController extends Controller
{
    public function __invoke(Complaint $complaint): StreamedResponse
    {
        abort_unless(request()->user()->can('view', $complaint), 404);
        abort_unless($complaint->attachment && Storage::disk('local')->exists($complaint->attachment), 404);

        return Storage::disk('local')->download($complaint->attachment, $complaint->attachment_name ?? 'complaint-attachment');
    }
}
