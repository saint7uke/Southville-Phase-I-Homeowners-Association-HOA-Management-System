<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\AuthenticatedActor;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class Payment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['homeowner_id', 'dues_setting_id', 'dues_obligation_id', 'or_number', 'amount_paid', 'balance', 'penalty', 'payment_date', 'covered_period', 'payment_method', 'status', 'notes', 'proof_path', 'proof_original_name', 'proof_mime_type', 'proof_size', 'reviewed_at', 'reviewed_by', 'review_status', 'recorded_by'];

    protected function casts(): array
    {
        return ['payment_date' => 'date', 'amount_paid' => 'decimal:2', 'balance' => 'decimal:2', 'penalty' => 'decimal:2', 'proof_size' => 'integer', 'reviewed_at' => 'datetime'];
    }

    public function homeowner(): BelongsTo
    {
        return $this->belongsTo(Homeowner::class)->withTrashed();
    }

    public function duesSetting(): BelongsTo
    {
        return $this->belongsTo(DuesSetting::class);
    }

    public function duesObligation(): BelongsTo
    {
        return $this->belongsTo(DuesObligation::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by')->withTrashed();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }

    protected static function booted(): void
    {
        self::creating(function (Payment $payment): void {
            $payment->or_number ??= 'OR-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
            $payment->recorded_by ??= app(AuthenticatedActor::class)->id();

            if ($payment->balance !== null && $payment->status !== null) {
                return;
            }

            $due = $payment->duesObligation ?? DuesObligation::query()->find($payment->dues_obligation_id);
            $amountDue = $due?->amount_due ?? DuesSetting::query()->find($payment->dues_setting_id)?->amount ?? '0.00';
            $totalDue = Money::toMinor((string) $amountDue) + Money::toMinor((string) $payment->penalty);
            $balance = max(0, $totalDue - Money::toMinor((string) $payment->amount_paid));
            $payment->balance = Money::fromMinor($balance);
            $payment->status = $balance > 0 ? 'Partial' : 'Paid';
        });

        self::deleting(function (Payment $payment): void {
            if (! $payment->isForceDeleting() && in_array($payment->review_status, ['Approved', 'Recorded'], true)) {
                self::reconcileObligation($payment, false);
            }
        });

        self::restoring(function (Payment $payment): void {
            if (in_array($payment->review_status, ['Approved', 'Recorded'], true)) {
                self::reconcileObligation($payment, true);
            }
        });
    }

    private static function reconcileObligation(Payment $payment, bool $restore): void
    {
        if ($payment->dues_obligation_id === null) {
            return;
        }

        $obligation = DuesObligation::query()->lockForUpdate()->findOrFail($payment->dues_obligation_id);
        $direction = $restore ? 1 : -1;
        $paid = Money::toMinor((string) $obligation->amount_paid) + ($direction * Money::toMinor((string) $payment->amount_paid));
        $penalty = Money::toMinor((string) $obligation->penalty_amount) + ($direction * Money::toMinor((string) $payment->penalty));
        $totalDue = Money::toMinor((string) $obligation->amount_due) + $penalty;

        if ($paid < 0 || $penalty < 0 || $paid > $totalDue) {
            throw ValidationException::withMessages(['payment' => 'This payment cannot be changed because it would make the obligation ledger inconsistent.']);
        }

        $obligation->update([
            'amount_paid' => Money::fromMinor($paid),
            'penalty_amount' => Money::fromMinor($penalty),
            'status' => $paid >= $totalDue
                ? 'Paid'
                : ($obligation->due_date->isPast() ? 'Overdue' : ($paid > 0 ? 'Partial' : 'Pending')),
        ]);
    }
}
