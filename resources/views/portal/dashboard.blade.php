@extends('layouts.portal')
@section('title', 'Dashboard')
@section('content')
<header class="page-header"><h1>Welcome, {{ auth()->user()->first_name }}</h1><p><span data-i18n="Here is the latest activity for your Southville Phase I household.">{{ __('Here is the latest activity for your Southville Phase I household.') }}</span></p></header>
<section class="dashboard-grid" aria-label="Account overview">
    <article class="metric"><strong>{{ $homeowner->complaints_count }}</strong><span data-i18n="Total complaints">{{ __('Total complaints') }}</span></article>
    <article class="metric"><strong>{{ $homeowner->service_requests_count }}</strong><span data-i18n="Service requests">{{ __('Service requests') }}</span></article>
    <article class="metric"><strong>{{ $payments->count() }}</strong><span data-i18n="Recent payments">{{ __('Recent payments') }}</span></article>
    <article class="metric"><strong>{{ $homeowner->status }}</strong><span data-i18n="Resident status">{{ __('Resident status') }}</span></article>
</section>
<section class="panel" aria-labelledby="open-dues-heading">
    <h2 id="open-dues-heading" data-i18n="Open dues">{{ __('Open dues') }}</h2>
    @forelse($duesObligations as $obligation)
        <article class="dues-obligation">
            <div>
                <h3>{{ $obligation->duesSetting->name }} · {{ $obligation->due_date->format('F Y') }}</h3>
                <p>Due {{ $obligation->due_date->format('M d, Y') }} · PHP {{ number_format((float) $obligation->amount_due, 2) }} · <span class="status">{{ $obligation->status }}</span></p>
            </div>
            @if (\App\Support\PaymentMethods::acceptsNewProofs())
            <details>
                <summary><span data-i18n="Submit payment proof">{{ __('Submit payment proof') }}</span></summary>
                <form class="form-grid" method="POST" action="{{ route('portal.dues-obligations.proof.store', $obligation) }}" enctype="multipart/form-data">
                    @csrf
                    <div class="field"><label for="amount_paid_{{ $obligation->id }}"><span data-i18n="Amount paid">{{ __('Amount paid') }}</span></label><input id="amount_paid_{{ $obligation->id }}" name="amount_paid" inputmode="decimal" required></div>
                    <div class="field"><label for="payment_date_{{ $obligation->id }}"><span data-i18n="Payment date">{{ __('Payment date') }}</span></label><input id="payment_date_{{ $obligation->id }}" name="payment_date" type="date" max="{{ today()->toDateString() }}" required></div>
                    <div class="field"><label for="payment_method_{{ $obligation->id }}"><span data-i18n="Payment method">{{ __('Payment method') }}</span></label><select id="payment_method_{{ $obligation->id }}" name="payment_method" required><option value="" data-i18n="Select an option">{{ __('Select an option') }}</option><option>{{ __('Bank Transfer') }}</option><option>{{ __('GCash') }}</option><option>{{ __('Maya') }}</option></select></div>
                    <div class="field"><label for="transaction_reference_{{ $obligation->id }}"><span data-i18n="Transaction reference">{{ __('Transaction reference') }}</span></label><input id="transaction_reference_{{ $obligation->id }}" name="transaction_reference" maxlength="120" required></div>
                    <div class="field"><label for="proof_{{ $obligation->id }}"><span data-i18n="Proof (PDF, JPEG, or PNG; max 5 MB)">{{ __('Proof (PDF, JPEG, or PNG; max 5 MB)') }}</span></label><input id="proof_{{ $obligation->id }}" name="proof" type="file" accept="application/pdf,image/jpeg,image/png" required></div>
                    <input name="submission_token" type="hidden" value="{{ (string) Illuminate\Support\Str::uuid() }}">
                    <div class="field"><label for="notes_{{ $obligation->id }}"><span data-i18n="Note">{{ __('Note') }}</span> <span class="hint" data-i18n="(optional)">{{ __('(optional)') }}</span></label><textarea id="notes_{{ $obligation->id }}" name="notes" maxlength="500"></textarea></div>
                    <button class="button button-primary" type="submit" data-i18n="Submit for review">{{ __('Submit for review') }}</button>
                </form>
            </details>
            @else
                <p>{{ \App\Support\PaymentMethods::CASH_ONLY_MESSAGE }}</p>
            @endif
        </article>
    @empty
        <p><span data-i18n="You have no open dues obligations.">{{ __('You have no open dues obligations.') }}</span></p>
    @endforelse
</section>
<section class="panel"><h2 data-i18n="Recent payments">{{ __('Recent payments') }}</h2><div class="scroll-region" tabindex="0" role="region" aria-label="Recent payments table"><table class="data-table"><caption class="sr-only" data-i18n="Recent association dues payments">{{ __('Recent association dues payments') }}</caption><thead><tr><th scope="col" data-i18n="Receipt">{{ __('Receipt') }}</th><th scope="col" data-i18n="Period">{{ __('Period') }}</th><th scope="col" data-i18n="Amount">{{ __('Amount') }}</th><th scope="col" data-i18n="Status">{{ __('Status') }}</th><th scope="col" data-i18n="Date">{{ __('Date') }}</th></tr></thead><tbody>@forelse($payments as $payment)<tr><td>@if(in_array($payment->review_status, ['Approved', 'Recorded'], true) && in_array($payment->status, ['Paid', 'Partial'], true))<a href="{{ route('payments.receipt.download', $payment) }}">{{ $payment->or_number }}</a>@else{{ $payment->or_number }}@endif</td><td>{{ $payment->covered_period }}</td><td>PHP {{ number_format((float)$payment->amount_paid, 2) }}</td><td><span class="status">{{ $payment->status }}</span></td><td>{{ $payment->payment_date->format('M d, Y') }}</td></tr>@empty<tr><td colspan="5"><span data-i18n="No payments have been recorded yet.">{{ __('No payments have been recorded yet.') }}</span></td></tr>@endforelse</tbody></table></div></section>
<section class="panel"><h2 data-i18n="Latest announcements">{{ __('Latest announcements') }}</h2>@forelse($announcements as $item)<article style="padding-block:1rem;border-bottom:1px solid var(--border)"><span class="category">{{ $item->category }}</span><h3><a href="{{ route('portal.announcements.show', $item) }}">{{ $item->title }}</a></h3><p>{{ Str::limit($item->content, 140) }}</p></article>@empty<p data-i18n="No published announcements yet.">{{ __('No published announcements yet.') }}</p>@endforelse</section>
@endsection
