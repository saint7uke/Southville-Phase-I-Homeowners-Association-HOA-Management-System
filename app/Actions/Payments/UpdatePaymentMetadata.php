<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class UpdatePaymentMetadata
{
    /** @param array<string, mixed> $data */
    public function handle(Payment $payment, array $data, User $actor): Payment
    {
        Gate::forUser($actor)->authorize('update', $payment);
        $validated = Validator::make($data, [
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'payment_method' => ['sometimes', 'string'],
            'transaction_reference' => ['sometimes', 'nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
            'property_snapshot' => ['prohibited'],
        ])->validate();

        return DB::transaction(function () use ($payment, $validated, $actor): Payment {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            Gate::forUser($actor)->authorize('update', $locked);
            foreach (['payment_method', 'transaction_reference'] as $field) {
                if (array_key_exists($field, $validated) && $validated[$field] !== $locked->{$field}) {
                    throw ValidationException::withMessages([$field => __('The original payment method and reference cannot be changed.')]);
                }
            }
            $locked->update([
                'payment_date' => $validated['payment_date'],
                'notes' => filled($validated['notes'] ?? null) ? trim($validated['notes']) : null,
            ]);

            return $locked->refresh();
        });
    }
}
