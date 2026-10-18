<?php

declare(strict_types=1);

namespace App\Http\Requests\Portal;

use App\Services\LandlordMatcher;
use App\Services\PropertyAddressAvailability;
use App\Support\PersonName;
use App\Support\PropertyAddress;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

final class RegisterHomeownerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $name = ['required', 'string', 'max:100', "regex:/^[\pL\s'.-]+$/u"];

        return [
            'first_name' => $name,
            'middle_name' => ['nullable', 'string', 'max:100', "regex:/^[\pL\s'.-]+$/u"],
            'last_name' => $name,
            'suffix' => ['nullable', 'in:Jr.,Sr.,II,III,IV,N/A'],
            'sex' => ['required', 'in:Male,Female,Prefer not to say'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'contact_number' => ['required', 'regex:/^09\d{9}$/'],
            'email' => [
                'required',
                'email:rfc',
                'max:255',
                Rule::unique('users', 'email'),
                Rule::unique('user_email_reservations', 'email'),
            ],
            'password' => ['required', 'confirmed', Password::default()],
            'house_number' => ['required', 'string', 'max:50'],
            'street' => ['required', 'string', 'max:100'],
            'block' => ['required', 'string', 'max:20'],
            'lot' => [
                'required',
                'string',
                'max:20',
            ],
            'residency_date' => ['required_if:ownership_type,Owner,Co-owner', 'nullable', 'date', 'before_or_equal:today'],
            'ownership_type' => ['required', 'in:Owner,Tenant,Co-owner'],
            'landlord_name' => ['required_if:ownership_type,Tenant', 'nullable', 'string', 'max:100'],
            'landlord_contact' => ['required_if:ownership_type,Tenant', 'nullable', 'regex:/^09\d{9}$/'],
            'lease_start_date' => ['required_if:ownership_type,Tenant', 'nullable', 'date'],
            'lease_end_date' => ['required_if:ownership_type,Tenant', 'nullable', 'date', 'after:lease_start_date'],
            'verification_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'privacy_consent' => ['accepted'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $address = PropertyAddress::normalizeForStorage([
            'house_number' => $this->input('house_number'),
            'street' => $this->input('street'),
            'block' => $this->input('block'),
            'lot' => $this->input('lot'),
            'phase' => PropertyAddress::DEFAULT_PHASE,
        ]);

        $this->merge([
            'first_name' => PersonName::normalize($this->string('first_name')->toString()),
            'middle_name' => PersonName::normalize($this->input('middle_name')),
            'last_name' => PersonName::normalize($this->string('last_name')->toString()),
            'email' => mb_strtolower(trim($this->string('email')->toString())),
            'contact_number' => preg_replace('/\D/', '', $this->string('contact_number')->toString()),
            'landlord_name' => PersonName::normalize($this->input('landlord_name')),
            'landlord_contact' => preg_replace('/\D/', '', $this->string('landlord_contact')->toString()),
            ...$address,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->hasAny(['house_number', 'street', 'block', 'lot', 'ownership_type'])) {
                return;
            }

            if (! app(PropertyAddressAvailability::class)->available($this->all())) {
                $validator->errors()->add('address', __(PropertyAddressAvailability::UNAVAILABLE_MESSAGE));
            }

            if ($this->input('ownership_type') !== 'Tenant'
                || $validator->errors()->hasAny(['landlord_name', 'house_number', 'street', 'block', 'lot'])) {
                return;
            }

            $match = app(LandlordMatcher::class)->match($this->all());
            if (! $match['valid']) {
                $validator->errors()->add('landlord_name', __($match['message']));
            }
        });
    }
}
