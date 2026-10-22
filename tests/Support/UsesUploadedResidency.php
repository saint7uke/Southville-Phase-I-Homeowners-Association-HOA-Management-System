<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Actions\Certificates\IssueCertificate;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\CertificateTemplates;
use App\Services\ResidencyCertificatePreviews;
use Illuminate\Support\Str;

trait UsesUploadedResidency
{
    private function requireResidencyFont(): void
    {
        $path = config('certificates.residency_font');
        if (! is_string($path) || ! is_file($path)) {
            $this->markTestSkipped('Requires an appropriately licensed Times New Roman Bold TTF via CERTIFICATE_RESIDENCY_FONT; no test font substitution is used.');
        }
    }

    private function confirmUploadedSource(): void
    {
        $this->requireResidencyFont();
        if (app(CertificateTemplates::class)->approved('Certificate of Residency') !== null) {
            return;
        }
        $admin = User::role('hoa_admin')->first();
        if ($admin === null) {
            $admin = User::factory()->create();
            $admin->assignRole('hoa_admin');
        }
        app(CertificateTemplates::class)->confirmResidencySource(['reviewed' => true, 'confirmed' => true, 'note' => 'Synthetic source authorization for isolated regression tests.'], $admin);
    }

    private function issueCertificate(array $data, User $actor)
    {
        if (($data['type'] ?? '') === 'Certificate of Residency' && ! isset($data['expires_at'])) {
            $this->confirmUploadedSource();
            $data['preview_token'] = app(ResidencyCertificatePreviews::class)->prepare($data, $actor);
        }

        return app(IssueCertificate::class)->handle($data, $actor);
    }

    private function issueRequestedCertificate(ServiceRequest $request, User $actor, ?string $purpose = null)
    {
        $token = null;
        if ($request->request_type === 'Certificate of Residency') {
            $this->confirmUploadedSource();
            $purpose ??= Str::limit((string) $request->details, 255, '');
            $token = app(ResidencyCertificatePreviews::class)->prepare(['homeowner_id' => $request->homeowner_id, 'purpose' => $purpose], $actor, $request->id);
        }

        return app(IssueCertificate::class)->fromApprovedRequest($request, $actor, $purpose, $token);
    }
}
