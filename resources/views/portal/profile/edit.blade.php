@extends('layouts.portal')
@section('title', 'Profile')
@section('content')
<header class="page-header"><h1>Your profile</h1><p>Keep your contact and property information current.</p></header>
@if($errors->any())<div class="error-summary" role="alert" tabindex="-1"><strong>Please correct the form errors.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form class="form-card" method="POST" action="{{ route('portal.profile.update') }}">@csrf @method('PATCH')<div class="form-grid two">
    <div class="field"><label for="full_name">Full name</label><input id="full_name" value="{{ $user->full_name }}" disabled><p class="hint">Contact the HOA office to correct your legal name.</p></div>
    <div class="field"><label for="email">Email address</label><input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required autocomplete="email">@error('email')<p class="error">{{ $message }}</p>@enderror</div>
    <div class="field"><label for="contact_number">Contact number</label><input id="contact_number" name="contact_number" value="{{ old('contact_number', $user->contact_number) }}" inputmode="numeric" pattern="09[0-9]{9}" maxlength="11" required autocomplete="tel">@error('contact_number')<p class="error">{{ $message }}</p>@enderror</div>
    <div class="field"><label for="house_number">House number</label><input id="house_number" name="house_number" value="{{ old('house_number', $user->homeowner->house_number) }}" required></div>
    <div class="field"><label for="street">Street</label><input id="street" name="street" value="{{ old('street', $user->homeowner->street) }}" required></div>
    <div class="field"><label for="block">Block</label><input id="block" name="block" value="{{ old('block', $user->homeowner->block) }}"></div>
    <div class="field"><label for="lot">Lot</label><input id="lot" name="lot" value="{{ old('lot', $user->homeowner->lot) }}"></div>
</div><div class="form-actions"><button class="button button-primary" type="submit">Save profile</button></div></form>
@endsection
