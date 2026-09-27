<?php

declare(strict_types=1);

namespace App\Actions\Certificates;

use App\Models\Certificate;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

final class IssueCertificate
{
    /** @param array<string, mixed> $data */
    public function handle(array $data, User $issuer): Certificate
    {
        if (! $issuer->can('manage_requests')) {
            throw new AuthorizationException;
        }

        $validated = Validator::make($data, [
            'homeowner_id' => ['required', 'integer', Rule::exists('homeowners', 'id')->whereNull('deleted_at')],
            'type' => ['required', Rule::in(ServiceRequest::CERTIFICATE_TYPES)],
            'purpose' => ['nullable', 'string', 'max:255'],
            'expires_at' => ['prohibited'],
            'service_request_id' => ['prohibited'],
        ])->validate();

        return $this->create($validated, $issuer);
    }

    public function fromApprovedRequest(ServiceRequest $request, User $issuer, ?string $purpose = null): Certificate
    {
        if (! $issuer->can('manage_requests')) {
            throw new AuthorizationException;
        }

        $storedPath = null;

        try {
            return DB::transaction(function () use ($request, $issuer, $purpose, &$storedPath): Certificate {
                $request = ServiceRequest::query()->lockForUpdate()->findOrFail($request->getKey());

                if ($request->status !== 'Approved' || ! in_array($request->request_type, ServiceRequest::CERTIFICATE_TYPES, true)) {
                    throw new AuthorizationException;
                }

                $certificate = $this->persist([
                    'homeowner_id' => $request->homeowner_id,
                    'service_request_id' => $request->id,
                    'type' => $request->request_type,
                    'purpose' => $purpose ?: $request->details,
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
    private function create(array $data, User $issuer): Certificate
    {
        $storedPath = null;

        try {
            return DB::transaction(function () use ($data, $issuer, &$storedPath): Certificate {
                $certificate = $this->persist($data, $issuer);
                $storedPath = $certificate->file_path;

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

        $certificate->update([
            'file_path' => app(StoreCertificateDocument::class)->handle($certificate),
        ]);

        return $certificate;
    }
}
