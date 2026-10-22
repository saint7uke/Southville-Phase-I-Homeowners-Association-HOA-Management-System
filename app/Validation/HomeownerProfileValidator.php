<?php

declare(strict_types=1);

namespace App\Validation;

use App\Models\Homeowner;
use App\Models\User;
use App\Services\PropertyAddressAvailability;
use App\Support\PersonName;
use App\Support\PropertyAddress;
use App\Support\PropertyIdentity;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class HomeownerProfileValidator
{
    public const EMAIL_AVAILABLE_MESSAGE = 'This email address is available.';

    public const EMAIL_UNAVAILABLE_MESSAGE = 'This email address is already registered or reserved.';

    public const ADDRESS_AVAILABLE_MESSAGE = 'This address is available for your profile.';

    public const LINKED_ADDRESS_MESSAGE = 'This address cannot be changed while a landlord or tenant relationship is linked. Contact the HOA to reassign or unlink the relationship first.';

    public function __construct(private readonly PropertyAddressAvailability $addressAvailability) {}

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
        'phase_suffix',
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

        $data = [...$data, ...PropertyAddress::normalizeForStorage($data)];
        $data['house_number'] = filled($data['house_number']) ? $data['house_number'] : null;

        $data['suffix'] = $this->collapseWhitespace($data['suffix'] ?? null);
        $data['email'] = mb_strtolower(trim((string) ($data['email'] ?? '')), 'UTF-8');
        $data['contact_number'] = $this->normalizePhone($data['contact_number'] ?? '');
        $data['emergency_contact_number'] = $this->normalizePhone($data['emergency_contact_number'] ?? '');

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
            'email' => [
                'required',
                'email:rfc',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
                Rule::unique('user_email_reservations', 'email')->where(
                    fn (Builder $query): Builder => $query->where(
                        fn (Builder $query): Builder => $query
                            ->whereNull('user_id')
                            ->orWhere('user_id', '!=', $user->id),
                    ),
                ),
            ],
            'house_number' => ['nullable', 'string', 'max:50'],
            'street' => ['required', 'string', 'max:100'],
            'block' => ['required', 'string', 'max:20'],
            'lot' => ['required', 'string', 'max:20'],
            'residency_date' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:date_of_birth'],
            'ownership_type' => ['required', Rule::in([$homeowner->ownership_type])],
            'phase' => ['required', 'string', 'max:50', Rule::in([PropertyAddress::normalizeForStorage(['phase' => $homeowner->phase])['phase']])],
            'phase_suffix' => ['nullable', Rule::in([$homeowner->phase_suffix])],
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
        $normalized = $this->normalize(['phase_suffix' => $homeowner->phase_suffix, ...$input]);

        $validator = Validator::make(
            $normalized,
            $this->rules($user, $homeowner, $normalized),
            $this->messages(),
            $this->attributes(),
        );
        $validator->after(function ($validator) use ($normalized, $homeowner): void {
            if ($validator->errors()->hasAny(['house_number', 'street', 'block', 'lot', 'phase', 'ownership_type'])) {
                return;
            }

            $message = $this->addressConflictMessage($homeowner, $normalized);
            if ($message !== null) {
                $validator->errors()->add('house_number', __($message));
            }
        });

        return $validator->validate();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return array_map(fn (string $message): string => __($message), [
            'first_name.regex' => 'The first name may contain letters, spaces, apostrophes, periods, and hyphens only.',
            'middle_name.regex' => 'The middle name may contain letters, spaces, apostrophes, periods, and hyphens only.',
            'last_name.regex' => 'The last name may contain letters, spaces, apostrophes, periods, and hyphens only.',
            'emergency_contact_name.regex' => 'The emergency contact name may contain letters, spaces, apostrophes, periods, and hyphens only.',
            'contact_number.regex' => 'Enter an 11-digit Philippine mobile number beginning with 09.',
            'emergency_contact_number.regex' => 'Enter an 11-digit Philippine mobile number beginning with 09.',
            'email.unique' => self::EMAIL_UNAVAILABLE_MESSAGE,
            'date_of_birth.before' => 'The date of birth must be before today.',
            'residency_date.before_or_equal' => 'The move-in date cannot be in the future.',
            'residency_date.after_or_equal' => 'The move-in date cannot be earlier than the date of birth.',
            'phase.in' => 'Only HOA staff can change the registered phase.',
            'ownership_type.in' => 'Only HOA staff can change the occupancy type.',
            'profile_photo_upload.max' => 'The profile photo must not be larger than 2 MB.',
            'profile_photo_upload.mimetypes' => 'Upload a JPEG, PNG, or WebP image.',
            'profile_photo_upload.extensions' => 'Upload a file with a JPG, JPEG, PNG, or WebP extension.',
            'profile_photo_upload.dimensions' => 'The profile photo must be between 100 and 4000 pixels on each side.',
        ]);
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return array_map(fn (string $attribute): string => __($attribute), [
            'first_name' => 'first name',
            'middle_name' => 'middle name',
            'last_name' => 'last name',
            'date_of_birth' => 'date of birth',
            'contact_number' => 'contact number',
            'house_number' => 'property address',
            'residency_date' => 'move-in date',
            'ownership_type' => 'occupancy type',
            'emergency_contact_name' => 'emergency contact name',
            'emergency_contact_number' => 'emergency contact number',
            'profile_photo_upload' => 'profile photo',
        ]);
    }

    /** @return array{valid: bool, message: string} */
    public function emailAvailability(User $user, Homeowner $homeowner, mixed $email): array
    {
        $normalized = mb_strtolower(trim((string) $email), 'UTF-8');
        $validator = Validator::make(
            ['email' => $normalized],
            ['email' => $this->rules($user, $homeowner)['email']],
            $this->messages(),
            $this->attributes(),
        );

        if ($validator->fails()) {
            return ['valid' => false, 'message' => $validator->errors()->first('email')];
        }

        return ['valid' => true, 'message' => __(self::EMAIL_AVAILABLE_MESSAGE)];
    }

    /** @param array<string, mixed> $address @return array{valid: bool, message: string} */
    public function addressAvailability(Homeowner $homeowner, array $address): array
    {
        $normalized = PropertyAddress::normalizeForStorage([
            ...$address,
            'phase' => $homeowner->phase,
            'phase_suffix' => $homeowner->phase_suffix,
        ]);
        $validator = Validator::make($normalized, [
            'house_number' => ['nullable', 'string', 'max:50'],
            'street' => ['required', 'string', 'max:100'],
            'block' => ['required', 'string', 'max:20'],
            'lot' => ['required', 'string', 'max:20'],
        ], [], $this->attributes());

        if ($validator->fails()) {
            return ['valid' => false, 'message' => $validator->errors()->first()];
        }

        $normalized['ownership_type'] = $homeowner->ownership_type;
        $message = $this->addressConflictMessage($homeowner, $normalized);

        return $message === null
            ? ['valid' => true, 'message' => __(self::ADDRESS_AVAILABLE_MESSAGE)]
            : ['valid' => false, 'message' => __($message)];
    }

    /** @param array<string, mixed> $normalized */
    private function addressConflictMessage(Homeowner $homeowner, array $normalized): ?string
    {
        if (PropertyIdentity::same($normalized, $homeowner)) {
            return null;
        }

        if ($this->addressAvailability->financialMoveNeedsReview($homeowner, $normalized)) {
            return PropertyAddressAvailability::BILLED_MOVE_MESSAGE;
        }

        if ($homeowner->landlord_homeowner_id !== null || $homeowner->tenants()->exists()) {
            return self::LINKED_ADDRESS_MESSAGE;
        }

        if (! $this->addressAvailability->available([
            ...$normalized,
            'ownership_type' => $homeowner->ownership_type,
        ], $homeowner->id)) {
            return $this->addressAvailability->needsReview($normalized, $homeowner->id) ? PropertyAddressAvailability::REVIEW_MESSAGE : PropertyAddressAvailability::UNAVAILABLE_MESSAGE;
        }

        return null;
    }

    private function collapseWhitespace(mixed $value): ?string
    {
        $collapsed = preg_replace('/\s+/u', ' ', trim((string) $value));

        return filled($collapsed) ? $collapsed : null;
    }

    public static function normalizePhone(mixed $value): string
    {
        return (string) preg_replace('/[\s().-]+/u', '', trim((string) $value));
    }
}
