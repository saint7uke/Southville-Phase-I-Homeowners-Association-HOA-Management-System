import { confirm, notify, handleRequestError } from './alerts';
import './sms-enrollment';

let activeAction = null;
const pendingForms = new WeakSet();
const allowedForms = new WeakSet();

// Capture before Filament opens its native confirmation. The original view remains the no-JS fallback.
window.addEventListener('open-modal', async event => {
    const descriptor = [...document.querySelectorAll('[data-hoa-action-confirm]')]
        .map(node => JSON.parse(node.dataset.hoaActionConfirm)).find(item => item.id === event.detail.id);
    if (!descriptor) return;
    event.stopImmediatePropagation();
    if (activeAction) return;
    activeAction = descriptor.id;
    let executed = false;
    try {
        const result = await confirm({ title: descriptor.title, text: descriptor.text, confirmButtonText: descriptor.confirmButtonText, showLoaderOnConfirm: true,
            preConfirm: async () => {
                if (executed) return false;
                executed = true;
                const wire = window.Livewire.find(descriptor.component);
                try { await wire[descriptor.method](); }
                catch (error) { void handleRequestError(error); }
                return true;
            },
        });
        if (!result.isConfirmed && !executed) await window.Livewire.find(descriptor.component)?.unmountAction(descriptor.cancelParents);
    } catch (error) {
        void handleRequestError(error);
    } finally { activeAction = null; }
}, true);

document.addEventListener('submit', async event => {
    const form = event.target;
    if (allowedForms.has(form)) { allowedForms.delete(form); return; }
    const button = event.submitter?.matches('[data-hoa-submit-confirm]') ? event.submitter : form.querySelector('[data-hoa-submit-confirm]');
    if (!button) return;
    if (button.dataset.hoaAccessBaseline) {
        const baseline = JSON.parse(button.dataset.hoaAccessBaseline);
        const component = form.closest('[wire\\:id]');
        const wire = component && window.Livewire.find(component.getAttribute('wire:id'));
        if (wire && wire.$get('data.role') === baseline.role && wire.$get('data.account_status') === baseline.status) return;
    }
    event.preventDefault();
    event.stopImmediatePropagation();
    if (pendingForms.has(form)) return;
    pendingForms.add(form);
    try {
        const result = await confirm(JSON.parse(button.dataset.hoaSubmitConfirm));
        if (result.isConfirmed && form.isConnected) {
            allowedForms.add(form);
            form.requestSubmit(button);
        }
    } finally { pendingForms.delete(form); }
}, true);

function livewireHooks() {
    if (window.__hoaAlertHooks) return;
    window.__hoaAlertHooks = true;
    // This adapter owns request failures; leave Filament's fallback active if our bundle cannot load.
    window.filamentErrorNotifications = null;
    window.Livewire.hook('request', ({ fail, payload }) => {
        // Filament sets this again after page/component renders. Reset at request time,
        // before its failure callback can send a second notification request.
        window.filamentErrorNotifications = null;
        const data = typeof payload === 'string' ? JSON.parse(payload) : payload;
        const notificationOnly = data?.components?.every(item => JSON.parse(item.snapshot).data.isFilamentNotificationsComponent);
        fail(({ status, content, preventDefault }) => {
            preventDefault();
            if (!notificationOnly) void handleRequestError({ status: status === 503 && content === null ? 0 : status });
        });
    });
    window.Livewire.hook('commit', ({ commit, succeed }) => {
        if (!commit.calls?.some(call => !['$refresh', 'removeNotification', 'unmountAction'].includes(call.method))) return;
        succeed(({ snapshot, effects }) => {
            if (effects?.dispatches?.some(event => event.name === 'notificationsSent')) return;
            const state = typeof snapshot === 'string' ? JSON.parse(snapshot) : snapshot;
            const errors = state.memo?.errors;
            if (errors && Object.keys(errors).length) void notify({ level: 'error', title: 'Please review the form', message: [...new Set(Object.values(errors).flat())].join('\n') });
        });
    });
}
if (window.Livewire) livewireHooks();
else document.addEventListener('livewire:init', livewireHooks, { once: true });
