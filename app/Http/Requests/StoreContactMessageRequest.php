<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\ContactMessages\CreateResidentInquiry;
use Illuminate\Foundation\Http\FormRequest;

final class StoreContactMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return CreateResidentInquiry::eligible($this->user());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:160'],
            'message' => ['required', 'string', 'max:5000'],
            'website' => ['nullable', 'max:0'],
        ];
    }
}
