<?php

declare(strict_types=1);

namespace App\Http\Requests\Portal;

use App\Models\ServiceRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('submit_own_request') ?? false;
    }

    public function rules(): array
    {
        return [
            'request_type' => ['required', Rule::in(ServiceRequest::TYPES)],
            'subject' => ['required', 'string', 'max:160'],
            'details' => ['required', 'string', 'max:3000'],
            'attachments' => ['nullable', 'array', 'max:3'],
            'attachments.*' => ['file', 'max:5120', 'mimetypes:application/pdf,image/jpeg,image/png,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'extensions:pdf,jpg,jpeg,png,doc,docx'],
        ];
    }
}
