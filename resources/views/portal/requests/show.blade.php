@extends('layouts.portal')
@section('title', $serviceRequest->ticket_number)
@section('content')
<header class="page-header"><p><a href="{{ route('portal.requests.index') }}">Back to requests</a></p><h1>{{ $serviceRequest->request_type }}</h1><p>{{ $serviceRequest->ticket_number }}</p></header><article class="panel"><dl class="dashboard-grid"><div><dt>Status</dt><dd><span class="status">{{ $serviceRequest->status }}</span></dd></div><div><dt>Submitted</dt><dd>{{ $serviceRequest->created_at->format('M d, Y g:i A') }}</dd></div></dl>@if($serviceRequest->details)<h2>Details</h2><p>{{ $serviceRequest->details }}</p>@endif @foreach($serviceRequest->caseAttachments as $attachment)<p><a class="button button-secondary" href="{{ route('case-attachments.download', $attachment) }}">Download {{ $attachment->original_name }}</a></p>@endforeach @if($serviceRequest->admin_remarks)<h2>HOA remarks</h2><p>{{ $serviceRequest->admin_remarks }}</p>@endif</article>
@endsection
