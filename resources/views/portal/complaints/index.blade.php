@extends('layouts.portal')
@section('title', 'Complaints')
@section('content')
<header class="page-header"><h1>Complaints</h1><p>Track concerns you have submitted to the HOA office.</p><a class="button button-primary" href="{{ route('portal.complaints.create') }}">Submit complaint</a></header>
<div class="panel scroll-region" tabindex="0" role="region" aria-label="Complaints table"><table class="data-table"><caption>Your submitted complaints</caption><thead><tr><th scope="col">Ticket</th><th scope="col">Subject</th><th scope="col">Category</th><th scope="col">Priority</th><th scope="col">Status</th><th scope="col">Submitted</th></tr></thead><tbody>@forelse($complaints as $item)<tr><td><a href="{{ route('portal.complaints.show', $item) }}">{{ $item->ticket_number }}</a></td><td>{{ $item->subject }}</td><td>{{ $item->category }}</td><td>{{ $item->priority }}</td><td><span class="status">{{ $item->status }}</span></td><td>{{ $item->created_at->format('M d, Y') }}</td></tr>@empty<tr><td colspan="6">You have not submitted any complaints.</td></tr>@endforelse</tbody></table></div>{{ $complaints->links() }}
@endsection
