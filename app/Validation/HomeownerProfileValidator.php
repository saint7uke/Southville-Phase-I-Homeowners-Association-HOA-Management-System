<?php

declare(strict_types=1);

namespace App\Validation;

use App\Models\Homeowner;
use App\Models\User;
use App\Support\PersonName;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class HomeownerProfileValidator
{
    /** @var list<string> */
    public const PROFILE_FIELDS = [
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'sex',
        'date_of_birth',
        'contact_number',
        'email',
        'house_number',
        'street',
        'block',
        'lot',
        'phase',
        'residency_date',
        'ownership_type',
        'emergency_contact_name',
        'emergency_contact_number',
        'profile_photo_upload',
    ];

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function normalize(array $input): array
    {
        $data = Arr::only($input, self::PROFILE_FIELDS);

        foreach (['first_name', 'middle_name', 'last_name', 'emergency_contact_name'] as $field) {
            $data[$field] = PersonName::normalize($data[$field] ?? null);
        }

        foreach (['house_number', 'street', 'phase'] as $field) {
            $data[$field] = $this->collapseWhitespace($data[$field] ?? null);
        }

        foreach (['block', 'lot'] as $field) {
            $value = $this->collapseWhitespace($data[$field] ?? null);
            $data[$field] = $value === null ? null : mb_strtoupper($value, 'UTF-8');
        }

        $data['suffix'] = $this->collapseWhitespace($data['suffix'] ?? null);
        $data['email'] = mb_strtolower(trim((string) ($data['email'] ?? '')), 'UTF-8');
        $data['contact_number'] = preg_replace('/\D/', '', (string) ($data['contact_number'] ?? ''));
        $data['emergency_contact_number'] = preg_replace('/\D/', '', (string) ($data['emergency_contact_number'] ?? ''));

        return $data;
    }

    /** @param array<string, mixed> $normalized @return array<string, list<mixed>> */
    public function rules(User $user, Homeowner $homeowner, array $normalized = []): array
    {
        $name = ['required', 'string', 'max:100', "regex:/^[\pL\s'.-]+$/u"];

        return [
            'first_name' => $name,
            'middle_name' => ['nullable', 'string', 'max:100', "regex:/^[\pL\s'.-]+$/u"],
            'last_name' => $name,
            'suffix' => ['nullable', Rule::in(['Jr.', 'Sr.', 'II', 'III', 'IV', 'N/A'])],
            'sex' => ['required', Rule::in(['Male', 'Female', 'Prefer not to say'])],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'contact_number' => ['required', 'regex:/^09\d{9}$/'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'house_number' => ['required', 'string', 'max:50'],
            'street' => ['required', 'string', 'max:100'],
            'block' => ['required', 'string', 'max:20'],
            'lot' => [
                'required',
                'string',
                'max:20',
                Rule::unique('homeowners', 'lot')
                    ->where(fn (Builder $query): Builder => $query->where('block', (string) ($normalized['block'] ?? $homeowner->block)))
                    ->ignore($homeowner->id),
            ],
            'phase' => ['required', 'string', 'max:50'],
            'residency_date' => ['required', 'date', 'before_or_equal:today'],
            'ownership_type' => ['required', Rule::in(['Owner', 'Tenant', 'Co-owner'])],
            'emergency_contact_name' => $name,
            'emergency_contact_number' => ['required', 'regex:/^09\d{9}$/'],
            'profile_photo_upload' => [
                'nullable',
                'file',
                'max:2048',
                'mimetypes:image/jpeg,image/png,image/webp',
                'extensions:jpg,jpeg,png,webp',
                'dimensions:min_width=100,min_height=100,max_width=4000,max_height=4000',
            ],
        ];
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function validate(User $user, Homeowner $homeowner, array $input): array
    {
        $normalized = $this->normalize($input);

        return Validator::make($normalized, $this->rules($user, $homeowner, $normalized))->validate();
    }

    private function collapseWhitespace(mixed $value): ?string
    {
        $collapsed = preg_replace('/\s+/u', ' ', trim((string) $value));

        return filled($collapsed) ? $collapsed : null;
    }
}
