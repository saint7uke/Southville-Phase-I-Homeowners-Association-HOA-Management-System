<?php

declare(strict_types=1);

namespace App\Http\Requests\Portal;

use App\Models\Homeowner;
use App\Models\User;
use App\Validation\HomeownerProfileValidator;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update_own_profile') ?? false;
    }

    public function rules(): array
    {
        $user = $this->user();
        $homeowner = $user?->homeowner;

        if (! $user instanceof User || ! $homeowner instanceof Homeowner) {
            return [];
        }

        return [
            ...app(HomeownerProfileValidator::class)->rules($user, $homeowner, $this->all()),
            'confirm_profile_update' => ['accepted'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return app(HomeownerProfileValidator::class)->messages();
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return app(HomeownerProfileValidator::class)->attributes();
    }

    protected function prepareForValidation(): void
    {
        $input = $this->all();
        $normalized = app(HomeownerProfileValidator::class)->normalize($input);

        if ($this->user()?->homeowner instanceof Homeowner) {
            foreach (['house_number', 'street', 'block', 'lot', 'phase', 'phase_suffix'] as $field) {
                if (! array_key_exists($field, $input)) {
                    $normalized[$field] = $this->user()->homeowner->{$field};
                }
            }
        }

        $this->replace([
            ...$input,
            ...$normalized,
        ]);
    }
}
