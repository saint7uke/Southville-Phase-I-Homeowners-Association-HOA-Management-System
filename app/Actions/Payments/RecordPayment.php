<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Models\DuesObligation;
use App\Models\DuesSetting;
use App\Models\Homeowner;
use App\Models\Payment;
use App\Models\User;
use App\Services\PropertyDuesAccounts;
use App\Support\Money;
use App\Validation\PaymentValidator;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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
        if (blank($data['dues_obligation_id'] ?? null) && filled($data['homeowner_id'] ?? null) && filled($data['dues_setting_id'] ?? null)) {
            $period = CarbonImmutable::parse((string) $data['payment_date'])->startOfMonth();
            $setting = DuesSetting::query()->findOrFail($data['dues_setting_id']);
            $obligation = DuesObligation::query()->firstOrCreate([
                'homeowner_id' => $data['homeowner_id'],
                'dues_setting_id' => $setting->id,
                'billing_year' => $period->year,
                'billing_month' => $period->month,
            ], [
                'due_date' => $period->day(min((int) ($setting->due_day ?: 10), $period->daysInMonth)),
                'amount_due' => $setting->amount,
                'penalty_amount' => '0.00',
                'amount_paid' => '0.00',
                'status' => 'Pending',
            ]);
            $data['dues_obligation_id'] = $obligation->id;
        }
        $data['submission_token'] ??= (string) Str::uuid();
        $validated = $this->validator->validate($data);

        if ($existing = Payment::withTrashed()->where('submission_token', $validated['submission_token'])->first()) {
            return $this->idempotentResult($existing, $validated);
        }

        try {
            return DB::transaction(function () use ($validated, $recorder): Payment {
                $obligation = DuesObligation::query()->lockForUpdate()->with(['homeowner.user'])->findOrFail($validated['dues_obligation_id']);
                if (! in_array($obligation->status, ['Pending', 'Partial', 'Overdue'], true)) {
                    throw ValidationException::withMessages(['dues_obligation_id' => __('Select an open dues obligation.')]);
                }

                $payer = Homeowner::query()->with('user')->findOrFail($validated['payer_homeowner_id'] ?? $obligation->homeowner_id);
                if (! $this->accounts->payerIsEligible($payer, $obligation->homeowner)) {
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
                    'status' => $balance === 0 ? 'Paid' : ($obligation->due_date->isPast() ? 'Overdue' : 'Partial'),
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
                ]);
            }, 3);
        } catch (QueryException $exception) {
            if ($validated['transaction_reference_key'] !== null
                && Payment::withTrashed()->where('transaction_reference_key', $validated['transaction_reference_key'])->exists()) {
                throw ValidationException::withMessages(['transaction_reference' => __('This transaction reference has already been used.')]);
            }
            if ($existing = Payment::withTrashed()->where('submission_token', $validated['submission_token'])->first()) {
                return $this->idempotentResult($existing, $validated);
            }
            throw $exception;
        }
    }

    /** @param array<string, mixed> $data */
    private function idempotentResult(Payment $payment, array $data): Payment
    {
        $matches = $payment->dues_obligation_id === (int) $data['dues_obligation_id']
            && Money::toMinor((string) $payment->amount_paid) === Money::toMinor((string) $data['amount_paid'])
            && Money::toMinor((string) $payment->penalty) === Money::toMinor((string) ($data['penalty'] ?? '0.00'))
            && $payment->payment_date?->toDateString() === CarbonImmutable::parse($data['payment_date'])->toDateString()
            && $payment->payment_method === $data['payment_method']
            && $payment->transaction_reference_key === $data['transaction_reference_key']
            && ($data['payer_homeowner_id'] === null || $payment->payer_homeowner_id === (int) $data['payer_homeowner_id']);
        if (! $matches) {
            throw ValidationException::withMessages(['submission_token' => __('This submission token has already been used for a different payment.')]);
        }

        return $payment;
    }
}
