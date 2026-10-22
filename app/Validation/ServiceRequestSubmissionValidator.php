<?php

declare(strict_types=1);

namespace App\Validation;

use App\Models\Homeowner;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\CertificateTypeCatalog;
use App\Support\CertificateTypeName;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class ServiceRequestSubmissionValidator
{
    public function __construct(private readonly CertificateTypeCatalog $certificateTypes) {}

    /** @return array<string, array<int, mixed>> */
    public function rules(bool $allowAssignment = false): array
    {
        return [
            'request_type' => ['required', 'string', Rule::in($this->certificateTypes->requestTypes())],
            'subject' => ['required', 'string', 'max:160'],
            'details' => ['required', 'string', 'max:3000'],
            'handled_by' => $allowAssignment
                ? ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->whereNull('deleted_at')->where('account_status', 'Active'))]
                : ['prohibited'],
            ...CaseAttachmentRules::rules(),
            'homeowner_id' => ['prohibited'],
            'ticket_number' => ['prohibited'],
            'status' => ['prohibited'],
            'admin_remarks' => ['prohibited'],
            'document_output' => ['prohibited'],
            'completed_at' => ['prohibited'],
            'grandfathered' => ['prohibited'],
            'processed_by' => ['prohibited'],
            'processed_at' => ['prohibited'],
            'approved_by' => ['prohibited'],
            'approved_at' => ['prohibited'],
            'address_snapshot' => ['prohibited'],
            'cohabitant_one' => ['required_if:request_type,Certificate of Cohabitation', 'nullable', 'string', 'max:160', "regex:/^[\pL\s'.-]+$/u"],
            'cohabitant_two' => ['required_if:request_type,Certificate of Cohabitation', 'nullable', 'string', 'max:160', "regex:/^[\pL\s'.-]+$/u"],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return array_map(fn (string $message): string => __($message), [
            'request_type.in' => 'Select a valid service request type.',
            'handled_by.exists' => 'Select an active HOA staff member.',
            ...CaseAttachmentRules::messages(),
        ]);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function normalize(array $data): array
    {
        foreach (['request_type', 'subject', 'details', 'cohabitant_one', 'cohabitant_two'] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        if (filled($data['request_type'] ?? null)) {
            $catalogType = $this->certificateTypes->findByName($data['request_type'], activeOnly: true);
            if ($catalogType !== null) {
                $data['request_type'] = $catalogType->name;
            } else {
                $submittedKey = CertificateTypeName::key($data['request_type']);
                foreach (ServiceRequest::NON_CERTIFICATE_TYPES as $type) {
                    if (CertificateTypeName::key($type) === $submittedKey) {
                        $data['request_type'] = $type;
                        break;
                    }
                }
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $files
     * @return array<string, mixed>
     */
    public function validate(Homeowner $homeowner, array $data, array $files, User $actor): array
    {
        $data = $this->normalize($data);
        $data['attachments'] = $files;

        $validator = Validator::make($data, $this->rules($actor->hasRole('hoa_admin')), self::messages());
        $validator->after(function ($validator) use ($homeowner, $actor, $data): void {
            $resident = User::withTrashed()->find($homeowner->user_id);
            if (! $homeowner->exists || $homeowner->trashed() || $resident === null || $resident->trashed()) {
                $validator->errors()->add('homeowner_id', __('Select a current resident.'));
            }

            if (! $actor->hasRole('hoa_admin') && $homeowner->user_id !== $actor->id) {
                $validator->errors()->add('homeowner_id', __('You cannot submit a service request for this resident.'));
            }

            if ($actor->hasRole('hoa_admin') && filled($data['handled_by'] ?? null)) {
                $handler = User::query()->find($data['handled_by']);
                if ($handler === null || $handler->account_status !== 'Active' || ! $handler->hasRole('hoa_staff')) {
                    $validator->errors()->add('handled_by', __('Select an active HOA staff member.'));
                }
            }
        });

        $validated = $validator->validate();
        unset($validated['attachments']);
        if (($validated['request_type'] ?? null) === 'Certificate of Cohabitation') {
            $validated['address_snapshot'] = Arr::only($homeowner->getAttributes(), ['house_number', 'street', 'block', 'lot', 'phase', 'phase_suffix']);
        }

        return $validated;
    }
}
