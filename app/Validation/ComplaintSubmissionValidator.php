<?php

declare(strict_types=1);

namespace App\Validation;

use App\Models\Homeowner;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class ComplaintSubmissionValidator
{
    public const CATEGORIES = ['Noise', 'Property Damage', 'Neighbor Dispute', 'Common Area', 'Security', 'Others'];

    public const PRIORITIES = ['Low', 'Medium', 'High', 'Urgent'];

    /** @return array<string, array<int, mixed>> */
    public static function rules(bool $allowAssignment = false): array
    {
        return [
            'subject' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'min:20', 'max:5000'],
            'category' => ['required', Rule::in(self::CATEGORIES)],
            'priority' => ['required', Rule::in(self::PRIORITIES)],
            'handled_by' => $allowAssignment
                ? ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->whereNull('deleted_at')->where('account_status', 'Active'))]
                : ['prohibited'],
            ...CaseAttachmentRules::rules(includeLegacySingle: true),
            'homeowner_id' => ['prohibited'],
            'status' => ['prohibited'],
            'admin_remarks' => ['prohibited'],
            'resolved_at' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return array_map(fn (string $message): string => __($message), [
            'description.min' => 'Describe the complaint in at least 20 characters.',
            'category.in' => 'Select a valid complaint category.',
            'priority.in' => 'Select a valid priority.',
            'handled_by.exists' => 'Select an active HOA staff member.',
            ...CaseAttachmentRules::messages(),
        ]);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function normalize(array $data): array
    {
        foreach (['subject', 'description', 'category', 'priority'] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $files
     * @return array<string, mixed>
     */
    public function validate(Homeowner $homeowner, array $data, array $files, User $actor): array
    {
        $data = $this->normalize($data);
        $data['attachments'] = $files;

        $validator = Validator::make($data, self::rules(false), self::messages());
        $validator->after(function ($validator) use ($homeowner, $actor, $data): void {
            $resident = User::withTrashed()->find($homeowner->user_id);
            if (! $homeowner->exists || $homeowner->trashed() || $resident === null || $resident->trashed()) {
                $validator->errors()->add('homeowner_id', __('Select a current resident.'));
            }

            if ($homeowner->user_id !== $actor->id) {
                $validator->errors()->add('homeowner_id', __('You cannot submit a complaint for this resident.'));
            }

            if ($actor->hasRole('hoa_admin') && filled($data['handled_by'] ?? null)) {
                $handler = User::query()->find($data['handled_by']);
                if ($handler === null || $handler->account_status !== 'Active' || ! $handler->hasRole('hoa_staff')) {
                    $validator->errors()->add('handled_by', __('Select an active HOA staff member.'));
                }
            }
        });

        $validated = $validator->validate();
        unset($validated['attachments'], $validated['attachment']);

        return $validated;
    }
}
