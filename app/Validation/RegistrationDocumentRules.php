<?php

declare(strict_types=1);

namespace App\Validation;

final class RegistrationDocumentRules
{
    public static function rules(): array
    {
        return ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'extensions:pdf,jpg,jpeg,png', 'max:10240'];
    }

    public static function messages(): array
    {
        return [
            'verification_document.required' => __('Upload a verification document to submit your application.'),
            'verification_document.file' => __('Upload a PDF, JPG, JPEG, or PNG file up to 10 MB.'),
            'verification_document.mimes' => __('Upload a PDF, JPG, JPEG, or PNG file up to 10 MB.'),
            'verification_document.extensions' => __('Upload a PDF, JPG, JPEG, or PNG file up to 10 MB.'),
            'verification_document.max' => __('Upload a PDF, JPG, JPEG, or PNG file up to 10 MB.'),
        ];
    }
}
