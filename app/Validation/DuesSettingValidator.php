<?php

declare(strict_types=1);

namespace App\Validation;

use App\Support\DuesSettingName;
use Illuminate\Support\Facades\Validator;

final class DuesSettingValidator
{
    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function validate(array $data): array
    {
        $data['name'] = DuesSettingName::display($data['name'] ?? null);
        $data['description'] = filled($data['description'] ?? null) ? trim((string) $data['description']) : null;

        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:120', 'regex:/^[^\p{C}]+$/u'],
            'amount' => ['required', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/', 'decimal:0,2', 'gt:0'],
            'frequency' => ['required', 'in:Monthly,Annual,Special Assessment'],
            'due_day' => ['required', 'integer', 'between:1,31'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ], [
            'name.regex' => __('The dues name may contain only printable characters.'),
            'amount.regex' => __('Enter a valid PHP amount with no more than two decimal places.'),
            'starts_on.required' => __('An effective start date is required.'),
        ])->validate();

        $validated['normalized_name'] = DuesSettingName::key($validated['name']);

        return $validated;
    }
}
