@props(['inputId'])

<div {{ $attributes->class(['hoa-password-field']) }}>
    {{ $slot }}
    <button
        class="hoa-password-toggle"
        type="button"
        data-password-toggle="{{ $inputId }}"
        aria-label="Show password"
        aria-pressed="false"
    >
        <svg class="password-eye password-eye-show" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"/>
            <circle cx="12" cy="12" r="2.5"/>
        </svg>
        <svg class="password-eye password-eye-hide" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M3 3l18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 5.2A10.8 10.8 0 0 1 12 5c6.5 0 10 7 10 7a18.2 18.2 0 0 1-3.1 3.9M6.2 6.2C3.5 8.2 2 12 2 12s3.5 7 10 7a10.8 10.8 0 0 0 3.1-.5"/>
        </svg>
    </button>
</div>
