<?php

declare(strict_types=1);

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

final class StoreServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('submit_own_request') ?? false;
    }

    public function rules(): array
    {
        return ['request_type' => ['required', 'in:Certificate of Residency,HOA Clearance,Gate Pass,Facility Reservation,Other'], 'details' => ['nullable', 'string', 'max:3000']];
    }
}
