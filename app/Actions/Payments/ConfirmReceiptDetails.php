<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Models\DuesObligation;
use App\Models\Payment;
use App\Models\User;
use App\Services\ButawReceiptEligibility;
use App\Services\ButawReceiptSource;
use App\Support\OperationalAccess;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Explicit, audited attestation for legacy records; never a migration or automatic backfill. */
final class ConfirmReceiptDetails
{
    public function handle(Payment $payment, array $data, User $actor): Payment
    {
        OperationalAccess::authorize($actor, 'manage_payments', true);
        app(ButawReceiptSource::class)->assertSchema();
        Gate::forUser($actor)->authorize('update', $payment);
        Validator::make($data, ['confirmed' => ['accepted'], 'note' => ['required', 'string', 'min:20', 'max:2000']])->validate();

        return DB::transaction(function () use ($payment, $data, $actor): Payment {
            DuesObligation::whereKey($payment->dues_obligation_id)->lockForUpdate()->firstOrFail();
            $payment = Payment::with(['homeowner', 'recorder'])->lockForUpdate()->findOrFail($payment->id);
            Gate::forUser($actor)->authorize('update', $payment);
            if ($payment->property_snapshot !== null) {
                throw ValidationException::withMessages(['confirmed' => __('The verified payment snapshot is immutable.')]);
            }
            $collector = $payment->recorder;
            if (! $collector || ! $collector->hasAnyRole(['hoa_admin', 'hoa_staff']) || blank($collector->full_name)) {
                throw ValidationException::withMessages(['confirmed' => __('The recorded collector requires HOA review. An arbitrary typed collector cannot be used.')]);
            }
            $payment->property_snapshot = [
                ...Arr::only($payment->homeowner->getAttributes(), ['house_number', 'street', 'block', 'lot', 'phase', 'phase_suffix', 'property_key', 'address_key']),
                'collector_id' => $collector->id, 'collector_name' => $collector->full_name, 'collector_authorized' => true,
                'confirmed_by' => $actor->id, 'confirmed_at' => now()->toIso8601String(), 'confirmation_note' => $data['note'],
            ];
            // Checks payment/year/penalty and complete block/lot before any write.
            app(ButawReceiptEligibility::class)->snapshot($payment);
            $payment->save();

            return $payment->refresh();
        });
    }
}
