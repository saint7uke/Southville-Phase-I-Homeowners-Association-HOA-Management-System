<?php

declare(strict_types=1);

namespace App\Validation;

final class CaseAttachmentRules
{
    public const MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    public const EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];

    /** @return array<string, array<int, mixed>> */
    public static function rules(bool $includeLegacySingle = false): array
    {
        $fileRules = [
            'file',
            'max:5120',
            'mimetypes:'.implode(',', self::MIME_TYPES),
            'extensions:'.implode(',', self::EXTENSIONS),
        ];
        $rules = [
            'attachments' => ['nullable', 'array', 'max:3'],
            'attachments.*' => $fileRules,
        ];

        if ($includeLegacySingle) {
            $rules['attachment'] = ['nullable', ...$fileRules];
        }

        return $rules;
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return array_map(fn (string $message): string => __($message), [
            'attachments.max' => 'A maximum of three attachments is allowed.',
            'attachments.*.mimetypes' => 'Every attachment must be a PDF, JPEG, PNG, DOC, or DOCX file.',
            'attachments.*.extensions' => 'Every attachment must have a PDF, JPG, JPEG, PNG, DOC, or DOCX extension.',
            'attachments.*.max' => 'Every attachment must be no larger than 5 MB.',
        ]);
    }
}
