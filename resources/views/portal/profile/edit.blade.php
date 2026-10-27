@extends('layouts.portal')

@section('title', 'Profile')

@section('content')
    <header class="page-header">
        <h1 data-i18n="Your profile">{{ __('Your profile') }}</h1>
        <p><span data-i18n="Keep your identity, contact, property, and emergency information current.">{{ __('Keep your identity, contact, property, and emergency information current.') }}</span></p>
    </header>

    @if ($errors->any())
        <div id="form-errors" class="error-summary" role="alert" tabindex="-1">
            <strong data-i18n="Please correct the following errors.">{{ __('Please correct the following errors.') }}</strong>
            <ul>
                @foreach ($errors->messages() as $field => $messages)
                    @foreach ($messages as $message)
                        <li><a href="#{{ $field }}">{{ $message }}</a></li>
                    @endforeach
                @endforeach
            </ul>
        </div>
    @endif

    <form class="form-card profile-form" method="POST" action="{{ route('portal.profile.update') }}" enctype="multipart/form-data" aria-labelledby="profile-form-heading" data-confirm-profile>
        @csrf
        @method('PATCH')
        <h2 id="profile-form-heading" data-i18n="Profile details">{{ __('Profile details') }}</h2>

        @if ($user->homeowner->profile_photo)
            <figure class="profile-photo-preview">
                <img src="{{ route('homeowner.profile-photo', $user->homeowner) }}" alt="Profile photo of {{ $user->full_name }}" width="160" height="160">
                <figcaption><span data-i18n="Current private profile photo">{{ __('Current private profile photo') }}</span></figcaption>
            </figure>
        @endif

        <fieldset class="profile-fieldset">
            <legend><span data-i18n="Personal information">{{ __('Personal information') }}</span></legend>
            <div class="form-grid two">
                <div class="field">
                    <label for="first_name" data-i18n="First name">{{ __('First name') }}</label>
                    <input id="first_name" name="first_name" value="{{ old('first_name', $user->first_name) }}" required maxlength="100" autocomplete="given-name" @error('first_name') aria-invalid="true" aria-describedby="first_name-error" @enderror>
                    @error('first_name')<p id="first_name-error" class="error">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="middle_name"><span data-i18n="Middle name">{{ __('Middle name') }}</span> <span class="hint" data-i18n="(optional)">{{ __('(optional)') }}</span></label>
                    <input id="middle_name" name="middle_name" value="{{ old('middle_name', $user->middle_name) }}" maxlength="100" autocomplete="additional-name" @error('middle_name') aria-invalid="true" aria-describedby="middle_name-error" @enderror>
                    @error('middle_name')<p id="middle_name-error" class="error">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="last_name" data-i18n="Last name">{{ __('Last name') }}</label>
                    <input id="last_name" name="last_name" value="{{ old('last_name', $user->last_name) }}" required maxlength="100" autocomplete="family-name" @error('last_name') aria-invalid="true" aria-describedby="last_name-error" @enderror>
                    @error('last_name')<p id="last_name-error" class="error">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="suffix"><span data-i18n="Suffix">{{ __('Suffix') }}</span> <span class="hint" data-i18n="(optional)">{{ __('(optional)') }}</span></label>
                    <select id="suffix" name="suffix" @error('suffix') aria-invalid="true" aria-describedby="suffix-error" @enderror>
                        <option value="" data-i18n="Select an option">{{ __('Select an option') }}</option>
                        @foreach (['Jr.', 'Sr.', 'II', 'III', 'IV', 'N/A'] as $suffix)
                            <option value="{{ $suffix }}" @selected(old('suffix', $user->suffix) === $suffix)>{{ $suffix }}</option>
                        @endforeach
                    </select>
                    @error('suffix')<p id="suffix-error" class="error">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="sex" data-i18n="Sex">{{ __('Sex') }}</label>
                    <select id="sex" name="sex" required @error('sex') aria-invalid="true" aria-describedby="sex-error" @enderror>
                        <option value="" data-i18n="Select an option">{{ __('Select an option') }}</option>
                        @foreach (['Male', 'Female', 'Prefer not to say'] as $sex)
                            <option value="{{ $sex }}" @selected(old('sex', $user->sex) === $sex)>{{ $sex }}</option>
                        @endforeach
                    </select>
                    @error('sex')<p id="sex-error" class="error">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="date_of_birth" data-i18n="Date of birth">{{ __('Date of birth') }}</label>
                    <input id="date_of_birth" name="date_of_birth" type="date" value="{{ old('date_of_birth', $user->date_of_birth?->toDateString()) }}" max="{{ today()->subDay()->toDateString() }}" required autocomplete="bday" @error('date_of_birth') aria-invalid="true" aria-describedby="date_of_birth-error" @enderror>
                    <p class="hint">Current age: {{ $user->age }} years</p>
                    @error('date_of_birth')<p id="date_of_birth-error" class="error">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="email" data-i18n="Email address">{{ __('Email address') }}</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required maxlength="255" autocomplete="email" aria-describedby="email-hint @error('email') email-error @enderror" @error('email') aria-invalid="true" @enderror>
                    <p id="email-hint" class="hint" data-i18n="Changing this address requires email verification.">{{ __('Changing this address requires email verification.') }}</p>
                    @error('email')<p id="email-error" class="error">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="contact_number" data-i18n="Contact number">{{ __('Contact number') }}</label>
                    <input id="contact_number" name="contact_number" value="{{ old('contact_number', $user->contact_number) }}" inputmode="numeric" pattern="09[0-9]{9}" maxlength="11" required autocomplete="tel" aria-describedby="contact_number-hint @error('contact_number') contact_number-error @enderror" @error('contact_number') aria-invalid="true" @enderror>
                    <p id="contact_number-hint" class="hint">11 digits beginning with 09.</p>
                    @error('contact_number')<p id="contact_number-error" class="error">{{ $message }}</p>@enderror
                </div>
            </div>
        </fieldset>

        <fieldset class="profile-fieldset">
            <legend><span data-i18n="Property and emergency contact">{{ __('Property and emergency contact') }}</span></legend>
            <div class="form-grid two">
                @foreach ([
                    'street' => ['Street', $user->homeowner->street],
                    'block' => ['Block', $user->homeowner->block],
                    'lot' => ['Lot', $user->homeowner->lot],
                    'phase' => ['Registered phase', \App\Support\PropertyIdentity::phase($user->homeowner)],
                ] as $field => [$label, $value])
                    <div class="field">
                        <span data-i18n="{{ $label }}">{{ __($label) }}</span>
                        <p id="registered-{{ $field }}">{{ $value }}</p>
                    </div>
                @endforeach
                <p class="hint" data-i18n="Contact the HOA to change the registered property address.">{{ __('Contact the HOA to change the registered property address.') }}</p>
                <div class="field">
                    <label for="residency_date" data-i18n="Move-in date">{{ __('Move-in date') }}</label>
                    <input id="residency_date" name="residency_date" type="date" value="{{ old('residency_date', $user->homeowner->residency_date?->toDateString()) }}" max="{{ today()->toDateString() }}" required @error('residency_date') aria-invalid="true" aria-describedby="residency_date-error" @enderror>
                    @error('residency_date')<p id="residency_date-error" class="error">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="ownership_type" data-i18n="Occupancy type">{{ __('Occupancy type') }}</label>
                    <select id="ownership_type" disabled aria-disabled="true" @error('ownership_type') aria-invalid="true" aria-describedby="ownership_type-error" @enderror>
                        <option value="" data-i18n="Select an option">{{ __('Select an option') }}</option>
                        @foreach (['Owner', 'Tenant', 'Co-owner'] as $type)
                            <option value="{{ $type }}" @selected(old('ownership_type', $user->homeowner->ownership_type) === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                    <input type="hidden" name="ownership_type" value="{{ $user->homeowner->ownership_type }}">
                    <p class="hint" data-i18n="Contact the HOA to change the occupancy type.">{{ __('Contact the HOA to change the occupancy type.') }}</p>
                    @error('ownership_type')<p id="ownership_type-error" class="error">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="emergency_contact_name" data-i18n="Emergency contact name">{{ __('Emergency contact name') }}</label>
                    <input id="emergency_contact_name" name="emergency_contact_name" value="{{ old('emergency_contact_name', $user->homeowner->emergency_contact_name) }}" maxlength="100" required autocomplete="name" @error('emergency_contact_name') aria-invalid="true" aria-describedby="emergency_contact_name-error" @enderror>
                    @error('emergency_contact_name')<p id="emergency_contact_name-error" class="error">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="emergency_contact_number" data-i18n="Emergency contact number">{{ __('Emergency contact number') }}</label>
                    <input id="emergency_contact_number" name="emergency_contact_number" value="{{ old('emergency_contact_number', $user->homeowner->emergency_contact_number) }}" inputmode="numeric" pattern="09[0-9]{9}" maxlength="11" required autocomplete="tel" @error('emergency_contact_number') aria-invalid="true" aria-describedby="emergency_contact_number-error" @enderror>
                    @error('emergency_contact_number')<p id="emergency_contact_number-error" class="error">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="profile_photo_upload"><span data-i18n="Replacement profile photo">{{ __('Replacement profile photo') }}</span> <span class="hint" data-i18n="(optional)">{{ __('(optional)') }}</span></label>
                    <input id="profile_photo_upload" name="profile_photo_upload" type="file" accept="image/jpeg,image/png,image/webp" aria-describedby="profile_photo_upload-hint @error('profile_photo_upload') profile_photo_upload-error @enderror" @error('profile_photo_upload') aria-invalid="true" @enderror>
                    <p id="profile_photo_upload-hint" class="hint"><span data-i18n="JPEG, PNG, or WebP; 100–4000 pixels per side; maximum 2 MB. Metadata is removed.">{{ __('JPEG, PNG, or WebP; 100–4000 pixels per side; maximum 2 MB. Metadata is removed.') }}</span></p>
                    @error('profile_photo_upload')<p id="profile_photo_upload-error" class="error">{{ $message }}</p>@enderror
                </div>
            </div>
        </fieldset>

        <div class="field confirmation-field">
            <x-auth.checkbox name="confirm_profile_update" id="confirm_profile_update" value="1" required>
                <span data-i18n="I confirm that these profile details are accurate.">{{ __('I confirm that these profile details are accurate.') }}</span>
            </x-auth.checkbox>
        </div>
        <div class="form-actions"><button class="button button-primary" type="submit" data-i18n="Save profile">{{ __('Save profile') }}</button></div>
    </form>

    <form class="form-card password-form" method="POST" action="{{ route('portal.profile.password') }}" aria-labelledby="password-form-heading">
        @csrf
        @method('PATCH')
        <h2 id="password-form-heading" data-i18n="Change password">{{ __('Change password') }}</h2>
        <p class="hint"><span data-i18n="A successful change signs you out from every device. Password fields allow paste and are never repopulated after an error.">{{ __('A successful change signs you out from every device. Password fields allow paste and are never repopulated after an error.') }}</span></p>
        <div class="form-grid">
            <div class="field">
                <label for="current_password" data-i18n="Current password">{{ __('Current password') }}</label>
                <div class="password-field">
                    <input id="current_password" name="current_password" type="password" required maxlength="255" autocomplete="current-password" @error('current_password') aria-invalid="true" aria-describedby="current_password-error" @enderror>
                    <button class="password-toggle" type="button" data-password-toggle="current_password" aria-label="Show password" aria-pressed="false"><svg class="password-eye password-eye-show" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"/><circle cx="12" cy="12" r="2.5"/></svg><svg class="password-eye password-eye-hide" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3l18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 5.2A10.8 10.8 0 0 1 12 5c6.5 0 10 7 10 7a18.2 18.2 0 0 1-3.1 3.9M6.2 6.2C3.5 8.2 2 12 2 12s3.5 7 10 7a10.8 10.8 0 0 0 3.1-.5"/></svg></button>
                </div>
                @error('current_password')<p id="current_password-error" class="error">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label for="password" data-i18n="New password">{{ __('New password') }}</label>
                <div class="password-field">
                    <input id="password" name="password" type="password" required autocomplete="new-password" aria-describedby="password-hint @error('password') password-error @enderror" @error('password') aria-invalid="true" @enderror>
                    <button class="password-toggle" type="button" data-password-toggle="password" aria-label="Show password" aria-pressed="false"><svg class="password-eye password-eye-show" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"/><circle cx="12" cy="12" r="2.5"/></svg><svg class="password-eye password-eye-hide" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3l18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 5.2A10.8 10.8 0 0 1 12 5c6.5 0 10 7 10 7a18.2 18.2 0 0 1-3.1 3.9M6.2 6.2C3.5 8.2 2 12 2 12s3.5 7 10 7a10.8 10.8 0 0 0 3.1-.5"/></svg></button>
                </div>
                <p id="password-hint" class="hint"><span data-i18n="At least 12 characters, including letters and numbers.">{{ __('At least 12 characters, including letters and numbers.') }}</span></p>
                @error('password')<p id="password-error" class="error">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label for="password_confirmation" data-i18n="Confirm new password">{{ __('Confirm new password') }}</label>
                <div class="password-field">
                    <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
                    <button class="password-toggle" type="button" data-password-toggle="password_confirmation" aria-label="Show password" aria-pressed="false"><svg class="password-eye password-eye-show" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"/><circle cx="12" cy="12" r="2.5"/></svg><svg class="password-eye password-eye-hide" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3l18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 5.2A10.8 10.8 0 0 1 12 5c6.5 0 10 7 10 7a18.2 18.2 0 0 1-3.1 3.9M6.2 6.2C3.5 8.2 2 12 2 12s3.5 7 10 7a10.8 10.8 0 0 0 3.1-.5"/></svg></button>
                </div>
            </div>
        </div>
        <div class="form-actions"><button class="button button-primary" type="submit" data-i18n="Change password and sign out">{{ __('Change password and sign out') }}</button></div>
    </form>
@endsection
