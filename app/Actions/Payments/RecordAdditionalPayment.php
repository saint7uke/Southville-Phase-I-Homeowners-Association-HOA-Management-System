<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class RecordAdditionalPayment
{
    public function handle(Payment $payment, array $data, User $actor): Payment
    {
        Gate::forUser($actor)->authorize('create', Payment::class);
        Gate::forUser($actor)->authorize('view', $payment);
        Validator::make($data, [
            'dues_obligation_id' => ['prohibited'], 'homeowner_id' => ['prohibited'],
            'dues_setting_id' => ['prohibited'], 'billing_month' => ['prohibited'], 'billing_year' => ['prohibited'],
            'penalty' => ['prohibited'], 'status' => ['prohibited'], 'review_status' => ['prohibited'],
        ])->validate();

        // RecordPayment owns the property -> liable account -> obligation lock order.
        // It checks available funds under lock and preserves the submission identity.
        $current = Payment::query()->findOrFail($payment->id);
        if (! $current->dues_obligation_id || $current->dues_obligation_id !== $payment->dues_obligation_id
            || ! in_array($current->review_status, ['Recorded', 'Approved'], true)) {
            throw ValidationException::withMessages(['payment' => __('Reload the payment history before recording another installment.')]);
        }

        return app(RecordPayment::class)->handle([
            ...$data, 'dues_obligation_id' => $current->dues_obligation_id, 'penalty' => '0.00',
        ], $actor);
    }
}
