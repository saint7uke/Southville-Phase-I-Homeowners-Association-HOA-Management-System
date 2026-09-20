<?php

declare(strict_types=1);

namespace App\Actions\Certificates;

use App\Models\Certificate;
use App\Models\SystemSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class StoreCertificateDocument
{
    public function handle(Certificate $certificate): string
    {
        $certificate->loadMissing(['homeowner.user', 'issuer']);
        $path = 'certificates/'.$certificate->issued_at->format('Y/m').'/'.$certificate->certificate_number.'.pdf';
        $contents = Pdf::loadView('pdf.certificate', [
            'certificate' => $certificate,
            'settings' => SystemSetting::current(),
        ])->setPaper('a4')->setOption(['isRemoteEnabled' => false, 'isPhpEnabled' => false])->output();

        if (! Storage::disk('local')->put($path, $contents)) {
            throw new RuntimeException('The certificate document could not be stored.');
        }

        return $path;
    }
}
