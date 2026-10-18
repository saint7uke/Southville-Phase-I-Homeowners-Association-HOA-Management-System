<?php

declare(strict_types=1);

namespace App\Actions\Certificates;

use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\CertificateTypeCatalog;
use App\Support\CertificateTypeName;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
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
        if (! $issuer->can('manage_requests')) {
            throw new AuthorizationException;
        }

        $validated = Validator::make($data, [
            'homeowner_id' => ['required', 'integer', Rule::exists('homeowners', 'id')->whereNull('deleted_at')],
            'type' => ['required', 'string', 'max:64'],
            'custom_type' => ['nullable', 'string', 'max:64', 'not_regex:/[\p{C}]/u'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'expires_at' => ['prohibited'],
            'service_request_id' => ['prohibited'],
        ])->validate();

        $isCustom = $validated['type'] === CertificateTypeCatalog::OTHER_VALUE;
        if ($isCustom) {
            if (! $issuer->hasRole('hoa_admin')) {
                throw ValidationException::withMessages(['type' => 'Only administrators may add a new certificate type.']);
            }

            $customType = CertificateTypeName::normalize($validated['custom_type'] ?? null);
            if ($customType === '') {
                throw ValidationException::withMessages(['custom_type' => 'Enter a custom certificate type.']);
            }

            if ($this->types->findByName($customType) !== null || $this->types->isReservedName($customType)) {
                throw ValidationException::withMessages(['custom_type' => 'This certificate type already exists. Select it from the Type list.']);
            }

            $validated['custom_type'] = $customType;
        } else {
            if (filled($validated['custom_type'] ?? null)) {
                throw ValidationException::withMessages(['custom_type' => 'A custom type may only be entered when Other is selected.']);
            }

            if ($this->types->findByName($validated['type'], true) === null) {
                throw ValidationException::withMessages(['type' => 'Select an active certificate type.']);
            }
        }

        return $this->create($validated, $issuer, $isCustom);
    }

    public function fromApprovedRequest(ServiceRequest $request, User $issuer, ?string $purpose = null): Certificate
    {
        if (! $issuer->can('manage_requests')) {
            throw new AuthorizationException;
        }

        $purpose = filled($purpose) ? trim($purpose) : null;
        Validator::make(['purpose' => $purpose], ['purpose' => ['nullable', 'string', 'max:255']])->validate();

        $storedPath = null;

        try {
            return DB::transaction(function () use ($request, $issuer, $purpose, &$storedPath): Certificate {
                $request = ServiceRequest::query()->lockForUpdate()->findOrFail($request->getKey());

                $certificateType = $this->types->findByName($request->request_type);
                if ($request->status !== 'Approved' || $certificateType === null) {
                    throw new AuthorizationException;
                }

                $certificate = $this->persist([
                    'homeowner_id' => $request->homeowner_id,
                    'service_request_id' => $request->id,
                    'certificate_type_id' => $certificateType->id,
                    'type' => $certificateType->name,
                    'purpose' => $purpose ?? Str::limit((string) $request->details, 255, ''),
                ], $issuer);
                $storedPath = $certificate->file_path;

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
                        throw ValidationException::withMessages(['type' => 'Select an active certificate type.']);
                    }
                }

                unset($data['custom_type']);
                $data['certificate_type_id'] = $certificateType->id;
                $data['type'] = $certificateType->name;
                $certificate = $this->persist($data, $issuer);
                $storedPath = $certificate->file_path;

                return $certificate;
            });
        } catch (Throwable $exception) {
            if (is_string($storedPath)) {
                Storage::disk('local')->delete($storedPath);
            }

            if ($isCustom && $exception instanceof QueryException && $this->types->findByName($data['custom_type'] ?? null) !== null) {
                throw ValidationException::withMessages(['custom_type' => 'This certificate type already exists. Select it from the Type list.']);
            }

            throw $exception;
        }
    }

    /** @param array<string, mixed> $data */
    private function persist(array $data, User $issuer): Certificate
    {
        $issuedAt = now();
        $certificate = Certificate::query()->create([
            ...$data,
            'certificate_number' => app(NextCertificateNumber::class)->handle((int) $issuedAt->format('Y')),
            'status' => 'Issued',
            'issued_at' => $issuedAt,
            'expires_at' => $issuedAt->copy()->endOfYear(),
            'issued_by' => $issuer->id,
        ]);

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
