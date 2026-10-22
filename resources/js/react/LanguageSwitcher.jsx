import React from 'react';
import { useLocale } from './LocaleContext';

// The delegated locale.js handler owns requests and feedback in both React and Blade.
export default function LanguageSwitcher() {
    const { locale, t } = useLocale();
    return (
        <div className="hoa-locale-switcher" data-locale-switcher data-locale-theme="light" aria-busy="false">
            <label className="hoa-locale-sr-only" htmlFor="hoa-locale">{t('Language')}</label>
            <svg className="hoa-locale-icon hoa-locale-globe" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M3 12h18M12 3c3 3 4 6 4 9s-1 6-4 9c-3-3-4-6-4-9s1-6 4-9Z" /></svg>
            <svg className="hoa-locale-icon hoa-locale-spinner" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true"><circle cx="12" cy="12" r="9" opacity=".25" /><path d="M12 3a9 9 0 0 1 9 9" /></svg>
            <select className="hoa-locale-select" id="hoa-locale" aria-describedby="hoa-locale-status" autoComplete="off" defaultValue={locale}>
                <option value="en">English</option><option value="fil">Filipino</option>
            </select>
            <svg className="hoa-locale-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
            <span className="hoa-locale-sr-only" id="hoa-locale-status" role="status" aria-live="polite" aria-atomic="true" />
        </div>
    );
}
