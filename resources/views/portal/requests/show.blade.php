@extends('layouts.portal')
@section('title', $serviceRequest->ticket_number)
@section('content')
<header class="page-header"><p><a href="{{ route('portal.requests.index') }}" data-i18n="Back to requests">{{ __('Back to requests') }}</a></p><h1>{{ $serviceRequest->request_type }}</h1><p>{{ $serviceRequest->ticket_number }}</p></header><article class="panel"><dl class="dashboard-grid"><div><dt data-i18n="Status">{{ __('Status') }}</dt><dd><span class="status">{{ $serviceRequest->status }}</span></dd></div><div><dt data-i18n="Submitted">{{ __('Submitted') }}</dt><dd>{{ $serviceRequest->created_at->format('M d, Y g:i A') }}</dd></div></dl>@if($serviceRequest->details)<h2 data-i18n="Details">{{ __('Details') }}</h2><p>{{ $serviceRequest->details }}</p>@endif @foreach($serviceRequest->caseAttachments as $attachment)<p><a class="button button-secondary" href="{{ route('case-attachments.download', $attachment) }}">Download {{ $attachment->original_name }}</a></p>@endforeach @if($serviceRequest->admin_remarks)<h2 data-i18n="HOA remarks">{{ __('HOA remarks') }}</h2><p>{{ $serviceRequest->admin_remarks }}</p>@endif</article>
@if ($serviceRequest->request_type === \App\Services\CohabitationCertificateData::TYPE)
    <section class="panel" aria-labelledby="certificate-guidance-heading">
        <h2 id="certificate-guidance-heading" data-i18n="Cohabitation certificate preparation">{{ __('Cohabitation certificate preparation') }}</h2>
        @include('filament.resources.service-requests.cohabitation-guidance')
    </section>
@endif
@endsection
