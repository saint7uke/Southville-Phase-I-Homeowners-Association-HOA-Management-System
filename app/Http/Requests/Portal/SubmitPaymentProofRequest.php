<?php

declare(strict_types=1);

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

final class SubmitPaymentProofRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('submit_own_payment_proof') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount_paid' => ['required', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'covered_period' => ['required', 'string', 'max:40'],
            'payment_method' => ['required', 'in:Bank Transfer,GCash,Maya'],
            'notes' => ['nullable', 'string', 'max:500'],
            'proof' => ['required', File::types(['pdf', 'jpg', 'jpeg', 'png'])->max('5mb')],
        ];
    }
}
