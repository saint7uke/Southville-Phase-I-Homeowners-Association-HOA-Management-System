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
<section class="panel"><h2>Recent payments</h2><div class="scroll-region" tabindex="0" role="region" aria-label="Recent payments table"><table class="data-table"><caption class="sr-only">Recent association dues payments</caption><thead><tr><th scope="col">Receipt</th><th scope="col">Period</th><th scope="col">Amount</th><th scope="col">Status</th><th scope="col">Date</th></tr></thead><tbody>@forelse($payments as $payment)<tr><td>{{ $payment->or_number }}</td><td>{{ $payment->covered_period }}</td><td>PHP {{ number_format((float)$payment->amount_paid, 2) }}</td><td><span class="status">{{ $payment->status }}</span></td><td>{{ $payment->payment_date->format('M d, Y') }}</td></tr>@empty<tr><td colspan="5">No payments have been recorded yet.</td></tr>@endforelse</tbody></table></div></section>
<section class="panel"><h2>Latest announcements</h2>@forelse($announcements as $item)<article style="padding-block:1rem;border-bottom:1px solid var(--border)"><span class="category">{{ $item->category }}</span><h3><a href="{{ route('portal.announcements.show', $item) }}">{{ $item->title }}</a></h3><p>{{ Str::limit($item->content, 140) }}</p></article>@empty<p>No published announcements yet.</p>@endforelse</section>
@endsection
