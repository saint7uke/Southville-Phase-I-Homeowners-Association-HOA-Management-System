<?php

declare(strict_types=1);

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update_own_profile') ?? false;
    }

    public function rules(): array
    {
        return [
            'contact_number' => ['required', 'regex:/^09\d{9}$/'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($this->user()?->id)],
            'house_number' => ['required', 'string', 'max:50'],
            'street' => ['required', 'string', 'max:100'],
            'block' => ['nullable', 'string', 'max:20'],
            'lot' => ['nullable', 'string', 'max:20'],
        ];
    }
}
