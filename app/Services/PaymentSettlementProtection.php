<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentDocument;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class PaymentSettlementProtection
{
    public function assertMutable(Payment $payment): void
    {
        $obligations = array_filter([$payment->dues_obligation_id, $payment->getOriginal('dues_obligation_id')]);
        if ($obligations && Schema::hasColumn('payment_documents', 'settlement_obligation_id')
            && PaymentDocument::whereIn('settlement_obligation_id', $obligations)->orderBy('id')->lockForUpdate()->first()) {
            throw ValidationException::withMessages(['payment' => __('This obligation has an issued settlement receipt. Its contributing payments cannot be changed or archived.')]);
        }
    }
}
