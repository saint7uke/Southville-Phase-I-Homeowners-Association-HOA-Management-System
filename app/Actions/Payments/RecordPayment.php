<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Actions\Dues\GenerateSettingObligations;
use App\Models\DuesObligation;
use App\Models\DuesSetting;
use App\Models\Homeowner;
use App\Models\Payment;
use App\Models\User;
use App\Services\PropertyAddressAvailability;
use App\Services\PropertyDuesAccounts;
use App\Support\Money;
use App\Support\PropertyIdentity;
use App\Support\PropertySnapshot;
use App\Validation\PaymentValidator;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class RecordPayment
{
    public function __construct(
        private readonly PaymentValidator $validator,
        private readonly PropertyDuesAccounts $accounts,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(array $data, User $recorder): Payment
    {
        Gate::forUser($recorder)->authorize('create', Payment::class);

        // Legacy obligation creation must roll back with validation or payment failures.
        return DB::transaction(fn (): Payment => $this->record($data, $recorder), 3);
    }

    /** @param array<string, mixed> $data */
    private function record(array $data, User $recorder): Payment
    {
        Gate::forUser($recorder)->authorize('create', Payment::class);
        if (blank($data['dues_obligation_id'] ?? null) && filled($data['homeowner_id'] ?? null) && filled($data['dues_setting_id'] ?? null)) {
            Validator::make($data, [
                'homeowner_id' => ['required', 'integer', 'exists:homeowners,id'],
                'dues_setting_id' => ['required', 'integer', 'exists:dues_settings,id'],
                'payment_date' => ['required', 'date', 'before_or_equal:today'],
            ])->validate();
            $period = CarbonImmutable::parse((string) $data['payment_date'])->startOfMonth();
            $setting = DuesSetting::query()->findOrFail($data['dues_setting_id']);
            $resident = Homeowner::query()->with('user')->findOrFail($data['homeowner_id']);
            app(PropertyAddressAvailability::class)->lockProperty($resident->getAttributes());
            $liable = $this->accounts->primaryForAddress(PropertyIdentity::key($resident));
            if ($liable === null || ! $this->accounts->payerIsEligible($resident, $liable)) {
                throw ValidationException::withMessages(['homeowner_id' => __('Select an active resident associated with a liable property account.')]);
            }
            $data['payer_homeowner_id'] ??= $resident->id;
            $existingObligation = DuesObligation::query()
                ->where('dues_setting_id', $setting->id)->where('billing_year', $period->year)->where('billing_month', $period->month)
                ->whereHas('homeowner', fn ($query) => PropertyIdentity::scope($query, $resident))
                ->orderBy('id')->first();
            if ($existingObligation !== null) {
                $data['dues_obligation_id'] = $existingObligation->id;
            } elseif (! $setting->is_active || ! app(GenerateSettingObligations::class)->appliesToPeriod($setting, $period)) {
                throw ValidationException::withMessages(['dues_setting_id' => __('This schedule does not apply to the selected billing month.')]);
            }
            if ($existingObligation === null) {
                $this->accounts->assertAssessmentIdentity($liable->getAttributes(), $period, $setting->id);
                $obligation = DuesObligation::query()->firstOrCreate([
                    'homeowner_id' => $liable->id,
                    'dues_setting_id' => $setting->id,
                    'billing_year' => $period->year,
                    'billing_month' => $period->month,
                ], [
                    'due_date' => $period->day(min((int) ($setting->due_day ?: 10), $period->daysInMonth)),
                    'amount_due' => $setting->amount,
                    'penalty_amount' => '0.00',
                    'amount_paid' => '0.00',
                    'status' => 'Pending',
                    'property_snapshot' => PropertySnapshot::from($liable),
                    'property_snapshot_needs_review' => false,
                ]);
                $data['dues_obligation_id'] = $obligation->id;
            }
        }
        $data['submission_token'] ??= (string) Str::uuid();
        $validated = $this->validator->validate($data);

        if ($existing = Payment::withTrashed()->where('submission_token', $validated['submission_token'])->first()) {
            return $this->idempotentResult($existing, $validated);
        }

        try {
            return DB::transaction(function () use ($validated, $recorder): Payment {
                // Match profile/landlord editing: property mutex before financial rows.
                $snapshot = DuesObligation::query()->with('homeowner')->findOrFail($validated['dues_obligation_id']);
                app(PropertyAddressAvailability::class)->lockProperty($snapshot->homeowner->getAttributes());
                $liable = Homeowner::withTrashed()->lockForUpdate()->findOrFail($snapshot->homeowner_id);
                $obligation = DuesObligation::query()->lockForUpdate()->findOrFail($validated['dues_obligation_id']);
                if ($obligation->homeowner_id !== $liable->id || ! PropertyIdentity::same($snapshot->homeowner, $liable)) {
                    throw ValidationException::withMessages(['dues_obligation_id' => __('The property details changed. Reload the payment form before recording this payment.')]);
                }
                $obligation->setRelation('homeowner', $liable);
                if ($existing = Payment::withTrashed()->where('submission_token', $validated['submission_token'])->lockForUpdate()->first()) {
                    return $this->idempotentResult($existing, $validated);
                }
                if (! in_array($obligation->status, ['Pending', 'Partial', 'Overdue'], true)) {
                    throw ValidationException::withMessages(['dues_obligation_id' => __('Select an open dues obligation.')]);
                }

                // Locking reads include the linked landlord and account-status checks.
                // Under REPEATABLE READ they must see changes committed while waiting.
                $payer = $this->accounts->eligiblePayers($liable, lock: true)
                    ->whereKey($validated['payer_homeowner_id'] ?? $obligation->homeowner_id)->first();
                if ($payer === null) {
                    throw ValidationException::withMessages(['payer_homeowner_id' => __('Select an active household member associated with this property.')]);
                }

                $amount = Money::toMinor((string) $validated['amount_paid']);
                $penalty = Money::toMinor((string) ($validated['penalty'] ?? '0.00'));
                $existingPenalty = Money::toMinor((string) $obligation->penalty_amount);
                $alreadyPaid = Money::toMinor((string) $obligation->amount_paid);
                $reserved = $obligation->payments()->where('review_status', 'Pending')->lockForUpdate()->get()
                    ->sum(fn (Payment $payment): int => Money::toMinor((string) $payment->amount_paid));
                $available = Money::toMinor((string) $obligation->amount_due) + $existingPenalty + $penalty - $alreadyPaid - $reserved;
                if ($amount > $available) {
                    throw ValidationException::withMessages(['amount_paid' => __('Payment cannot exceed the unreserved obligation balance.')]);
                }

                $newPaid = $alreadyPaid + $amount;
                $total = Money::toMinor((string) $obligation->amount_due) + $existingPenalty + $penalty;
                $balance = $total - $newPaid;
                $obligation->update([
                    'penalty_amount' => Money::fromMinor($existingPenalty + $penalty),
                    'amount_paid' => Money::fromMinor($newPaid),
                    'status' => $balance === 0 ? 'Paid' : ($obligation->due_date->isBefore(today()) ? 'Overdue' : 'Partial'),
                ]);

                return Payment::query()->create([
                    'homeowner_id' => $obligation->homeowner_id,
                    'payer_homeowner_id' => $payer->id,
                    'dues_setting_id' => $obligation->dues_setting_id,
                    'dues_obligation_id' => $obligation->id,
                    'or_number' => 'OR-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                    'amount_paid' => Money::fromMinor($amount),
                    'penalty' => Money::fromMinor($penalty),
                    'balance' => Money::fromMinor($balance),
                    'payment_date' => $validated['payment_date'],
                    'covered_period' => CarbonImmutable::create($obligation->billing_year, $obligation->billing_month)->format('F Y'),
                    'payment_method' => $validated['payment_method'],
                    'transaction_reference' => $validated['transaction_reference'] ?? null,
                    'transaction_reference_key' => $validated['transaction_reference_key'],
                    'submission_token' => $validated['submission_token'],
                    'status' => $balance > 0 ? 'Partial' : 'Paid',
                    'notes' => $validated['notes'] ?? null,
                    'review_status' => 'Recorded',
                    'reviewed_at' => now(),
                    'reviewed_by' => $recorder->id,
                    'recorded_by' => $recorder->id,
                    'property_snapshot' => [
                        ...Arr::only($obligation->homeowner->getAttributes(), ['house_number', 'street', 'block', 'lot', 'phase', 'phase_suffix', 'property_key', 'address_key']),
                        'collector_id' => $recorder->id, 'collector_name' => $recorder->full_name,
                        'collector_authorized' => $recorder->hasAnyRole(['hoa_admin', 'hoa_staff']) && $recorder->can('manage_payments'),
                    ],
                ]);
            }, 3);
        } catch (QueryException $exception) {
            if ($existing = Payment::withTrashed()->where('submission_token', $validated['submission_token'])->first()) {
                return $this->idempotentResult($existing, $validated);
            }
            if ($validated['transaction_reference_key'] !== null
                && Payment::withTrashed()->where('transaction_reference_key', $validated['transaction_reference_key'])->exists()) {
                throw ValidationException::withMessages(['transaction_reference' => __('This transaction reference has already been used.')]);
            }
            throw $exception;
        }
    }

    /** @param array<string, mixed> $data */
    private function idempotentResult(Payment $payment, array $data): Payment
    {
        $matches = ! $payment->trashed()
            && $payment->review_status === 'Recorded'
            && $payment->proof_path === null
            && $payment->dues_obligation_id === (int) $data['dues_obligation_id']
            && Money::toMinor((string) $payment->amount_paid) === Money::toMinor((string) $data['amount_paid'])
            && Money::toMinor((string) $payment->penalty) === Money::toMinor((string) ($data['penalty'] ?? '0.00'))
            && $payment->payment_date?->toDateString() === CarbonImmutable::parse($data['payment_date'])->toDateString()
            && $payment->payment_method === $data['payment_method']
            && $payment->transaction_reference_key === $data['transaction_reference_key']
            && (($data['payer_homeowner_id'] ?? null) === null || $payment->payer_homeowner_id === (int) $data['payer_homeowner_id']);
        if (! $matches) {
            throw ValidationException::withMessages(['submission_token' => __('This submission token has already been used for a different payment.')]);
        }

        return $payment;
    }
}
