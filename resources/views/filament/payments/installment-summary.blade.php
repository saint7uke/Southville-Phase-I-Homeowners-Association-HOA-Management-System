@php
    $due = \App\Support\Money::toMinor((string) $obligation->amount_due);
    $penalty = \App\Support\Money::toMinor((string) $obligation->penalty_amount);
    $paid = \App\Support\Money::toMinor((string) $obligation->amount_paid);
    $pending = $obligation->payments->where('review_status', 'Pending')->sum(fn ($payment) => \App\Support\Money::toMinor((string) $payment->amount_paid));
@endphp
<dl class="grid grid-cols-1 gap-3 sm:grid-cols-2">
    @foreach ([__('Property') => $obligation->homeowner->full_address, __('Dues type') => $obligation->duesSetting->name,
        __('Billing period') => sprintf('%02d/%d', $obligation->billing_month, $obligation->billing_year),
        __('Current obligation status') => __($obligation->status),
        __('Assessed amount') => 'PHP '.\App\Support\Money::fromMinor($due), __('Penalties') => 'PHP '.\App\Support\Money::fromMinor($penalty),
        __('Accepted total') => 'PHP '.\App\Support\Money::fromMinor($paid), __('Pending proofs') => 'PHP '.\App\Support\Money::fromMinor($pending),
        __('Current obligation balance') => 'PHP '.\App\Support\Money::fromMinor($due + $penalty - $paid),
        __('Available balance') => 'PHP '.\App\Support\Money::fromMinor(max(0, $due + $penalty - $paid - $pending))] as $label => $value)
        <div><dt class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</dt><dd class="font-medium break-words">{{ $value }}</dd></div>
    @endforeach
</dl>
