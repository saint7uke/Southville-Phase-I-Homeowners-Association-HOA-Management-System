import Swal from 'sweetalert2/dist/sweetalert2.js';
import { translate } from './translations';
import { createFeedbackQueue, requestFeedback } from './alert-policy';
import '../css/alerts.css';

const t = text => translate(String(text ?? ''), document.documentElement.lang);
const messageText = text => String(text ?? '').split('\n').map(t).join('\n');
let confirmation = null;
const defaults = () => ({
    customClass: { container: 'hoa-alert-container', popup: 'hoa-alert', confirmButton: 'hoa-alert-confirm', cancelButton: 'hoa-alert-cancel' },
    confirmButtonText: t('Confirm'), cancelButtonText: t('Cancel'),
    reverseButtons: true, heightAuto: false, returnFocus: true,
    allowOutsideClick: () => !Swal.isLoading(), allowEscapeKey: () => !Swal.isLoading(),
});

export function confirm(options = {}) {
    if (confirmation) return Promise.resolve({ isConfirmed: false });
    confirmation = Swal.fire({ ...defaults(), icon: 'warning', showCancelButton: true, focusCancel: true, ...options,
        title: undefined, titleText: t(options.title), text: t(options.text ?? options.message),
    }).finally(() => { confirmation = null; });
    return confirmation;
}

function previouslyShown(id) {
    try { return id && JSON.parse(sessionStorage.getItem('hoa.feedback.ids') || '[]').includes(id); }
    catch { return false; }
}

function remember(id) {
    if (!id) return;
    try {
        const ids = JSON.parse(sessionStorage.getItem('hoa.feedback.ids') || '[]');
        sessionStorage.setItem('hoa.feedback.ids', JSON.stringify([...ids.filter(value => value !== id), id].slice(-200)));
    } catch { /* Feedback also works with browser storage disabled. */ }
}

const enqueue = createFeedbackQueue(async feedback => {
    if (previouslyShown(feedback.id)) return false;
    if (confirmation) await confirmation;
    // A form-based Filament dialog keeps its focus trap; render feedback inside it.
    const dialog = [...document.querySelectorAll('.fi-modal-window')].find(node => node.getClientRects().length);
    await Swal.fire({ ...defaults(),
        ...(dialog ? { target: dialog, backdrop: true } : {}),
        icon: ({ danger: 'error', warning: 'warning', success: 'success', error: 'error' })[feedback.level] ?? 'info',
        titleText: t(feedback.title ?? ''), text: messageText(feedback.message),
        confirmButtonText: t('OK'),
    });
    remember(feedback.id);
    if (feedback.level === 'error' || feedback.level === 'danger') {
        document.querySelector('[aria-invalid="true"], .fi-fo-field-wrp-error-message')?.closest('.fi-fo-field-wrp')?.querySelector('input, select, textarea')?.focus();
    }
    return true;
});

export function notify(feedback) {
    // A missing backend success title is not permission to invent a successful outcome.
    if (feedback.level === 'success' && !String(feedback.title ?? '').trim()) return Promise.resolve(false);
    return enqueue(feedback);
}
export function handleRequestError(error) { return notify(requestFeedback(error)); }

export function consumeFeedback(root = document) {
    const nodes = [...root.querySelectorAll('[data-hoa-feedback]:not([data-hoa-feedback-consumed])')];
    for (const node of nodes) {
        node.dataset.hoaFeedbackConsumed = 'true';
        try {
            const data = JSON.parse(node.dataset.hoaFeedback);
            const fallback = node.querySelector('[data-hoa-feedback-fallback]');
            fallback?.setAttribute('hidden', '');
            notify(data).then(() => {
                const component = node.closest('[wire\\:id]');
                if (node.dataset.hoaNotificationId && component && window.Livewire) {
                    window.Livewire.find(component.getAttribute('wire:id'))?.removeNotification(node.dataset.hoaNotificationId);
                }
            });
        } catch { node.querySelector('[data-hoa-feedback-fallback]')?.removeAttribute('hidden'); }
    }
}

function initialize() {
    consumeFeedback();
    document.documentElement.dataset.hoaAlertsReady = 'true';
    const observer = new MutationObserver(records => {
        if (records.some(record => [...record.addedNodes].some(node => node.nodeType === 1 && (node.matches('[data-hoa-feedback]') || node.querySelector('[data-hoa-feedback]'))))) consumeFeedback();
    });
    observer.observe(document.body, { childList: true, subtree: true });
}

if (!window.HoaAlerts) {
    window.HoaAlerts = { confirm, notify, handleRequestError, consumeFeedback };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true });
    else initialize();
    document.addEventListener('livewire:navigated', () => consumeFeedback());
}

export { Swal };
