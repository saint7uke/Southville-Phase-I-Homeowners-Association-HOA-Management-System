@props(['scope' => 'registration', 'user' => null])
@php
    $user = $user?->fresh();
    $preference = $user ? \App\Models\SmsContactPreference::where('user_id', $user->id)->first() : null;
    $available = app(\App\Services\Sms\SmsEligibility::class)->enabled();
@endphp
<section data-sms-enrollment data-scope="{{ $scope }}" data-request-url="{{ route('sms.verification.request') }}"
    data-confirm-url="{{ route('sms.verification.confirm') }}" data-preferences-url="{{ route('sms.preferences') }}"
    wire:key="sms-enrollment-{{ $user?->id ?? 'guest' }}-{{ hash('sha256', (string) $user?->contact_number) }}"
    data-available="{{ $available ? 'true' : 'false' }}"
    data-saved-number="{{ $scope === 'profile' ? \App\Support\PhilippineMobile::local($user?->contact_number) : '' }}"
    class="hoa-sms">
    <h2 class="font-semibold">{{ __('Optional SMS notifications') }}</h2>
    <p>{{ __('SMS consent is optional and separate from privacy consent. Registration and account access do not require SMS enrollment.') }}</p>
    @if ($scope === 'profile')
        <p>{{ __('Current mobile number') }}: {{ \App\Support\PhilippineMobile::mask(\App\Support\PhilippineMobile::normalize($user?->contact_number)) }}</p>
        <p>{{ __('Save contact changes before verifying a replacement number. Changed numbers must be verified again.') }}</p>
    @else
        <p class="hoa-sms-destination">{{ __('Verification destination') }}: <strong data-sms-destination></strong>
            <a href="#contact_number" data-sms-edit>{{ __('Edit mobile number') }}</a></p>
    @endif
    @if (! $available)
        <p role="status">{{ __('SMS enrollment is currently unavailable. You may continue without SMS enrollment.') }}</p>
    @endif
    @foreach (['sms_transactional_opt_in' => 'I consent to account, payment, complaint and dues SMS notifications.', 'sms_announcement_opt_in' => 'I consent to Important HOA announcement SMS notifications.'] as $name => $label)
        <label class="hoa-sms-consent"><input type="checkbox" name="{{ $name }}" value="1"
            @checked($scope === 'registration' ? (bool) old($name) : (bool) ($preference?->{str_replace('sms_', '', $name)}))
            @disabled(! $available && $scope === 'registration')><span>{{ __($label) }}</span></label>
    @endforeach
    <input type="hidden" name="sms_verification_id" value="{{ $scope === 'registration' && is_string(old('sms_verification_id')) ? old('sms_verification_id') : '' }}">
    <div class="hoa-sms-actions">
        <button type="button" data-sms-request @disabled(! $available)>@svg('heroicon-o-paper-airplane', '', ['aria-hidden' => 'true', 'style' => 'width:1.1rem;height:1.1rem'])<span>{{ __('Send verification code') }}</span></button>
        <label class="hoa-sms-code">{{ __('SMS verification code') }}<input data-sms-code type="text" inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="000000" aria-describedby="sms-code-hint-{{ $scope }}"></label>
        <button type="button" data-sms-confirm disabled>@svg('heroicon-o-check-circle', '', ['aria-hidden' => 'true', 'style' => 'width:1.1rem;height:1.1rem'])<span>{{ __('Verify mobile number') }}</span></button>
        @if ($scope === 'profile')<button type="button" data-sms-save>@svg('heroicon-o-check', '', ['aria-hidden' => 'true', 'style' => 'width:1.1rem;height:1.1rem'])<span>{{ __('Save SMS preferences') }}</span></button>@endif
    </div>
    <p id="sms-code-hint-{{ $scope }}">{{ __('Enter the six-digit code sent to your mobile number. This is not your phone number.') }}</p>
    <p data-sms-cooldown role="status" aria-live="polite"></p>
    <p data-sms-result role="status" aria-live="polite"></p>
    @error('sms_verification_id')<p role="alert" class="hoa-error-text">{{ $message }}</p>@enderror
    <noscript><p>{{ __('JavaScript is required for optional SMS verification. You may continue without SMS enrollment.') }}</p></noscript>
</section>
