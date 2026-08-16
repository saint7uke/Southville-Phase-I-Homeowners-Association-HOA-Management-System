<?php

declare(strict_types=1);

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

final class StoreComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('submit_own_complaint') ?? false;
    }

    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'min:20', 'max:5000'],
            'category' => ['required', 'in:Noise,Property Damage,Neighbor Dispute,Common Area,Security,Others'],
            'priority' => ['required', 'in:Low,Medium,High'],
            'attachment' => ['nullable', 'file', 'max:5120', 'mimetypes:application/pdf,image/jpeg,image/png,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'extensions:pdf,jpg,jpeg,png,doc,docx'],
        ];
    }
}
