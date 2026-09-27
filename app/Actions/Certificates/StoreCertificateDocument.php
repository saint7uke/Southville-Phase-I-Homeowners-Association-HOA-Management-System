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
        $logoPath = public_path('images/HOA.png');

        if (! is_readable($logoPath) || ($logoContents = file_get_contents($logoPath)) === false) {
            throw new RuntimeException('The HOA logo could not be loaded for the certificate.');
        }

        $path = 'certificates/'.$certificate->issued_at->format('Y/m').'/'.$certificate->certificate_number.'.pdf';
        $contents = Pdf::loadView('pdf.certificate', [
            'certificate' => $certificate,
            'settings' => SystemSetting::current(),
            'logoDataUri' => 'data:image/png;base64,'.base64_encode($logoContents),
        ])->setPaper('a4')->setOption(['isRemoteEnabled' => false, 'isPhpEnabled' => false])->output();

        if (! Storage::disk('local')->put($path, $contents)) {
            throw new RuntimeException('The certificate document could not be stored.');
        }

        return $path;
    }
}
