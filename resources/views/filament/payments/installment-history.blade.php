<div class="space-y-4">
    @include('filament.payments.installment-summary', ['obligation' => $obligation])
    <p>{{ __('Each installment keeps its original status and balance. The current obligation summary shows whether the month is fully paid.') }}</p>
    @foreach ($obligation->payments as $payment)
        <section class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
            <h3 class="font-semibold">{{ $payment->or_number }}</h3>
            <dl class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                @foreach ([__('Actual payer') => $payment->payer?->user?->full_name, __('Amount paid') => 'PHP '.$payment->amount_paid,
                    __('Payment date') => $payment->payment_date?->format('M d, Y'), __('Review status') => __($payment->review_status),
                    __('Reviewed at') => $payment->reviewed_at?->format('M d, Y h:i A'),
                    __('Installment status') => __($payment->status), __('Balance after installment') => 'PHP '.$payment->balance,
                    __('Payment method') => $payment->payment_method, __('Transaction reference') => $payment->transaction_reference,
                    __('Recorded by') => $payment->recorder?->full_name] as $label => $value)
                    <div><dt class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</dt><dd class="break-words">{{ $value ?? '—' }}</dd></div>
                @endforeach
            </dl>
        </section>
    @endforeach
</div>
