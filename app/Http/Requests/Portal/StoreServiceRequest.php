<?php

declare(strict_types=1);

namespace App\Http\Requests\Portal;

use App\Validation\ServiceRequestSubmissionValidator;
use Illuminate\Foundation\Http\FormRequest;

final class StoreServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('submit_own_request') ?? false;
    }

    public function rules(): array
    {
        return app(ServiceRequestSubmissionValidator::class)->rules();
    }

    public function messages(): array
    {
        return ServiceRequestSubmissionValidator::messages();
    }
}
