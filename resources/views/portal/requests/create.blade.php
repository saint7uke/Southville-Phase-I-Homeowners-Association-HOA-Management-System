@extends('layouts.portal')
@section('title', 'New service request')
@section('content')
<header class="page-header"><h1>New service request</h1><p>Select the service you need and add any useful details.</p></header>
<form class="form-card" method="POST" action="{{ route('portal.requests.store') }}">@csrf<div class="form-grid"><div class="field"><label for="request_type">Request type</label><select id="request_type" name="request_type" required>@foreach(['Certificate of Residency','HOA Clearance','Gate Pass','Facility Reservation','Other'] as $value)<option @selected(old('request_type') === $value)>{{ $value }}</option>@endforeach</select>@error('request_type')<p class="error">{{ $message }}</p>@enderror</div><div class="field"><label for="details">Details</label><textarea id="details" name="details" maxlength="3000">{{ old('details') }}</textarea>@error('details')<p class="error">{{ $message }}</p>@enderror</div></div><div class="form-actions"><button class="button button-primary" type="submit">Submit request</button><a href="{{ route('portal.requests.index') }}">Cancel</a></div></form>
@endsection
