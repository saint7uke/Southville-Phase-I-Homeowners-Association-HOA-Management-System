<?php

declare(strict_types=1);

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;
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
            'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()],
            'house_number' => ['required', 'string', 'max:50'],
            'street' => ['required', 'string', 'max:100'],
            'block' => ['nullable', 'string', 'max:20'],
            'lot' => ['nullable', 'string', 'max:20'],
            'residency_date' => ['required', 'date', 'before_or_equal:today'],
            'ownership_type' => ['required', 'in:Owner,Tenant,Co-owner'],
            'privacy_consent' => ['accepted'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $cleanName = static fn (?string $value): ?string => filled($value) ? mb_convert_case(trim($value), MB_CASE_TITLE, 'UTF-8') : null;
        $this->merge([
            'first_name' => $cleanName($this->string('first_name')->toString()),
            'middle_name' => $cleanName($this->input('middle_name')),
            'last_name' => $cleanName($this->string('last_name')->toString()),
            'email' => mb_strtolower(trim($this->string('email')->toString())),
            'contact_number' => preg_replace('/\D/', '', $this->string('contact_number')->toString()),
        ]);
    }
}
