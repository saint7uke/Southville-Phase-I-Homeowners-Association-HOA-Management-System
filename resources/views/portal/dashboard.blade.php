@extends('layouts.portal')
@section('title', 'Dashboard')
@section('content')
<header class="page-header"><h1>Welcome, {{ auth()->user()->first_name }}</h1><p>Here is the latest activity for your Southville Phase I household.</p></header>
<section class="dashboard-grid" aria-label="Account overview">
    <article class="metric"><strong>{{ $homeowner->complaints_count }}</strong><span>Total complaints</span></article>
    <article class="metric"><strong>{{ $homeowner->service_requests_count }}</strong><span>Service requests</span></article>
    <article class="metric"><strong>{{ $payments->count() }}</strong><span>Recent payments</span></article>
    <article class="metric"><strong>{{ $homeowner->status }}</strong><span>Resident status</span></article>
</section>
<section class="panel" aria-labelledby="open-dues-heading">
    <h2 id="open-dues-heading">Open dues</h2>
    @forelse($duesObligations as $obligation)
        <article class="dues-obligation">
            <div>
                <h3>{{ $obligation->duesSetting->name }} · {{ $obligation->due_date->format('F Y') }}</h3>
                <p>Due {{ $obligation->due_date->format('M d, Y') }} · PHP {{ number_format((float) $obligation->amount_due, 2) }} · <span class="status">{{ $obligation->status }}</span></p>
            </div>
            <details>
                <summary>Submit payment proof</summary>
                <form class="form-grid" method="POST" action="{{ route('portal.dues-obligations.proof.store', $obligation) }}" enctype="multipart/form-data">
                    @csrf
                    <div class="field"><label for="amount_paid_{{ $obligation->id }}">Amount paid</label><input id="amount_paid_{{ $obligation->id }}" name="amount_paid" inputmode="decimal" required></div>
                    <div class="field"><label for="payment_date_{{ $obligation->id }}">Payment date</label><input id="payment_date_{{ $obligation->id }}" name="payment_date" type="date" max="{{ today()->toDateString() }}" required></div>
                    <div class="field"><label for="payment_method_{{ $obligation->id }}">Payment method</label><select id="payment_method_{{ $obligation->id }}" name="payment_method" required><option value="">Select an option</option><option>Bank Transfer</option><option>GCash</option><option>Maya</option></select></div>
                    <div class="field"><label for="proof_{{ $obligation->id }}">Proof (PDF, JPEG, or PNG; max 5 MB)</label><input id="proof_{{ $obligation->id }}" name="proof" type="file" accept="application/pdf,image/jpeg,image/png" required></div>
                    <input name="covered_period" type="hidden" value="{{ $obligation->due_date->format('F Y') }}">
                    <div class="field"><label for="notes_{{ $obligation->id }}">Note <span class="hint">(optional)</span></label><textarea id="notes_{{ $obligation->id }}" name="notes" maxlength="500"></textarea></div>
                    <button class="button button-primary" type="submit">Submit for review</button>
                </form>
            </details>
        </article>
    @empty
        <p>You have no open dues obligations.</p>
    @endforelse
</section>
<section class="panel"><h2>Recent payments</h2><div class="scroll-region" tabindex="0" role="region" aria-label="Recent payments table"><table class="data-table"><caption class="sr-only">Recent association dues payments</caption><thead><tr><th scope="col">Receipt</th><th scope="col">Period</th><th scope="col">Amount</th><th scope="col">Status</th><th scope="col">Date</th></tr></thead><tbody>@forelse($payments as $payment)<tr><td>@if($payment->review_status === 'Approved')<a href="{{ route('payments.receipt.download', $payment) }}">{{ $payment->or_number }}</a>@else{{ $payment->or_number }}@endif</td><td>{{ $payment->covered_period }}</td><td>PHP {{ number_format((float)$payment->amount_paid, 2) }}</td><td><span class="status">{{ $payment->status }}</span></td><td>{{ $payment->payment_date->format('M d, Y') }}</td></tr>@empty<tr><td colspan="5">No payments have been recorded yet.</td></tr>@endforelse</tbody></table></div></section>
<section class="panel"><h2>Latest announcements</h2>@forelse($announcements as $item)<article style="padding-block:1rem;border-bottom:1px solid var(--border)"><span class="category">{{ $item->category }}</span><h3><a href="{{ route('portal.announcements.show', $item) }}">{{ $item->title }}</a></h3><p>{{ Str::limit($item->content, 140) }}</p></article>@empty<p>No published announcements yet.</p>@endforelse</section>
@endsection
