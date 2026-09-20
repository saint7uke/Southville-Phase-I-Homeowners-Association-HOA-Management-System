<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Certificate;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CertificateDownloadController extends Controller
{
    public function __invoke(Certificate $certificate): StreamedResponse
    {
        Gate::forUser(request()->user())->authorize('view', $certificate);
        abort_unless($certificate->status === 'Issued' && ($certificate->expires_at === null || $certificate->expires_at->isFuture()), 404);

        abort_unless(is_string($certificate->file_path) && Storage::disk('local')->exists($certificate->file_path), 404);

        return Storage::disk('local')->download(
            $certificate->file_path,
            "hoa-certificate-{$certificate->certificate_number}.pdf",
            ['Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff'],
        );
    }
}
