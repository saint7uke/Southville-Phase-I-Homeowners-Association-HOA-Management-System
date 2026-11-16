@extends('layouts.portal')
@section('title', __('My Concerns'))
@section('content')
<h1>{{ __('My Concerns') }}</h1>
<form class="panel hoa-concern-filters" method="GET">
    <label>{{ __('Type') }}<select name="type"><option value="">{{ __('All types') }}</option>@foreach(['Complaint','Inquiry'] as $type)<option value="{{ $type }}" @selected(request('type') === $type)>{{ __($type) }}</option>@endforeach</select></label>
    <label>{{ __('Status') }}<select name="status"><option value="">{{ __('All statuses') }}</option>@foreach(['Pending','Under Review','Resolved','Rejected','New','In Progress','Awaiting Resident','Closed'] as $status)<option @selected(request('status') === $status)>{{ $status }}</option>@endforeach</select></label>
    <button class="button" type="submit">{{ __('Apply filters') }}</button>
</form>
<div class="hoa-concern-list">@foreach($concerns as $concern)<article><div><strong>{{ $concern->subject }}</strong><p>{{ __($concern->kind) }} · {{ $concern->reference }} · {{ __($concern->status) }}</p></div><x-record-icon :label="__('View details')" label-key="View details" :href="route('portal.concerns.index', ['concern' => $concern->kind.':'.$concern->id])" /></article>@endforeach</div>
{{ $concerns->withQueryString()->links() }}
@if($detail)<article class="panel"><h2>{{ $detail->subject }}</h2><p class="hoa-concern-body">{{ $detail instanceof \App\Models\Complaint ? $detail->description : $detail->message }}</p>
    @if($detail instanceof \App\Models\Complaint)
        @if($detail->admin_remarks)<h3>{{ __('Resolution notes') }}</h3><p class="hoa-concern-body">{{ $detail->admin_remarks }}</p>@endif
        @foreach($detail->caseAttachments as $attachment)<span class="hoa-record-file"><span>{{ $attachment->original_name }}</span><x-record-icon :label="__('Download')" label-key="Download" icon="heroicon-o-document-arrow-down" :href="route('case-attachments.download', $attachment)" /></span>@endforeach
    @else @foreach($detail->activities as $reply)<h3>{{ __('HOA reply') }}</h3><p class="hoa-concern-body">{{ $reply->body }}</p>@endforeach @endif
</article>@endif
<details class="panel" @if($errors->any()) open @endif><summary>{{ __('Submit Concern') }}</summary>
    <form method="POST" action="{{ route('portal.concerns.store') }}" enctype="multipart/form-data" data-resident-concern>@csrf
        @if($errors->any())<ul role="alert">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
        <label>{{ __('Type') }}<select name="kind" data-concern-kind><option value="Complaint">{{ __('Complaint') }}</option><option value="Inquiry" @selected(old('kind') === 'Inquiry')>{{ __('Inquiry') }}</option></select></label>
        <div data-concern-complaint><label>{{ __('Category') }}<select name="category" data-concern-category>@foreach(\App\Validation\ComplaintSubmissionValidator::CATEGORIES as $category)<option @selected(old('category') === $category)>{{ $category }}</option>@endforeach</select></label>
        <label data-concern-other>{{ __('Please Specify') }}<input name="category_other" value="{{ old('category_other') }}" maxlength="160"></label>
        <label>{{ __('Supporting attachments') }}<input name="attachments[]" type="file" multiple accept=".pdf,.jpg,.jpeg,.png"></label></div>
        <label data-concern-inquiry>{{ __('Subject') }}<input name="subject" maxlength="160" value="{{ old('subject') }}"></label>
        <label>{{ __('Description') }}<textarea name="description" data-concern-description required maxlength="5000" rows="5">{{ old('description', old('message')) }}</textarea></label>
        <button class="button button-primary" type="submit">@svg('heroicon-o-paper-airplane', 'hoa-action-icon', ['aria-hidden' => 'true'])<span>{{ __('Submit Concern') }}</span></button>
    </form>
</details>
@endsection
