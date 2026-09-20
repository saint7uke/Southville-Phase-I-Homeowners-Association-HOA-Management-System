<?php

declare(strict_types=1);

namespace App\Http\Requests\Portal;

use App\Support\PersonName;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

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
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::default()],
            'house_number' => ['required', 'string', 'max:50'],
            'street' => ['required', 'string', 'max:100'],
            'block' => ['required', 'string', 'max:20'],
            'lot' => [
                'required',
                'string',
                'max:20',
                Rule::unique('homeowners', 'lot')->where(fn (Builder $query): Builder => $query->where('block', (string) $this->input('block'))),
            ],
            'residency_date' => ['required', 'date', 'before_or_equal:today'],
            'ownership_type' => ['required', 'in:Owner,Tenant,Co-owner'],
            'privacy_consent' => ['accepted'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'first_name' => PersonName::normalize($this->string('first_name')->toString()),
            'middle_name' => PersonName::normalize($this->input('middle_name')),
            'last_name' => PersonName::normalize($this->string('last_name')->toString()),
            'email' => mb_strtolower(trim($this->string('email')->toString())),
            'contact_number' => preg_replace('/\D/', '', $this->string('contact_number')->toString()),
            'block' => mb_strtoupper(preg_replace('/\s+/u', ' ', trim($this->string('block')->toString())) ?: '', 'UTF-8'),
            'lot' => mb_strtoupper(preg_replace('/\s+/u', ' ', trim($this->string('lot')->toString())) ?: '', 'UTF-8'),
        ]);
    }
}
