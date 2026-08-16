@extends('layouts.portal')
@section('title', 'Service requests')
@section('content')
<header class="page-header"><h1>Service requests</h1><p>Request HOA documents and community services.</p><a class="button button-primary" href="{{ route('portal.requests.create') }}">New request</a></header>
<div class="panel scroll-region" tabindex="0" role="region" aria-label="Service requests table"><table class="data-table"><caption>Your submitted requests</caption><thead><tr><th scope="col">Ticket</th><th scope="col">Type</th><th scope="col">Status</th><th scope="col">Submitted</th></tr></thead><tbody>@forelse($requests as $item)<tr><td><a href="{{ route('portal.requests.show', $item) }}">{{ $item->ticket_number }}</a></td><td>{{ $item->request_type }}</td><td><span class="status">{{ $item->status }}</span></td><td>{{ $item->created_at->format('M d, Y') }}</td></tr>@empty<tr><td colspan="4">You have not submitted any service requests.</td></tr>@endforelse</tbody></table></div>{{ $requests->links() }}
@endsection
