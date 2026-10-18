<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Models\Payment;
use App\Models\User;
use App\Support\PaymentReference;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class UpdatePaymentMetadata
{
    /** @param array<string, mixed> $data */
    public function handle(Payment $payment, array $data, User $actor): Payment
    {
        Gate::forUser($actor)->authorize('update', $payment);
        $reference = PaymentReference::display($data['transaction_reference'] ?? null);
        $electronic = in_array($data['payment_method'] ?? null, ['Bank Transfer', 'GCash', 'Maya'], true);
        $validated = Validator::make([...$data, 'transaction_reference' => $reference], [
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'payment_method' => ['required', Rule::in(['Cash', 'Bank Transfer', 'GCash', 'Maya'])],
            'transaction_reference' => [$electronic ? 'required' : 'nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
        ])->validate();
        $key = $electronic ? PaymentReference::key($reference) : null;
        if ($key !== null && Payment::withTrashed()->where('transaction_reference_key', $key)->whereKeyNot($payment->id)->exists()) {
            throw ValidationException::withMessages(['transaction_reference' => __('This transaction reference has already been used.')]);
        }

        try {
            return DB::transaction(function () use ($payment, $validated, $reference, $key): Payment {
                $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
                $locked->update([
                    'payment_date' => $validated['payment_date'],
                    'payment_method' => $validated['payment_method'],
                    'transaction_reference' => $reference,
                    'transaction_reference_key' => $key,
                    'notes' => filled($validated['notes'] ?? null) ? trim($validated['notes']) : null,
                ]);

                return $locked->refresh();
            });
        } catch (QueryException) {
            throw ValidationException::withMessages(['transaction_reference' => __('This transaction reference has already been used.')]);
        }
    }
}
