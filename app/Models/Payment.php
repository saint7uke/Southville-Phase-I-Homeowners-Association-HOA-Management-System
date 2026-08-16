<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

final class Payment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['homeowner_id', 'dues_setting_id', 'or_number', 'amount_paid', 'balance', 'penalty', 'payment_date', 'covered_period', 'payment_method', 'status', 'notes', 'recorded_by'];

    protected function casts(): array
    {
        return ['payment_date' => 'date', 'amount_paid' => 'decimal:2', 'balance' => 'decimal:2', 'penalty' => 'decimal:2'];
    }

    public function homeowner(): BelongsTo
    {
        return $this->belongsTo(Homeowner::class);
    }

    public function duesSetting(): BelongsTo
    {
        return $this->belongsTo(DuesSetting::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    protected static function booted(): void
    {
        self::creating(function (Payment $payment): void {
            $payment->or_number ??= 'OR-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
            $payment->recorded_by ??= auth()->id();
            $dues = DuesSetting::query()->find($payment->dues_setting_id);
            $payment->balance = max(0, (float) ($dues?->amount ?? 0) + (float) $payment->penalty - (float) $payment->amount_paid);
            $payment->status = $payment->balance > 0 ? 'Partial' : 'Paid';
        });
    }
}
