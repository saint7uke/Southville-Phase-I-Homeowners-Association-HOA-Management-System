@props(['appearance' => 'auto'])
<div class="hoa-locale-switcher" data-locale-switcher data-locale-theme="{{ $appearance }}" aria-busy="false">
    <label class="hoa-locale-sr-only" for="hoa-locale" data-i18n="Language">{{ __('Language') }}</label>
    <svg class="hoa-locale-icon hoa-locale-globe" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3 4 6 4 9s-1 6-4 9c-3-3-4-6-4-9s1-6 4-9Z"/></svg>
    <svg class="hoa-locale-icon hoa-locale-spinner" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9" opacity=".25"/><path d="M12 3a9 9 0 0 1 9 9"/></svg>
    <select class="hoa-locale-select" id="hoa-locale" aria-describedby="hoa-locale-status" autocomplete="off">
        <option value="en" @selected(app()->getLocale() === 'en')>English</option>
        <option value="fil" @selected(app()->getLocale() === 'fil')>Filipino</option>
    </select>
    <svg class="hoa-locale-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
    <span class="hoa-locale-sr-only" id="hoa-locale-status" role="status" aria-live="polite" aria-atomic="true"></span>
</div>
@vite('resources/js/locale.js')
