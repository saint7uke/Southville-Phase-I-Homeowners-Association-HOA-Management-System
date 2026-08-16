<?php

declare(strict_types=1);

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

final class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['email' => ['required', 'email:rfc', 'max:255'], 'password' => ['required', 'string', 'max:255'], 'remember' => ['nullable', 'boolean']];
    }
}
