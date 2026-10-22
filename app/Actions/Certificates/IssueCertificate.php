<?php

declare(strict_types=1);

namespace App\Actions\Certificates;

use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Homeowner;
use App\Models\ServiceRequest;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\CertificateTemplates;
use App\Services\CertificateTypeCatalog;
use App\Services\CohabitationCertificateData;
use App\Services\OriginalCertificateRenderer;
use App\Services\ResidencyCertificatePreviews;
use App\Services\ResidencyFont;
use App\Support\CertificateTypeName;
use App\Support\OperationalAccess;
use App\Support\PropertyIdentity;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

final class IssueCertificate
{
    public function __construct(private readonly CertificateTypeCatalog $types) {}

    /** @param array<string, mixed> $data */
    public function handle(array $data, User $issuer): Certificate
    {
        OperationalAccess::authorize($issuer, 'manage_requests');
        if (! $this->types->allowsNew($data['type'] ?? null)) {
            throw ValidationException::withMessages(['type' => __('Only Certificate of Residency and Certificate of Cohabitation may be newly issued.')]);
        }
        if (! $issuer->can('manage_requests')) {
            throw new AuthorizationException;
        }

        $validated = Validator::make($data, [
            'homeowner_id' => ['required', 'integer', Rule::exists('homeowners', 'id')->whereNull('deleted_at')],
            'type' => ['required', 'string', 'max:64'],
            'custom_type' => ['nullable', 'string', 'max:64', 'not_regex:/[\p{C}]/u'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'preview_token' => ['nullable', 'string', 'size:64'],
            'expires_at' => ['prohibited'],
            'service_request_id' => ['prohibited'],
            'cohabitant_one' => ['required_if:type,Certificate of Cohabitation', 'nullable', 'string', 'max:160', "regex:/^[\pL\s'.-]+$/u"],
            'cohabitant_two' => ['required_if:type,Certificate of Cohabitation', 'nullable', 'string', 'max:160', "regex:/^[\pL\s'.-]+$/u"],
            ...(($data['type'] ?? null) === CohabitationCertificateData::TYPE ? app(CohabitationCertificateData::class)->rules() : []),
        ])->validate();

        $isCustom = $validated['type'] === CertificateTypeCatalog::OTHER_VALUE;
        if ($isCustom) {
            if (! $issuer->hasRole('hoa_admin')) {
                throw ValidationException::withMessages(['type' => __('Only administrators may add a new certificate type.')]);
            }

            $customType = CertificateTypeName::normalize($validated['custom_type'] ?? null);
            if ($customType === '') {
                throw ValidationException::withMessages(['custom_type' => __('Enter a custom certificate type.')]);
            }

            if ($this->types->findByName($customType) !== null || $this->types->isReservedName($customType)) {
                throw ValidationException::withMessages(['custom_type' => __('This certificate type already exists. Select it from the Type list.')]);
            }

            $validated['custom_type'] = $customType;
        } else {
            if (filled($validated['custom_type'] ?? null)) {
                throw ValidationException::withMessages(['custom_type' => __('A custom type may only be entered when Other is selected.')]);
            }

            if ($this->types->findByName($validated['type'], true) === null) {
                throw ValidationException::withMessages(['type' => __('Select an active certificate type.')]);
            }
        }

        return $this->create($validated, $issuer, $isCustom);
    }

    public function fromApprovedRequest(ServiceRequest $request, User $issuer, ?string $purpose = null, ?string $previewToken = null, array $documentData = []): Certificate
    {
        OperationalAccess::authorize($issuer, 'manage_requests');
        if (! $issuer->can('manage_requests')) {
            throw new AuthorizationException;
        }

        $purpose = filled($purpose) ? trim($purpose) : null;
        Validator::make(['purpose' => $purpose], ['purpose' => ['nullable', 'string', 'max:255']])->validate();

        $storedPath = null;

        try {
            return DB::transaction(function () use ($request, $issuer, $purpose, $previewToken, $documentData, &$storedPath): Certificate {
                $request = ServiceRequest::query()->lockForUpdate()->findOrFail($request->getKey());
                $resolvedPurpose = $purpose ?? Str::limit((string) $request->details, 255, '');
                $preview = in_array($request->request_type, CertificateTypeCatalog::ALLOWED, true)
                    ? app(ResidencyCertificatePreviews::class)->lockAndValidate($previewToken, [...$documentData, 'homeowner_id' => $request->homeowner_id, 'purpose' => $resolvedPurpose, 'type' => $request->request_type], $issuer, $request->id) : null;
                if ($preview?->certificate_id) {
                    return Certificate::findOrFail($preview->certificate_id);
                }

                $certificateType = $this->types->findByName($request->request_type);
                if ($request->status !== 'Approved' || $certificateType === null) {
                    throw new AuthorizationException;
                }
                if (! $this->types->allowsNew($request->request_type) && ! $request->grandfathered) {
                    throw ValidationException::withMessages(['request_type' => __('This retired request type is not eligible for new issuance.')]);
                }

                $certificate = $this->persist([
                    'homeowner_id' => $request->homeowner_id,
                    'service_request_id' => $request->id,
                    'certificate_type_id' => $certificateType->id,
                    'type' => $certificateType->name,
                    'purpose' => $resolvedPurpose,
                    'issuance_key' => $preview?->token_hash,
                    'cohabitant_one' => $request->cohabitant_one,
                    'cohabitant_two' => $request->cohabitant_two,
                    'address_snapshot' => $request->address_snapshot,
                    'cohabitation' => $preview?->snapshot['cohabitation'] ?? null,
                ], $issuer);
                $storedPath = $certificate->file_path;
                $preview?->update(['certificate_id' => $certificate->id]);

                $request->update([
                    'status' => 'Completed',
                    'document_output' => $certificate->certificate_number,
                ]);

                return $certificate;
            });
        } catch (Throwable $exception) {
            if (is_string($storedPath)) {
                Storage::disk('local')->delete($storedPath);
            }

            throw $exception;
        }
    }

    /** @param array<string, mixed> $data */
    private function create(array $data, User $issuer, bool $isCustom): Certificate
    {
        $storedPath = null;

        try {
            return DB::transaction(function () use ($data, $issuer, $isCustom, &$storedPath): Certificate {
                $preview = in_array($data['type'], CertificateTypeCatalog::ALLOWED, true) ? app(ResidencyCertificatePreviews::class)->lockAndValidate($data['preview_token'] ?? null, $data, $issuer) : null;
                if ($preview?->certificate_id) {
                    return Certificate::findOrFail($preview->certificate_id);
                }
                $data['issuance_key'] = $preview?->token_hash;
                $data['cohabitation'] = $preview?->snapshot['cohabitation'] ?? null;
                if ($isCustom) {
                    $name = (string) $data['custom_type'];
                    $certificateType = CertificateType::query()->create([
                        'name' => $name,
                        'normalized_key' => CertificateTypeName::key($name),
                        'is_active' => true,
                        'is_system' => false,
                        'created_by' => $issuer->id,
                    ]);
                } else {
                    $certificateType = $this->types->findByName($data['type'], true);
                    if ($certificateType === null) {
                        throw ValidationException::withMessages(['type' => __('Select an active certificate type.')]);
                    }
                }

                unset($data['custom_type']);
                $data['certificate_type_id'] = $certificateType->id;
                $data['type'] = $certificateType->name;
                $certificate = $this->persist($data, $issuer);
                $storedPath = $certificate->file_path;
                $preview?->update(['certificate_id' => $certificate->id]);

                return $certificate;
            });
        } catch (Throwable $exception) {
            if (is_string($storedPath)) {
                Storage::disk('local')->delete($storedPath);
            }

            if ($isCustom && $exception instanceof QueryException && $this->types->findByName($data['custom_type'] ?? null) !== null) {
                throw ValidationException::withMessages(['custom_type' => __('This certificate type already exists. Select it from the Type list.')]);
            }

            throw $exception;
        }
    }

    /** @param array<string, mixed> $data */
    private function persist(array $data, User $issuer): Certificate
    {
        // Keep signatory validation and its document snapshot consistent during configuration changes.
        $settings = SystemSetting::current();
        $settings = SystemSetting::query()->whereKey($settings->id)->lockForUpdate()->firstOrFail();
        $templates = app(CertificateTemplates::class);
        $template = $templates->assertIssuable($data['type']);
        $resident = Homeowner::query()->with('user')->findOrFail($data['homeowner_id']);
        if ($data['type'] === 'Certificate of Cohabitation' && (blank($data['cohabitant_one'] ?? null) || blank($data['cohabitant_two'] ?? null))) {
            throw ValidationException::withMessages(['type' => __('Both cohabiting residents must be identified before issuance.')]);
        }
        $issuedAt = now();
        if ($template?->renderer === OriginalCertificateRenderer::ID) {
            app(OriginalCertificateRenderer::class)->assertYear($template->layout, $issuedAt);
        }
        $address = $data['address_snapshot'] ?? Arr::only($resident->getAttributes(), ['house_number', 'street', 'block', 'lot', 'phase', 'phase_suffix']);
        $snapshot = [
            'hoa_name' => $settings->hoa_name, 'hoa_address' => $settings->address,
            'resident_name' => $resident->user->full_name, 'address' => $address,
            'full_address' => PropertyIdentity::address($address),
            'purpose' => $data['purpose'] ?? '', 'issuance_date' => $issuedAt->format('F j, Y'),
            'cohabitant_one' => $data['cohabitant_one'] ?? null, 'cohabitant_two' => $data['cohabitant_two'] ?? null,
            'signatory_name' => $settings->certificate_signatory_name, 'signatory_title' => $settings->certificate_signatory_title,
            'template_version' => $template?->version ?? 'legacy-residency', 'title' => $template?->title ?? $data['type'],
        ];
        if ($data['type'] === CohabitationCertificateData::TYPE) {
            $snapshot['cohabitation'] = $data['cohabitation'];
            $snapshot['cohabitant_one'] = $data['cohabitation']['cohabitant_one'];
            $snapshot['cohabitant_two'] = $data['cohabitation']['cohabitant_two'];
            $snapshot['signatory_name'] = $template->layout['printed_signatory_name'];
            $snapshot['signatory_title'] = $template->layout['printed_signatory_title'];
            $snapshot['signatory_confirmation'] = $template->layout['signatory_confirmation'];
        }
        $snapshot['body'] = $template ? $templates->render($template->body, [...$snapshot, 'address' => $snapshot['full_address']]) : null;
        if ($template?->renderer === OriginalCertificateRenderer::ID) {
            $snapshot['renderer'] = OriginalCertificateRenderer::ID;
            $snapshot['layout'] = $template->layout;
            $snapshot['layout']['font_sha256'] = app(ResidencyFont::class)->checksum($template->layout['font_profile'] ?? 'residency');
        }
        $certificate = new Certificate([
            ...Arr::only($data, ['homeowner_id', 'service_request_id', 'certificate_type_id', 'type', 'purpose']),
            'certificate_template_id' => $template?->id,
            'document_snapshot' => $snapshot,
            'certificate_number' => app(NextCertificateNumber::class)->handle((int) $issuedAt->format('Y')),
            'status' => 'Issued',
            'issued_at' => $issuedAt,
            'expires_at' => $issuedAt->copy()->endOfYear(),
            'issued_by' => $issuer->id,
        ]);
        $certificate->forceFill(['issuance_key' => $data['issuance_key'] ?? null])->save();

        $path = app(StoreCertificateDocument::class)->handle($certificate);
        try {
            $certificate->update(['file_path' => $path]);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return $certificate;
    }
}
