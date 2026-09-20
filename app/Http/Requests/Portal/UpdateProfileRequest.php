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

    protected function prepareForValidation(): void
    {
        $this->replace([
            ...$this->all(),
            ...app(HomeownerProfileValidator::class)->normalize($this->all()),
        ]);
    }
}
