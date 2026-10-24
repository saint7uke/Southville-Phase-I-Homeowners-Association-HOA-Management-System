<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Homeowner registration | Southville Phase I HOA') }}</title>
    <x-favicon />
    @vite(['resources/css/landing.css', 'resources/js/app.js'])
    <style>
        .hoa-auth-shell { min-height: 100svh; display: grid; place-items: center; padding: 2rem 1rem; position: relative; background: var(--hoa-navy); }
        .hoa-auth-overlay { position: absolute; inset: 0; background: linear-gradient(135deg, rgb(5 38 57 / .92) 0%, rgb(8 44 66 / .85) 100%); z-index: 1; }
        .hoa-auth-card { position: relative; z-index: 2; width: min(100%, 32rem); padding: clamp(2rem, 5vw, 3rem); border-radius: 20px; background: #fff; color: var(--hoa-ink); color-scheme: light; box-shadow: 0 25px 50px rgb(2 16 24 / .25); transition: width .3s ease; }
        .hoa-auth-card.is-wide { width: min(100%, 46rem); }
        .hoa-auth-brand { display: flex; flex-direction: column; align-items: center; text-align: center; margin-bottom: 2rem; color: var(--hoa-ink); }
        .hoa-auth-brand .hoa-brand-mark { width: 48px; height: 48px; font-size: 1rem; margin-bottom: 1rem; color: var(--hoa-navy); background: #e3f5fc; border-color: transparent; }
        .hoa-auth-brand h1 { font-size: 1.5rem; letter-spacing: -.03em; margin-bottom: .3rem; }
        .hoa-auth-brand p { color: var(--hoa-muted); font-size: .85rem; }
        
        .hoa-step { display: none; }
        .hoa-step.is-active { display: block; animation: fadeIn .4s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        
        .hoa-role-cards { display: grid; gap: 1rem; margin-top: 1.5rem; }
        .hoa-role-card { border: 2px solid var(--hoa-line); border-radius: 12px; padding: 1.5rem; text-align: left; cursor: pointer; transition: all .2s ease; background: #fff; display: flex; flex-direction: column; gap: .5rem; }
        .hoa-role-card:hover, .hoa-role-card:focus-visible { border-color: var(--hoa-blue); box-shadow: 0 8px 20px rgb(8 121 173 / .1); transform: translateY(-3px); outline: none; }
        .hoa-role-card.is-selected { border-color: var(--hoa-blue-dark); background: #f2fbff; box-shadow: 0 0 0 1px var(--hoa-blue-dark); }
        .hoa-role-card h3 { font-size: 1.15rem; color: var(--hoa-blue-dark); margin: 0; }
        .hoa-role-card p { font-size: .85rem; color: var(--hoa-muted); margin: 0; line-height: 1.4; }
        
        .hoa-form-group { display: grid; gap: 1rem; }
        .hoa-form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .hoa-field label { display: block; margin-bottom: .4rem; font-size: .78rem; font-weight: 700; color: var(--hoa-ink); }
        .hoa-field label > span[aria-hidden="true"] { color: var(--hoa-red); }
        .hoa-field input::placeholder { color: #596a76; opacity: 1; }
        .hoa-phase-input { display: flex; align-items: center; gap: .6rem; }
        .hoa-phase-input > span { flex-shrink: 0; color: var(--hoa-ink); font-weight: 600; }
        .hoa-field input, .hoa-field select { width: 100%; min-height: 46px; padding: .6rem .9rem; border: 1px solid var(--hoa-line); border-radius: 8px; font-family: inherit; font-size: .95rem; color: var(--hoa-ink); transition: border-color .2s; background: #fff; }
        .hoa-field input:focus, .hoa-field select:focus { outline: none; border-color: var(--hoa-blue-dark); box-shadow: 0 0 0 3px rgb(102 199 244 / .15); }
        .hoa-field input[aria-invalid="true"] { border-color: var(--hoa-red); }
        .hoa-field input[type="file"] { padding: .45rem .8rem; font-size: .85rem; }
        
        .hoa-hint { margin-top: .35rem; font-size: .75rem; color: var(--hoa-muted); }
        .hoa-error-text { margin-top: .4rem; font-size: .75rem; color: var(--hoa-red); font-weight: 600; }
        .hoa-field-status { display: flex; align-items: flex-start; gap: .45rem; margin-top: .5rem; padding: .55rem .7rem; border: 1px solid #94a3b8; border-radius: 8px; background: #f8fafc; color: #344054; font-size: .8rem; font-weight: 650; line-height: 1.4; }
        .hoa-field-status::before { flex: 0 0 auto; font-weight: 800; content: '…'; }
        .hoa-field-status[data-state="success"] { border-color: #75b798; background: #edf8f1; color: #146c43; }
        .hoa-field-status[data-state="success"]::before { content: '✓'; }
        .hoa-field-status[data-state="error"] { border-color: #e3a3aa; background: #fff1f2; color: #9f1239; }
        .hoa-field-status[data-state="error"]::before { content: '!'; }
        .hoa-password-requirements { display: grid; gap: .28rem; margin: .65rem 0 0; padding: 0; list-style: none; color: var(--hoa-muted); font-size: .75rem; }
        .hoa-password-requirements li { display: flex; align-items: center; gap: .45rem; }
        .hoa-password-requirements li > span[aria-hidden="true"] { display: inline-grid; width: 1rem; height: 1rem; flex: 0 0 1rem; place-items: center; border: 1px solid currentColor; border-radius: 50%; font-size: .7rem; line-height: 1; }
        .hoa-password-requirements li > span[data-i18n] { flex: 1; min-width: 0; line-height: 1.5; }
        .hoa-password-requirements li.is-valid { color: #16734d; }
        .hoa-password-requirements li.is-valid > span[aria-hidden="true"] { border-color: currentColor; background: currentColor; }
        .hoa-password-requirements li.is-valid > span[aria-hidden="true"]::before { color: #fff; content: '✓'; }
        .hoa-password-requirements li.is-invalid { color: var(--hoa-red); }
        .hoa-password-requirements li.is-invalid > span[aria-hidden="true"]::before { content: '•'; }
        .hoa-field input.is-valid { border-color: #16734d; }
        .hoa-field input.is-invalid { border-color: var(--hoa-red); }
        .hoa-auth-actions { margin-top: 2rem; display: flex; justify-content: space-between; align-items: center; }
        
        .hoa-auth-footer { margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--hoa-line); text-align: center; font-size: .85rem; color: var(--hoa-muted); }
        .hoa-auth-footer a { color: var(--hoa-blue-dark); font-weight: 700; transition: color .2s; }
        .hoa-auth-footer a:hover { color: var(--hoa-blue); }
        
        .hoa-checkbox-label { display: flex; align-items: flex-start; gap: .7rem; font-size: .85rem; color: var(--hoa-ink); cursor: pointer; margin-top: 1rem; }
        .hoa-checkbox-label input { width: 18px; height: 18px; margin-top: .15rem; accent-color: var(--hoa-blue-dark); cursor: pointer; }
        
        @media (max-width: 640px) {
            .hoa-form-grid-2 { grid-template-columns: 1fr; }
            .hoa-auth-actions { flex-direction: column-reverse; gap: 1rem; }
            .hoa-auth-actions .hoa-button { width: 100%; }
        }
    </style>
</head>
<body class="hoa-landing">
    <main class="hoa-auth-shell hoa-portal-auth" aria-labelledby="register-title">
        <div class="hoa-auth-overlay"></div>
        <aside class="hoa-portal-auth-intro">
            <a class="hoa-portal-auth-brand" href="{{ route('home') }}" aria-label="Go to Southville Phase I HOA homepage">
                <span class="hoa-portal-auth-logo"><img src="{{ asset('images/HOA.png') }}" alt=""></span>
                <span>Southville Phase I HOA</span>
            </a>
            <p class="hoa-portal-auth-portal" data-i18n="Homeowner registration">{{ __('Homeowner registration') }}</p>
            <h1 data-i18n="Join your HOA community.">{{ __('Join your HOA community.') }}</h1>
            <p class="hoa-portal-auth-support"><span data-i18n="Complete the guided application so the HOA team can verify your household and provide secure account access.">{{ __('Complete the guided application so the HOA team can verify your household and provide secure account access.') }}</span></p>
        </aside>
        <section class="hoa-auth-card hoa-portal-auth-form" id="auth-card" aria-labelledby="register-title">
            <x-locale-toolbar appearance="light" />
            <a href="{{ route('home') }}" class="hoa-auth-brand" aria-label="Go to homepage">
                <span class="hoa-brand-mark" aria-hidden="true"><img src="{{ asset('images/HOA.png') }}" width="48" height="48" alt=""></span>
                <h1 id="register-title" data-i18n="Create your homeowner account">{{ __('Create your homeowner account') }}</h1>
                <p><span data-i18n="Tell us how you use your home so we can verify your application.">{{ __('Tell us how you use your home so we can verify your application.') }}</span></p>
            </a>
            
            @if($errors->any())
                <div class="hoa-contact-error" role="alert" style="margin-top: 0; margin-bottom: 1.5rem;">
                    <strong data-i18n="Please correct the following:">{{ __('Please correct the following:') }}</strong>
                    <ul style="margin: .5rem 0 0; padding-left: 1.2rem; font-size: .8rem;">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('homeowner.register.store') }}" data-review-registration data-email-availability-url="{{ route('homeowner.register.email-availability') }}" data-address-availability-url="{{ route('homeowner.register.address-availability') }}" data-landlord-availability-url="{{ route('homeowner.register.landlord-availability') }}" enctype="multipart/form-data">
                @csrf
                
                <!-- STEP 1: Role Selection -->
                <div id="step-1" class="hoa-step is-active">
                    <h2 style="font-size: 1.1rem; text-align: center; margin-bottom: 1rem;" data-i18n="How do you use this home?">{{ __('How do you use this home?') }}</h2>
                    
                    <div class="hoa-role-cards">
                        <button type="button" class="hoa-role-card" data-role-card="Owner" aria-pressed="false" onclick="selectRole('Owner')">
                            <span class="hoa-role-icon" aria-hidden="true">⌂</span>
                            <h3 data-i18n="Resident / Homeowner">{{ __('Resident / Homeowner') }}</h3>
                            <p data-i18n="I own this property or I am an authorized resident.">{{ __('I own this property or I am an authorized resident.') }}</p>
                        </button>
                        
                        <button type="button" class="hoa-role-card" data-role-card="Tenant" aria-pressed="false" onclick="selectRole('Tenant')">
                            <span class="hoa-role-icon" aria-hidden="true">↔</span>
                            <h3 data-i18n="Rentee / Tenant">{{ __('Rentee / Tenant') }}</h3>
                            <p data-i18n="I am renting this property from a homeowner or landlord.">{{ __('I am renting this property from a homeowner or landlord.') }}</p>
                        </button>
                        
                        <button type="button" class="hoa-role-card" onclick="selectRole('Co-owner')" style="display: none;" id="btn-co-owner">
                            <h3 data-i18n="Co-owner">{{ __('Co-owner') }}</h3>
                        </button>
                    </div>
                    
                    <div class="hoa-auth-footer">
                        <span data-i18n="Already have an account?">{{ __('Already have an account?') }}</span> <a href="{{ route('homeowner.login') }}" data-i18n="Log in here →">{{ __('Log in here →') }}</a>
                    </div>
                </div>

                <!-- STEP 2: Registration Form -->
                <div id="step-2" class="hoa-step">
                    <input type="hidden" id="ownership_type" name="ownership_type" value="{{ old('ownership_type', 'Owner') }}">
                    
                    <div class="hoa-form-group">
                        <div class="hoa-form-grid-2">
                            @php($fields = ['first_name' => 'First name', 'middle_name' => 'Middle name', 'last_name' => 'Last name', 'street' => 'Street', 'block' => 'Block', 'lot' => 'Lot'])
                            @foreach($fields as $name => $label)
                                @php($isAddressField = in_array($name, ['street', 'block', 'lot'], true))
                                @php($addressFieldHasError = $isAddressField && $errors->has('address'))
                                <div class="hoa-field">
                                    <label for="{{ $name }}"><span data-i18n="{{ $label }}">{{ __($label) }}</span> @if($name !== 'middle_name')<span aria-hidden="true">*</span>@endif</label>
                                    <input id="{{ $name }}" name="{{ $name }}" value="{{ old($name) }}" @if($name !== 'middle_name') required @endif maxlength="{{ in_array($name, ['block', 'lot'], true) ? 20 : 100 }}" @if($isAddressField || $errors->has($name)) aria-describedby="{{ $isAddressField ? 'address-availability-message ' : '' }}{{ $errors->has($name) ? $name.'-error' : '' }}" @endif @if($errors->has($name) || $addressFieldHasError) aria-invalid="true" class="is-invalid" @endif>
                                    @error($name)<p id="{{ $name }}-error" class="hoa-error-text">{{ $message }}</p>@enderror
                                </div>
                            @endforeach
                            <div class="hoa-field">
                                <label for="phase_suffix"><span data-i18n="Phase">{{ __('Phase') }}</span> <span aria-hidden="true">*</span></label>
                                <div class="hoa-phase-input"><span id="phase-prefix">Phase 1 -</span><input id="phase_suffix" name="phase_suffix" value="{{ old('phase_suffix') }}" required maxlength="10" pattern="[A-Za-z0-9]{1,10}" autocomplete="off" aria-describedby="phase-prefix phase-help address-availability-message @error('phase_suffix') phase_suffix-error @enderror" @if($errors->has('phase_suffix')) aria-invalid="true" @endif></div>
                                <p id="phase-help" class="hoa-hint" data-i18n="Enter the phase suffix using 1–10 letters or digits.">{{ __('Enter the phase suffix using 1–10 letters or digits.') }}</p>
                                @error('phase_suffix')<p id="phase_suffix-error" class="hoa-error-text">{{ $message }}</p>@enderror
                            </div>
                            
                            <div class="hoa-field">
                                <label for="suffix" data-i18n="Suffix">{{ __('Suffix') }}</label>
                                <select id="suffix" name="suffix">
                                    <option value="" data-i18n="None">{{ __('None') }}</option>
                                    @foreach(['Jr.','Sr.','II','III','IV'] as $value)
                                        <option @selected(old('suffix') === $value)>{{ $value }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <p id="address-availability-message" class="hoa-field-status" data-state="{{ $errors->has('address') ? 'error' : 'neutral' }}" role="status" aria-live="polite" aria-atomic="true" @if(! $errors->has('address')) hidden @endif>{{ $errors->first('address') }}</p>

                        <div class="hoa-form-grid-2">
                            <div class="hoa-field">
                                <label for="sex"><span data-i18n="Sex">{{ __('Sex') }}</span> <span aria-hidden="true">*</span></label>
                                <select id="sex" name="sex" required>
                                    <option value="" disabled selected data-i18n="Select sex">{{ __('Select sex') }}</option>
                                    @foreach(['Male','Female','Prefer not to say'] as $value)
                                        <option value="{{ $value }}" @selected(old('sex') === $value)>{{ $value }}</option>
                                    @endforeach
                                </select>
                            </div>
                            
                            <div class="hoa-field">
                                <label for="date_of_birth"><span data-i18n="Date of birth">{{ __('Date of birth') }}</span> <span aria-hidden="true">*</span></label>
                                <input id="date_of_birth" name="date_of_birth" type="date" value="{{ old('date_of_birth') }}" max="{{ now()->subDay()->toDateString() }}" required autocomplete="bday">
                                @error('date_of_birth')<p class="hoa-error-text">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div class="hoa-form-grid-2">
                            <div class="hoa-field">
                                <label for="contact_number"><span data-i18n="Contact number">{{ __('Contact number') }}</span> <span aria-hidden="true">*</span></label>
                                <input id="contact_number" name="contact_number" inputmode="numeric" pattern="09[0-9]{9}" maxlength="11" value="{{ old('contact_number') }}" required autocomplete="tel" aria-describedby="contact-hint" placeholder="09XXXXXXXXX">
                                <p id="contact-hint" class="hoa-hint">11-digit mobile number beginning with 09.</p>
                                @error('contact_number')<p class="hoa-error-text">{{ $message }}</p>@enderror
                            </div>
                            
                            <div class="hoa-field">
                                <label for="email"><span data-i18n="Email address">{{ __('Email address') }}</span> <span aria-hidden="true">*</span></label>
                                <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" aria-describedby="email-hint email-availability-message">
                                <p id="email-hint" class="hoa-hint"><span data-i18n="Use an email address that has not been used for another HOA account.">{{ __('Use an email address that has not been used for another HOA account.') }}</span></p>
                                <p id="email-availability-message" class="hoa-field-status" data-state="neutral" role="status" aria-live="polite" aria-atomic="true" hidden></p>
                                @error('email')<p class="hoa-error-text">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        
                        <div class="hoa-form-grid-2">
                            <div class="hoa-field">
                                <label for="password"><span data-i18n="Password">{{ __('Password') }}</span> <span aria-hidden="true">*</span></label>
                                <div class="hoa-password-field">
                                    <input id="password" name="password" type="password" required autocomplete="new-password" aria-describedby="password-hint password-requirements">
                                    <button class="hoa-password-toggle" type="button" data-password-toggle="password" aria-label="Show password" aria-pressed="false">
                                        <svg class="password-eye password-eye-show" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                                        <svg class="password-eye password-eye-hide" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3l18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 5.2A10.8 10.8 0 0 1 12 5c6.5 0 10 7 10 7a18.2 18.2 0 0 1-3.1 3.9M6.2 6.2C3.5 8.2 2 12 2 12s3.5 7 10 7a10.8 10.8 0 0 0 3.1-.5"/></svg>
                                    </button>
                                </div>
                                <p id="password-hint" class="hoa-hint"><span data-i18n="Use a strong password that meets all of the requirements below.">{{ __('Use a strong password that meets all of the requirements below.') }}</span></p>
                                <ul id="password-requirements" class="hoa-password-requirements" aria-label="{{ __('Password requirements') }}" data-i18n-aria-label="Password requirements">
                                    <li data-password-requirement="length"><span aria-hidden="true"></span><span data-i18n="At least 12 characters">{{ __('At least 12 characters') }}</span></li>
                                    <li data-password-requirement="uppercase"><span aria-hidden="true"></span><span data-i18n="At least one uppercase letter">{{ __('At least one uppercase letter') }}</span></li>
                                    <li data-password-requirement="lowercase"><span aria-hidden="true"></span><span data-i18n="At least one lowercase letter">{{ __('At least one lowercase letter') }}</span></li>
                                    <li data-password-requirement="number"><span aria-hidden="true"></span><span data-i18n="At least one number">{{ __('At least one number') }}</span></li>
                                    <li data-password-requirement="symbol"><span aria-hidden="true"></span><span data-i18n="At least one symbol">{{ __('At least one symbol') }}</span></li>
                                </ul>
                                @error('password')<p class="hoa-error-text">{{ $message }}</p>@enderror
                            </div>
                            <div class="hoa-field">
                                <label for="password_confirmation"><span data-i18n="Confirm password">{{ __('Confirm password') }}</span> <span aria-hidden="true">*</span></label>
                                <div class="hoa-password-field">
                                    <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" aria-describedby="password-confirmation-status">
                                    <button class="hoa-password-toggle" type="button" data-password-toggle="password_confirmation" aria-label="Show password" aria-pressed="false">
                                        <svg class="password-eye password-eye-show" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                                        <svg class="password-eye password-eye-hide" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3l18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 5.2A10.8 10.8 0 0 1 12 5c6.5 0 10 7 10 7a18.2 18.2 0 0 1-3.1 3.9M6.2 6.2C3.5 8.2 2 12 2 12s3.5 7 10 7a10.8 10.8 0 0 0 3.1-.5"/></svg>
                                    </button>
                                </div>
                                <p id="password-confirmation-status" class="hoa-hint" aria-live="polite"></p>
                            </div>
                        </div>

                        <!-- Dynamic Fields Area -->
                        <div class="hoa-form-grid-2 owner-field">
                            <div class="hoa-field">
                                <label for="residency_date"><span data-i18n="Residency date (Move-in)">{{ __('Residency date (Move-in)') }}</span> <span aria-hidden="true">*</span></label>
                                <input id="residency_date" name="residency_date" type="date" value="{{ old('residency_date') }}" max="{{ today()->toDateString() }}">
                                @error('residency_date')<p class="hoa-error-text">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div class="hoa-form-grid-2 tenant-field" style="display:none;">
                            <div class="hoa-field">
                                <label for="landlord_name"><span data-i18n="Landlord name">{{ __('Landlord name') }}</span> <span aria-hidden="true">*</span></label>
                                <input id="landlord_name" name="landlord_name" type="text" maxlength="100" value="{{ old('landlord_name') }}" aria-describedby="landlord-availability-message{{ $errors->has('landlord_name') ? ' landlord_name-error' : '' }}" @error('landlord_name') aria-invalid="true" class="is-invalid" @enderror>
                                <p id="landlord-availability-message" class="hoa-field-status" data-state="{{ $errors->has('landlord_name') ? 'error' : 'neutral' }}" role="status" aria-live="polite" aria-atomic="true" @if(! $errors->has('landlord_name')) hidden @endif>{{ $errors->first('landlord_name') }}</p>
                                @error('landlord_name')<p id="landlord_name-error" class="hoa-error-text">{{ $message }}</p>@enderror
                            </div>
                            <div class="hoa-field">
                                <label for="landlord_contact"><span data-i18n="Landlord contact">{{ __('Landlord contact') }}</span> <span aria-hidden="true">*</span></label>
                                <input id="landlord_contact" name="landlord_contact" inputmode="numeric" pattern="09[0-9]{9}" maxlength="11" value="{{ old('landlord_contact') }}" placeholder="09XXXXXXXXX">
                                @error('landlord_contact')<p class="hoa-error-text">{{ $message }}</p>@enderror
                            </div>
                            <div class="hoa-field">
                                <label for="lease_start_date"><span data-i18n="Lease start date">{{ __('Lease start date') }}</span> <span aria-hidden="true">*</span></label>
                                <input id="lease_start_date" name="lease_start_date" type="date" value="{{ old('lease_start_date') }}">
                                @error('lease_start_date')<p class="hoa-error-text">{{ $message }}</p>@enderror
                            </div>
                            <div class="hoa-field">
                                <label for="lease_end_date"><span data-i18n="Lease end date">{{ __('Lease end date') }}</span> <span aria-hidden="true">*</span></label>
                                <input id="lease_end_date" name="lease_end_date" type="date" value="{{ old('lease_end_date') }}">
                                @error('lease_end_date')<p class="hoa-error-text">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        
                        <div class="hoa-field">
                            <label for="verification_document" data-i18n="Verification document (ID / Lease / Title)">{{ __('Verification document (ID / Lease / Title)') }}</label>
                            <input id="verification_document" name="verification_document" type="file" accept=".pdf,.jpg,.jpeg,.png">
                            <p class="hoa-hint"><span data-i18n="Upload a PDF or image when requested by the HOA (max 10MB).">{{ __('Upload a PDF or image when requested by the HOA (max 10MB).') }}</span></p>
                            @error('verification_document')<p class="hoa-error-text">{{ $message }}</p>@enderror
                        </div>

                        <x-auth.checkbox name="privacy_consent" value="1" required :checked="(bool) old('privacy_consent')">
                            <span data-i18n="I consent to the HOA processing my information for resident verification and community services.">{{ __('I consent to the HOA processing my information for resident verification and community services.') }}</span> <span aria-hidden="true">*</span>
                        </x-auth.checkbox>
                    </div>

                    <div class="hoa-auth-actions">
                        <button type="button" class="hoa-button hoa-button-ghost" onclick="goBack()" style="color: var(--hoa-ink); border-color: var(--hoa-line);">← Back</button>
                        <button class="hoa-button hoa-button-primary" type="submit" data-i18n="Submit application">{{ __('Submit application') }}</button>
                    </div>
                </div>
            </form>
        </section>
    </main>

    <script>
        function selectRole(role) {
            const ownershipType = document.getElementById('ownership_type');
            ownershipType.value = role;
            ownershipType.dispatchEvent(new Event('change', { bubbles: true }));
            document.querySelector('[data-review-registration]')?.dispatchEvent(new Event('ownership-type-changed'));
            document.querySelectorAll('[data-role-card]').forEach((card) => {
                const selected = card.dataset.roleCard === role;
                card.classList.toggle('is-selected', selected);
                card.setAttribute('aria-pressed', selected ? 'true' : 'false');
            });
            
            // Switch steps
            document.getElementById('step-1').classList.remove('is-active');
            document.getElementById('step-2').classList.add('is-active');
            
            // Widen the card for the form
            document.getElementById('auth-card').classList.add('is-wide');
            
            toggleDynamicFields(role);
        }
        
        function goBack() {
            // Switch steps
            document.getElementById('step-2').classList.remove('is-active');
            document.getElementById('step-1').classList.add('is-active');
            
            // Narrow the card for role selection
            document.getElementById('auth-card').classList.remove('is-wide');
        }

        function toggleDynamicFields(type) {
            const tenantFields = document.querySelectorAll('.tenant-field');
            const ownerFields = document.querySelectorAll('.owner-field');
            
            if (type === 'Tenant') {
                tenantFields.forEach(f => { f.style.display = 'grid'; f.querySelectorAll('input').forEach(i => i.required = true); });
                ownerFields.forEach(f => { f.style.display = 'none'; f.querySelectorAll('input').forEach(i => i.required = false); });
            } else {
                tenantFields.forEach(f => { f.style.display = 'none'; f.querySelectorAll('input').forEach(i => i.required = false); });
                ownerFields.forEach(f => { f.style.display = 'grid'; f.querySelectorAll('input').forEach(i => i.required = true); });
            }
        }
        
        // If there are validation errors, we want to stay on step 2
        document.addEventListener("DOMContentLoaded", () => {
            const hasErrors = {{ $errors->any() ? 'true' : 'false' }};
            const role = document.getElementById('ownership_type').value;
            
            if (hasErrors || role) {
                // Pre-select and go to step 2 if old value exists or there are errors
                if (role && hasErrors) {
                    selectRole(role);
                }
            }
        });
    </script>
</body>
</html>
