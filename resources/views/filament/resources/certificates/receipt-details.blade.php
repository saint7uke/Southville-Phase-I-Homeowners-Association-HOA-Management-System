<div class="space-y-4" aria-live="polite" aria-atomic="true">
    <p class="text-sm text-gray-600 dark:text-gray-300" data-i18n="Select a homeowner and a recorded payment. Receipt details are filled automatically and cannot be edited here.">{{ __('Select a homeowner and a recorded payment. Receipt details are filled automatically and cannot be edited here.') }}</p>
    <p wire:loading wire:target="data.receipt_homeowner_id,data.payment_id" role="status" class="text-sm text-gray-600 dark:text-gray-300" data-i18n="Loading receipt details…">{{ __('Loading receipt details…') }}</p>

    @if (! $schemaReady)
        <p role="status" class="text-sm text-gray-700 dark:text-gray-200" data-i18n="{{ \App\Services\ButawReceiptSource::SETUP_MESSAGE }}">{{ __(\App\Services\ButawReceiptSource::SETUP_MESSAGE) }}</p>
    @elseif ($details)
        <section aria-labelledby="receipt-details-heading" class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
            <h3 id="receipt-details-heading" class="font-semibold text-gray-950 dark:text-white" data-i18n="Automatically filled receipt details">{{ __('Automatically filled receipt details') }}</h3>
            <dl class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                @foreach ([
                    'Liable homeowner' => $details['liable_name'],
                    'Actual payer' => $details['payer_name'],
                    'Receipt number' => $details['or_number'],
                    'Payment date' => $details['payment_date'],
                    'Payment method' => $details['payment_method'],
                    'Monthly dues amount' => 'PHP '.$details['values']['amount'],
                    'Billing period' => $details['values']['month'].' '.$details['billing_year'],
                    'Block' => $details['values']['block'],
                    'Lot' => $details['values']['lot'],
                    'Paid to (recorded collector)' => $details['values']['collector'],
                ] as $label => $value)
                    <div class="min-w-0">
                        <dt class="text-sm text-gray-600 dark:text-gray-300" data-i18n="{{ $label }}">{{ __($label) }}</dt>
                        <dd class="mt-1 break-words text-sm font-medium text-gray-950 dark:text-white">{{ $value ?: __('Not available') }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
        <p class="text-sm text-gray-600 dark:text-gray-300" data-i18n="Only the amount, month, block, lot, and recorded collector are printed in the original blanks. Homeowner and payer names are shown here for verification only.">{{ __('Only the amount, month, block, lot, and recorded collector are printed in the original blanks. Homeowner and payer names are shown here for verification only.') }}</p>
    @elseif ($hasHomeowner && ! $hasPayments)
        <p role="status" class="text-sm text-gray-700 dark:text-gray-200" data-i18n="This homeowner has no eligible recorded payment for the original Resibo Butaw. Record or verify a qualifying Cash payment first.">{{ __('This homeowner has no eligible recorded payment for the original Resibo Butaw. Record or verify a qualifying Cash payment first.') }}</p>
        @if ($recordPaymentUrl)
            <a class="text-sm text-primary-700 underline dark:text-primary-400" href="{{ $recordPaymentUrl }}" data-i18n="Record Cash payment">{{ __('Record Cash payment') }}</a>
        @endif
    @else
        <p class="text-sm text-gray-600 dark:text-gray-300" data-i18n="{{ $hasHomeowner ? 'Select a recorded payment to review its receipt details.' : 'Select the homeowner / liable property account first.' }}">{{ $hasHomeowner ? __('Select a recorded payment to review its receipt details.') : __('Select the homeowner / liable property account first.') }}</p>
    @endif

    @if ($schemaReady && ! $sourceConfirmed)
        <p role="status" class="text-sm text-gray-700 dark:text-gray-200" data-i18n="An administrator must review and confirm the original Resibo Butaw source under Certificates first.">{{ __('An administrator must review and confirm the original Resibo Butaw source under Certificates first.') }}</p>
    @endif
</div>
