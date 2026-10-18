@extends('layouts.portal')

@section('title', 'Profile')

@section('content')
    <header class="page-header">
        <h1>Your profile</h1>
        <p>Keep your identity, contact, property, and emergency information current.</p>
    </header>

    @if ($errors->any())
        <div id="form-errors" class="error-summary" role="alert" tabindex="-1">
            <strong>Please correct the following errors.</strong>
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
        <h2 id="profile-form-heading">Profile details</h2>

        @if ($user->homeowner->profile_photo)
            <figure class="profile-photo-preview">
                <img src="{{ route('homeowner.profile-photo', $user->homeowner) }}" alt="Profile photo of {{ $user->full_name }}" width="160" height="160">
                <figcaption>Current private profile photo</figcaption>
            </figure>
        @endif

        <fieldset class="profile-fieldset">
            <legend>Personal information</legend>
            <div class="form-grid two">
                <div class="field">
                    <label for="first_name">First name</label>
                    <input id="first_name" name="first_name" value="{{ old('first_name', $user->first_name) }}" required maxlength="100" autocomplete="given-name" @error('first_name') aria-invalid="true" aria-describedby="first_name-error" @enderror>
                    @error('first_name')<p id="first_name-error" class="error">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="middle_name">Middle name <span class="hint">(optional)</span></label>
                    <input id="middle_name" name="middle_name" value="{{ old('middle_name', $user->middle_name) }}" maxlength="100" autocomplete="additional-name" @error('middle_name') aria-invalid="true" aria-describedby="middle_name-error" @enderror>
                    @error('middle_name')<p id="middle_name-error" class="error">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="last_name">Last name</label>
                    <input id="last_name" name="last_name" value="{{ old('last_name', $user->last_name) }}" required maxlength="100" autocomplete="family-name" @error('last_name') aria-invalid="true" aria-describedby="last_name-error" @enderror>
                    @error('last_name')<p id="last_name-error" class="error">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="suffix">Suffix <span class="hint">(optional)</span></label>
                    <select id="suffix" name="suffix" @error('suffix') aria-invalid="true" aria-describedby="suffix-error" @enderror>
                        <option value="">Select an option</option>
                        @foreach (['Jr.', 'Sr.', 'II', 'III', 'IV', 'N/A'] as $suffix)
                            <option value="{{ $suffix }}" @selected(old('suffix', $user->suffix) === $suffix)>{{ $suffix }}</option>
                        @endforeach
                    </select>
                    @error('suffix')<p id="suffix-error" class="error">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="sex">Sex</label>
                    <select id="sex" name="sex" required @error('sex') aria-invalid="true" aria-describedby="sex-error" @enderror>
                        <option value="">Select an option</option>
                        @foreach (['Male', 'Female', 'Prefer not to say'] as $sex)
                            <option value="{{ $sex }}" @selected(old('sex', $user->sex) === $sex)>{{ $sex }}</option>
                        @endforeach
                    </select>
                    @error('sex')<p id="sex-error" class="error">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="date_of_birth">Date of birth</label>
                    <input id="date_of_birth" name="date_of_birth" type="date" value="{{ old('date_of_birth', $user->date_of_birth?->toDateString()) }}" max="{{ today()->subDay()->toDateString() }}" required autocomplete="bday" @error('date_of_birth') aria-invalid="true" aria-describedby="date_of_birth-error" @enderror>
                    <p class="hint">Current age: {{ $user->age }} years</p>
                    @error('date_of_birth')<p id="date_of_birth-error" class="error">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="email">Email address</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required maxlength="255" autocomplete="email" aria-describedby="email-hint @error('email') email-error @enderror" @error('email') aria-invalid="true" @enderror>
                    <p id="email-hint" class="hint">Changing this address requires email verification.</p>
                    @error('email')<p id="email-error" class="error">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="contact_number">Contact number</label>
                    <input id="contact_number" name="contact_number" value="{{ old('contact_number', $user->contact_number) }}" inputmode="numeric" pattern="09[0-9]{9}" maxlength="11" required autocomplete="tel" aria-describedby="contact_number-hint @error('contact_number') contact_number-error @enderror" @error('contact_number') aria-invalid="true" @enderror>
                    <p id="contact_number-hint" class="hint">11 digits beginning with 09.</p>
                    @error('contact_number')<p id="contact_number-error" class="error">{{ $message }}</p>@enderror
                </div>
            </div>
        </fieldset>

        <fieldset class="profile-fieldset">
            <legend>Property and emergency contact</legend>
            <div class="form-grid two">
                @foreach ([
                    'house_number' => ['House number', $user->homeowner->house_number, 'address-line1', 50],
                    'street' => ['Street', $user->homeowner->street, 'address-line2', 100],
                    'block' => ['Block', $user->homeowner->block, 'off', 20],
                    'lot' => ['Lot', $user->homeowner->lot, 'off', 20],
                    'phase' => ['Phase', $user->homeowner->phase, 'off', 50],
                ] as $field => [$label, $value, $autocomplete, $max])
                    <div class="field">
                        <label for="{{ $field }}">{{ $label }}</label>
                        <input id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $value) }}" maxlength="{{ $max }}" required autocomplete="{{ $autocomplete }}" @if($field === 'phase') readonly aria-readonly="true" @endif @error($field) aria-invalid="true" aria-describedby="{{ $field }}-error" @enderror>
                        @if ($field === 'phase')<p class="hint">Contact the HOA to change the registered phase.</p>@endif
                        @error($field)<p id="{{ $field }}-error" class="error">{{ $message }}</p>@enderror
                    </div>
                @endforeach
                <div class="field">
                    <label for="residency_date">Move-in date</label>
                    <input id="residency_date" name="residency_date" type="date" value="{{ old('residency_date', $user->homeowner->residency_date?->toDateString()) }}" max="{{ today()->toDateString() }}" required @error('residency_date') aria-invalid="true" aria-describedby="residency_date-error" @enderror>
                    @error('residency_date')<p id="residency_date-error" class="error">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="ownership_type">Occupancy type</label>
                    <select id="ownership_type" disabled aria-disabled="true" @error('ownership_type') aria-invalid="true" aria-describedby="ownership_type-error" @enderror>
                        <option value="">Select an option</option>
                        @foreach (['Owner', 'Tenant', 'Co-owner'] as $type)
                            <option value="{{ $type }}" @selected(old('ownership_type', $user->homeowner->ownership_type) === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                    <input type="hidden" name="ownership_type" value="{{ $user->homeowner->ownership_type }}">
                    <p class="hint">Contact the HOA to change the occupancy type.</p>
                    @error('ownership_type')<p id="ownership_type-error" class="error">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="emergency_contact_name">Emergency contact name</label>
                    <input id="emergency_contact_name" name="emergency_contact_name" value="{{ old('emergency_contact_name', $user->homeowner->emergency_contact_name) }}" maxlength="100" required autocomplete="name" @error('emergency_contact_name') aria-invalid="true" aria-describedby="emergency_contact_name-error" @enderror>
                    @error('emergency_contact_name')<p id="emergency_contact_name-error" class="error">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="emergency_contact_number">Emergency contact number</label>
                    <input id="emergency_contact_number" name="emergency_contact_number" value="{{ old('emergency_contact_number', $user->homeowner->emergency_contact_number) }}" inputmode="numeric" pattern="09[0-9]{9}" maxlength="11" required autocomplete="tel" @error('emergency_contact_number') aria-invalid="true" aria-describedby="emergency_contact_number-error" @enderror>
                    @error('emergency_contact_number')<p id="emergency_contact_number-error" class="error">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="profile_photo_upload">Replacement profile photo <span class="hint">(optional)</span></label>
                    <input id="profile_photo_upload" name="profile_photo_upload" type="file" accept="image/jpeg,image/png,image/webp" aria-describedby="profile_photo_upload-hint @error('profile_photo_upload') profile_photo_upload-error @enderror" @error('profile_photo_upload') aria-invalid="true" @enderror>
                    <p id="profile_photo_upload-hint" class="hint">JPEG, PNG, or WebP; 100–4000 pixels per side; maximum 2 MB. Metadata is removed.</p>
                    @error('profile_photo_upload')<p id="profile_photo_upload-error" class="error">{{ $message }}</p>@enderror
                </div>
            </div>
        </fieldset>

        <div class="field confirmation-field">
            <x-auth.checkbox name="confirm_profile_update" id="confirm_profile_update" value="1" required>
                I confirm that these profile details are accurate.
            </x-auth.checkbox>
        </div>
        <div class="form-actions"><button class="button button-primary" type="submit">Save profile</button></div>
    </form>

    <form class="form-card password-form" method="POST" action="{{ route('portal.profile.password') }}" aria-labelledby="password-form-heading">
        @csrf
        @method('PATCH')
        <h2 id="password-form-heading">Change password</h2>
        <p class="hint">A successful change signs you out from every device. Password fields allow paste and are never repopulated after an error.</p>
        <div class="form-grid">
            <div class="field">
                <label for="current_password">Current password</label>
                <div class="password-field">
                    <input id="current_password" name="current_password" type="password" required maxlength="255" autocomplete="current-password" @error('current_password') aria-invalid="true" aria-describedby="current_password-error" @enderror>
                    <button class="password-toggle" type="button" data-password-toggle="current_password" aria-label="Show password" aria-pressed="false"><svg class="password-eye password-eye-show" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"/><circle cx="12" cy="12" r="2.5"/></svg><svg class="password-eye password-eye-hide" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3l18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 5.2A10.8 10.8 0 0 1 12 5c6.5 0 10 7 10 7a18.2 18.2 0 0 1-3.1 3.9M6.2 6.2C3.5 8.2 2 12 2 12s3.5 7 10 7a10.8 10.8 0 0 0 3.1-.5"/></svg></button>
                </div>
                @error('current_password')<p id="current_password-error" class="error">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label for="password">New password</label>
                <div class="password-field">
                    <input id="password" name="password" type="password" required autocomplete="new-password" aria-describedby="password-hint @error('password') password-error @enderror" @error('password') aria-invalid="true" @enderror>
                    <button class="password-toggle" type="button" data-password-toggle="password" aria-label="Show password" aria-pressed="false"><svg class="password-eye password-eye-show" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"/><circle cx="12" cy="12" r="2.5"/></svg><svg class="password-eye password-eye-hide" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3l18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 5.2A10.8 10.8 0 0 1 12 5c6.5 0 10 7 10 7a18.2 18.2 0 0 1-3.1 3.9M6.2 6.2C3.5 8.2 2 12 2 12s3.5 7 10 7a10.8 10.8 0 0 0 3.1-.5"/></svg></button>
                </div>
                <p id="password-hint" class="hint">At least 12 characters, including letters and numbers.</p>
                @error('password')<p id="password-error" class="error">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label for="password_confirmation">Confirm new password</label>
                <div class="password-field">
                    <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
                    <button class="password-toggle" type="button" data-password-toggle="password_confirmation" aria-label="Show password" aria-pressed="false"><svg class="password-eye password-eye-show" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"/><circle cx="12" cy="12" r="2.5"/></svg><svg class="password-eye password-eye-hide" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3l18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 5.2A10.8 10.8 0 0 1 12 5c6.5 0 10 7 10 7a18.2 18.2 0 0 1-3.1 3.9M6.2 6.2C3.5 8.2 2 12 2 12s3.5 7 10 7a10.8 10.8 0 0 0 3.1-.5"/></svg></button>
                </div>
            </div>
        </div>
        <div class="form-actions"><button class="button button-primary" type="submit">Change password and sign out</button></div>
    </form>
@endsection
