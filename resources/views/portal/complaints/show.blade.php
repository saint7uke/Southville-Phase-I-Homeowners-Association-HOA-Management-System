@extends('layouts.portal')
@section('title', $complaint->ticket_number)
@section('content')
<header class="page-header"><p><a href="{{ route('portal.complaints.index') }}">Back to complaints</a></p><h1>{{ $complaint->subject }}</h1><p>{{ $complaint->ticket_number }}</p></header>
<article class="panel"><dl class="dashboard-grid"><div><dt>Status</dt><dd><span class="status">{{ $complaint->status }}</span></dd></div><div><dt>Category</dt><dd>{{ $complaint->category }}</dd></div><div><dt>Priority</dt><dd>{{ $complaint->priority }}</dd></div><div><dt>Submitted</dt><dd>{{ $complaint->created_at->format('M d, Y g:i A') }}</dd></div></dl><h2>Description</h2><p>{{ $complaint->description }}</p>@if($complaint->attachment)<p><a class="button button-secondary" href="{{ route('portal.complaints.attachment', $complaint) }}">Download legacy attachment</a></p>@endif @foreach($complaint->caseAttachments as $attachment)<p><a class="button button-secondary" href="{{ route('case-attachments.download', $attachment) }}">Download {{ $attachment->original_name }}</a></p>@endforeach @if($complaint->admin_remarks)<h2>HOA remarks</h2><p>{{ $complaint->admin_remarks }}</p>@endif</article>
@endsection
