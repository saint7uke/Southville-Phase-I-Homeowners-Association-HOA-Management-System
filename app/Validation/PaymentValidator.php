<?php

declare(strict_types=1);

namespace App\Validation;

use App\Support\PaymentMethods;
use App\Support\PaymentReference;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class PaymentValidator
{
    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function validate(array $data, bool $proofSubmission = false): array
    {
        $data['transaction_reference'] = PaymentReference::display($data['transaction_reference'] ?? null);
        $data['notes'] = filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null;
        $electronic = in_array($data['payment_method'] ?? null, ['Bank Transfer', 'GCash', 'Maya'], true);

        $validated = Validator::make($data, [
            'dues_obligation_id' => ['required', 'integer', 'exists:dues_obligations,id'],
            'payer_homeowner_id' => ['nullable', 'integer', 'exists:homeowners,id'],
            'amount_paid' => ['required', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/', 'decimal:0,2', 'gt:0'],
            'penalty' => [$proofSubmission ? 'prohibited' : 'nullable', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/', 'decimal:0,2', 'gte:0'],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'payment_method' => ['required', Rule::in(PaymentMethods::accepted())],
            'transaction_reference' => [$electronic ? 'required' : 'nullable', 'string', 'max:120', 'regex:/^[^\p{C}]+$/u'],
            'submission_token' => ['nullable', 'uuid'],
            'notes' => ['nullable', 'string', 'max:500'],
            'property_snapshot' => ['prohibited'],
        ], [
            'amount_paid.regex' => __('Enter a valid amount with no more than two decimal places.'),
            'payment_method.in' => __(PaymentMethods::CASH_ONLY_MESSAGE),
            'transaction_reference.required' => __('A transaction reference is required for electronic payments.'),
        ])->validate();

        $validated['transaction_reference_key'] = $electronic ? PaymentReference::key($validated['transaction_reference'] ?? null) : null;

        return $validated;
    }
}
