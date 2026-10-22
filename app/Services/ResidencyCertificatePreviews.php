<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CertificatePreview;
use App\Models\Homeowner;
use App\Models\ServiceRequest;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\OperationalAccess;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ResidencyCertificatePreviews
{
    public function prepare(array $data, User $actor, ?int $requestId = null): string
    {
        OperationalAccess::authorize($actor, 'manage_requests');
        app(CertificateTemplates::class)->assertSchema(true);
        $payload = $this->payload($data, $requestId);
        $snapshot = $this->context($payload, actor: $actor, stage: true);
        // Fail on fit/font/source errors before creating even a preview record.
        app(OriginalCertificateRenderer::class)->render($snapshot['layout'], app(OriginalCertificateRenderer::class)->values($snapshot, now()), true);
        $token = Str::random(64);
        CertificatePreview::create(['token_hash' => hash('sha256', $token), 'actor_id' => $actor->id,
            'payload' => $payload, 'snapshot' => $snapshot, 'fingerprint' => $this->fingerprint($snapshot), 'expires_at' => now()->addMinutes(15)]);

        return $token;
    }

    public function read(string $token, User $actor): CertificatePreview
    {
        OperationalAccess::authorize($actor, 'manage_requests');
        app(CertificateTemplates::class)->assertSchema(true);
        $preview = CertificatePreview::find(hash('sha256', $token));
        $this->assertOwner($preview, $actor);
        if ($preview->certificate_id === null && ! $this->matchesFingerprint($preview->fingerprint, $this->context($preview->payload, actor: $actor))) {
            $this->invalid();
        }

        return $preview;
    }

    /** Must run inside the issuance transaction; holds the one-time confirmation row lock. */
    public function lockAndValidate(?string $token, array $data, User $actor, ?int $requestId = null): CertificatePreview
    {
        OperationalAccess::authorize($actor, 'manage_requests');
        app(CertificateTemplates::class)->assertSchema(true);
        $preview = CertificatePreview::whereKey(hash('sha256', $token ?? ''))->lockForUpdate()->first();
        $this->assertOwner($preview, $actor);
        if ($preview->payload != $this->payload($data, $requestId)) {
            $this->invalid();
        }
        if ($preview->certificate_id === null && ! $this->matchesFingerprint($preview->fingerprint, $this->context($preview->payload, true, $actor))) {
            $this->invalid();
        }

        return $preview;
    }

    private function assertOwner(?CertificatePreview $preview, User $actor): void
    {
        if ($preview === null || $preview->actor_id !== $actor->id || $preview->expires_at->isPast()) {
            $this->invalid();
        }
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['preview_token' => __('The preview expired or its information changed. Generate a fresh preview before issuing.')]);
    }

    private function payload(array $data, ?int $requestId): array
    {
        $validated = Validator::make($data, ['homeowner_id' => ['required', 'integer'], 'purpose' => ['nullable', 'string', 'max:255']])->validate();

        $type = $data['type'] ?? 'Certificate of Residency';
        if (! in_array($type, CertificateTypeCatalog::ALLOWED, true)) {
            throw ValidationException::withMessages(['type' => __('Select an active certificate type.')]);
        }

        return ['homeowner_id' => (int) $validated['homeowner_id'], 'type' => $type,
            'purpose' => trim($validated['purpose'] ?? ''), 'service_request_id' => $requestId,
            ...($type === CohabitationCertificateData::TYPE ? Arr::only($data, CohabitationCertificateData::FIELDS) : [])];
    }

    private function context(array $payload, bool $lock = false, ?User $actor = null, bool $stage = false): array
    {
        $request = $payload['service_request_id'] ? ServiceRequest::query()->when($lock, fn ($q) => $q->lockForUpdate())->find($payload['service_request_id']) : null;
        if ($payload['service_request_id'] && ($request === null || $request->status !== 'Approved' || $request->request_type !== $payload['type'] || $request->homeowner_id !== $payload['homeowner_id'])) {
            $this->invalid();
        }
        $settings = SystemSetting::current();
        if ($lock) {
            $settings = SystemSetting::query()->whereKey($settings->id)->lockForUpdate()->firstOrFail();
        }
        $owner = Homeowner::find($payload['homeowner_id']);
        $user = $owner ? User::query()->when($lock, fn ($q) => $q->lockForUpdate())->find($owner->user_id) : null;
        if ($owner === null || $user === null) {
            throw ValidationException::withMessages(['homeowner_id' => __('Select a current resident with a non-deleted account.')]);
        }
        if ($lock) {
            $owner = Homeowner::whereKey($owner->id)->lockForUpdate()->firstOrFail();
        }
        $owner->setRelation('user', $user);
        if ($payload['type'] === CohabitationCertificateData::TYPE) {
            $data = app(CohabitationCertificateData::class)->validate($payload, $owner, $request, $lock);
            $source = app(CohabitationCertificateSource::class);
            $template = $lock ? $source->approved() : ($stage ? $source->draft($actor) : $source->previewTemplate());
            app(OriginalCertificateRenderer::class)->assertYear($template->layout, now());
            $layout = $template->layout;
            // Approval metadata is not document content: a reviewed draft can be confirmed later.
            unset($layout['signatory_confirmation']);
            $font = app(ResidencyFont::class)->checksum('cohabitation');
            if (! hash_equals($layout['font_sha256'], $font)) {
                $this->invalid();
            }

            return ['resident_name' => $user->full_name, 'address' => $data['address'], 'purpose' => $payload['purpose'],
                'registered_address' => Arr::only($owner->getAttributes(), ['house_number', 'street', 'block', 'lot', 'phase', 'phase_suffix']),
                'cohabitation' => $data, 'template_id' => $template->id, 'template_version' => $template->version,
                'layout' => $layout, 'signatory_name' => $layout['printed_signatory_name'], 'signatory_title' => $layout['printed_signatory_title'],
                'issuance_date' => now()->toDateString()];
        }
        $template = app(CertificateTemplates::class)->assertIssuable('Certificate of Residency');
        app(OriginalCertificateRenderer::class)->assertYear($template->layout, now());
        $font = app(ResidencyFont::class)->checksum();
        $address = $request?->address_snapshot ?? Arr::only($owner->getAttributes(), ['house_number', 'street', 'block', 'lot', 'phase', 'phase_suffix']);

        return ['resident_name' => $user->full_name, 'address' => $address, 'purpose' => $payload['purpose'],
            'registered_address' => Arr::only($owner->getAttributes(), ['house_number', 'street', 'block', 'lot', 'phase', 'phase_suffix']),
            'template_id' => $template->id, 'template_version' => $template->version,
            'layout' => [...$template->layout, 'font_sha256' => $font],
            'signatory_name' => $settings->certificate_signatory_name, 'signatory_title' => $settings->certificate_signatory_title,
            'signatory_confirmation' => $settings->signatory_confirmed_at,
            'issuance_date' => now()->toDateString()];
    }

    private function fingerprint(array $snapshot): string
    {
        // MySQL's JSON storage reorders object keys. Key order is not document content.
        return hash('sha256', json_encode($this->canonical($snapshot), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    private function matchesFingerprint(string $fingerprint, array $snapshot): bool
    {
        // Preserve still-valid 15-minute previews made before canonical fingerprints were introduced.
        return hash_equals($fingerprint, $this->fingerprint($snapshot))
            || hash_equals($fingerprint, hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)));
    }

    private function canonical(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->canonical($item);
            }
        }
        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return $value;
    }
}
