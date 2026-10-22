<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Filament\Homeowner\Resources\ServiceRequests\ServiceRequestResource as HomeownerServiceRequestResource;
use App\Filament\Resources\Certificates\CertificateResource;
use App\Filament\Resources\ServiceRequests\ServiceRequestResource;
use App\Models\CertificateTemplate;
use App\Models\ServiceRequest;
use App\Services\CertificateTemplates;
use App\Services\CertificateTypeCatalog;
use App\Services\CohabitationCertificateData;
use App\Services\CohabitationCertificateSource;
use App\Services\OriginalCertificateRenderer;
use App\Services\ResidencyCertificatePreviews;
use App\Support\OperationalAccess;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class CertificateDraftController extends Controller
{
    public function prepared(string $token)
    {
        try {
            $preview = app(ResidencyCertificatePreviews::class)->read($token, request()->user());
            $snapshot = $preview->snapshot;

            return $this->originalResponse(app(OriginalCertificateRenderer::class)->render($snapshot['layout'], app(OriginalCertificateRenderer::class)->values($snapshot, Carbon::parse($snapshot['issuance_date'])), true));
        } catch (ValidationException) {
            abort(410, __('The preview expired or its information changed. Generate a fresh preview before issuing.'));
        }
    }

    public function reference()
    {
        abort_unless(OperationalAccess::allows(request()->user(), 'manage_settings', true), 403);

        return $this->originalResponse(app(OriginalCertificateRenderer::class)->referencePreview());
    }

    public function cohabitationReference(CohabitationCertificateSource $source)
    {
        OperationalAccess::authorize(request()->user(), 'manage_settings', true);
        try {
            return $this->originalResponse(app(OriginalCertificateRenderer::class)->referencePreview($source->manifest(), $source->pdf()));
        } catch (ValidationException $exception) {
            return response()->view('errors.certificate-source-unavailable', [
                'status' => $source->setupStatus(),
                'message' => collect($exception->errors())->flatten()->first(),
                'returnUrl' => CertificateResource::getUrl('index', panel: 'admin'),
            ], 409)->header('Cache-Control', 'private, no-store');
        }
    }

    public function template(CertificateTemplate $template)
    {
        abort_unless(OperationalAccess::allows(request()->user(), 'manage_requests') || OperationalAccess::allows(request()->user(), 'manage_settings', true), 403);
        if ($template->type === CohabitationCertificateData::TYPE) {
            try {
                abort_unless(app(CohabitationCertificateSource::class)->previewTemplate()->id === $template->id, 409);
            } catch (ValidationException $exception) {
                abort(409, collect($exception->errors())->flatten()->first());
            }
        }

        if ($template->renderer === OriginalCertificateRenderer::ID) {
            return $this->originalResponse(app(OriginalCertificateRenderer::class)->render($template->layout, [], true));
        }

        return $this->pdf($template->title, $template->body);
    }

    public function request(ServiceRequest $serviceRequest)
    {
        Gate::authorize('view', $serviceRequest);
        $actor = request()->user();
        $manager = OperationalAccess::allows($actor, 'manage_requests');
        if (! $manager) {
            abort_unless($actor?->hasRole('homeowner') && $actor->hasVerifiedEmail() && $actor->homeowner()->whereKey($serviceRequest->homeowner_id)->exists(), 403);
        }
        abort_unless(in_array($serviceRequest->request_type, CertificateTypeCatalog::ALLOWED, true), 404);
        if ($serviceRequest->request_type === CohabitationCertificateData::TYPE) {
            // Requests do not contain the verified declarations needed for the original PDF.
            // Only approved requests may enter the existing certificate preparation workflow.
            if ($manager) {
                $guard = Auth::getDefaultDriver();
                $panel = in_array($guard, ['admin', 'staff'], true) ? $guard : ($actor->hasRole('hoa_admin') ? 'admin' : 'staff');
                if ($serviceRequest->status === 'Approved') {
                    return redirect(CertificateResource::getUrl('create', ['request' => $serviceRequest->id], panel: $panel))
                        ->header('Cache-Control', 'private, no-store');
                }
                $returnUrl = ServiceRequestResource::getUrl('edit', ['record' => $serviceRequest->id], panel: $panel);
            } else {
                $returnUrl = HomeownerServiceRequestResource::getUrl('view', ['record' => $serviceRequest->id], panel: 'homeowner');
            }

            return response()->view('certificates.request-guidance', compact('serviceRequest', 'returnUrl'))
                ->header('Cache-Control', 'private, no-store')
                ->header('X-Content-Type-Options', 'nosniff');
        }
        if ($serviceRequest->request_type === 'Certificate of Residency') {
            try {
                $template = app(CertificateTemplates::class)->assertIssuable('Certificate of Residency');
            } catch (ValidationException $exception) {
                abort(422, collect($exception->errors())->flatten()->first());
            }
        } else {
            $template = CertificateTemplate::query()->where('type', $serviceRequest->request_type)->orderByDesc('version')->first();
        }
        if ($template?->renderer === OriginalCertificateRenderer::ID) {
            $renderer = app(OriginalCertificateRenderer::class);
            $snapshot = ['resident_name' => $serviceRequest->homeowner->user->full_name,
                'address' => $serviceRequest->address_snapshot ?? $serviceRequest->homeowner->getAttributes(),
                'purpose' => $serviceRequest->details];

            return $this->originalResponse($renderer->render($template->layout, $renderer->values($snapshot, now()), true));
        }
        $body = $template?->body ?? 'Draft wording has not yet been prepared by the HOA.';
        $body = app(CertificateTemplates::class)->render($body, [
            'resident_name' => $serviceRequest->homeowner->user->full_name,
            'address' => implode(', ', array_filter($serviceRequest->address_snapshot ?? [])),
            'purpose' => $serviceRequest->details, 'issuance_date' => 'DRAFT — not issued',
            'cohabitant_one' => $serviceRequest->cohabitant_one, 'cohabitant_two' => $serviceRequest->cohabitant_two,
        ]);

        return $this->pdf($template?->title ?? $serviceRequest->request_type, $body);
    }

    private function originalResponse(string $bytes)
    {
        return response($bytes)->withHeaders([
            'Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store',
            'Content-Disposition' => 'inline; filename="hoa-draft.pdf"', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function pdf(string $title, string $body)
    {
        return Pdf::loadView('pdf.certificate-draft', compact('title', 'body'))->setPaper('a4')
            ->setOption(['isRemoteEnabled' => false, 'isPhpEnabled' => false])->stream('hoa-draft.pdf', ['Attachment' => false])->header('Cache-Control', 'private, no-store');
    }
}
