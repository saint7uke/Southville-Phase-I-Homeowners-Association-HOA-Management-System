import '../css/locale-switcher.css';
import { dictionaries, translate } from './translations';
export { dictionaries, translate } from './translations';

const runtime = window.__hoaLocaleSwitcher ??= { initialized: false, pending: null };
const allowedLocales = ['en', 'fil'];

function syncSwitchers(locale) {
    document.querySelectorAll('[data-locale-switcher]').forEach(wrapper => {
        const select = wrapper.querySelector('select');
        if (!select) return;
        select.value = runtime.pending || locale;
        select.disabled = runtime.pending !== null;
        wrapper.setAttribute('aria-busy', String(runtime.pending !== null));
    });
}

function announce(message) {
    document.querySelectorAll('[data-locale-switcher]').forEach(wrapper => {
        const status = wrapper.querySelector('[role="status"]');
        if (status && status.textContent !== message) status.textContent = message;
    });
}

// Only explicit application-owned labels are changed. Inputs and user content are never scanned or replaced.
function applyLocale(locale) {
    if (!allowedLocales.includes(locale)) return;
    document.documentElement.lang = locale;
    const { en, fil } = dictionaries;
    const reverse = Object.fromEntries(Object.entries(fil).map(([key, value]) => [value, key]));
    document.querySelectorAll('[data-i18n-section]').forEach(section => {
        const heading = section.querySelector('.fi-section-header-heading');
        if (heading) heading.dataset.i18n = section.dataset.i18nSection;
    });
    // Only exact, static list/page routes; record-detail headings can contain resident content.
    const pageTitles = {
        '/homeowner/my-profile': 'My Profile', '/homeowner/my-household': 'My Household',
        '/admin/reports': 'Reports', '/staff/reports': 'Reports',
        '/admin/projects': 'Projects',
    };
    const title = pageTitles[location.pathname.replace(/\/$/, '')];
    const heading = document.querySelector('h1.fi-header-heading');
    if (title && heading && [title, fil[title]].includes(heading.textContent.trim())) heading.dataset.i18n = title;
    // These selectors contain application form/navigation/action labels, never record cells or input values.
    document.querySelectorAll('.fi-fo-field-label-content, .fi-fo-field-wrp-label, .fi-sidebar-item-label, .fi-sidebar-group-label, .fi-ta-header-cell-label').forEach(label => {
        const walker = document.createTreeWalker(label, NodeFilter.SHOW_TEXT);
        let node;
        while ((node = walker.nextNode())) {
            const value = node.textContent.trim();
            const key = reverse[value] || value;
            if (Object.hasOwn(en, key) || Object.hasOwn(fil, key)) {
                const translated = translate(key, locale);
                if (value !== translated) node.textContent = node.textContent.replace(value, translated);
            }
        }
    });
    document.querySelectorAll('[data-i18n]').forEach(node => {
        // Explicit markers belong to application copy only; never annotate resident content.
        const value = (node.dataset.i18nPrefix || '') + translate(node.dataset.i18n, locale);
        if (node.textContent !== value) node.textContent = value;
    });
    document.querySelectorAll('[data-i18n-placeholder]').forEach(node => { node.placeholder = translate(node.dataset.i18nPlaceholder, locale); });
    document.querySelectorAll('[data-i18n-aria-label]').forEach(node => { node.setAttribute('aria-label', translate(node.dataset.i18nAriaLabel, locale)); });
    syncSwitchers(locale);
}

async function changeLocale(event) {
    if (!(event.target instanceof Element)) return;
    const select = event.target.closest('[data-locale-switcher] select');
    if (!select || select.disabled || runtime.pending !== null) return;
    const previous = document.documentElement.lang || 'en';
    const requested = select.value;
    if (!allowedLocales.includes(requested) || requested === previous) { syncSwitchers(previous); return; }
    runtime.pending = requested;
    syncSwitchers(previous);
    announce(translate('Changing language…', previous));
    try {
        const response = await fetch('/locale', {
            method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || document.querySelector('[data-csrf]')?.dataset.csrf || window.livewireScriptConfig?.csrf || '' }, body: JSON.stringify({ locale: requested }),
        });
        if (!response.ok) throw new Error('locale');
        const { locale } = await response.json();
        if (locale !== requested || !allowedLocales.includes(locale)) throw new Error('locale');
        applyLocale(locale);
        window.dispatchEvent(new CustomEvent('hoa:locale', { detail: { locale } }));
        // Do not refresh/remount Livewire: that could erase browser-only values and temporary uploads.
        announce(translate('Language updated. Your unsaved changes are preserved.', locale));
    } catch {
        announce(translate('Language could not be changed. Please try again.', previous));
    } finally {
        runtime.pending = null;
        syncSwitchers(document.documentElement.lang || previous);
    }
}

// Vite entries and Livewire navigation can encounter this module more than once.
if (!runtime.initialized) {
    runtime.initialized = true;
    document.addEventListener('change', changeLocale);
    document.addEventListener('livewire:navigated', () => applyLocale(document.documentElement.lang || 'en'));
    let scheduled = false;
    const observer = new MutationObserver(() => {
        if (scheduled) return;
        scheduled = true;
        requestAnimationFrame(() => { scheduled = false; applyLocale(document.documentElement.lang || 'en'); });
    });
    const start = () => {
        applyLocale(document.documentElement.lang || 'en');
        observer.observe(document.body, { childList: true, subtree: true });
        document.documentElement.dataset.localeReady = 'true';
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true }); else start();
}
