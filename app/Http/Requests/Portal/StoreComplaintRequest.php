<?php

declare(strict_types=1);

namespace App\Http\Requests\Portal;

use App\Validation\ComplaintSubmissionValidator;
use Illuminate\Foundation\Http\FormRequest;

final class StoreComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('submit_own_complaint') ?? false;
    }

    public function rules(): array
    {
        return ComplaintSubmissionValidator::rules();
    }

    public function messages(): array
    {
        return ComplaintSubmissionValidator::messages();
    }

    protected function prepareForValidation(): void
    {
        $this->merge(app(ComplaintSubmissionValidator::class)->normalize($this->all()));
    }
}
