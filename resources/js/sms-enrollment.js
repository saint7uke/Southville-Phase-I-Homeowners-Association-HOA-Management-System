import { notify } from './alerts';
import { translate } from './translations';
import { bindDigits, validLocalMobile, validOtp } from './sms-policy';
import '../css/sms-enrollment.css';

const t = (key, parameters = {}) => translate(key, document.documentElement.lang, parameters);
const bindings = new WeakMap();
const digitBindings = new WeakSet();
const uuid = value => typeof value === 'string' && /^[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}$/i.test(value);

function bind(root) {
    const code = root.querySelector('[data-sms-code]');
    const previous = bindings.get(root);
    if (previous?.code === code) return;
    previous?.controller.abort();
    const controller = new AbortController();
    const { signal } = controller;
    bindings.set(root, { code, controller });
    let pending = false;
    let verified = false;
    let generation = 0;
    let resendAt = 0;
    const proof = root.querySelector('[name="sms_verification_id"]');
    const number = root.dataset.scope === 'registration' ? root.closest('form')?.querySelector('[name="contact_number"]') : null;
    const currentNumber = () => number?.value ?? root.dataset.savedNumber ?? '';
    const result = root.querySelector('[data-sms-result]');
    const requestButton = root.querySelector('[data-sms-request]');
    const confirmButton = root.querySelector('[data-sms-confirm]');
    const saveButton = root.querySelector('[data-sms-save]');
    const refresh = () => {
        const available = root.dataset.available === 'true';
        requestButton.disabled = pending || !available || !validLocalMobile(currentNumber()) || Date.now() < resendAt;
        confirmButton.disabled = pending || !available || verified || !uuid(proof.value) || !validOtp(code.value);
        if (saveButton) saveButton.disabled = pending;
        root.setAttribute('aria-busy', String(pending));
        const destination = root.querySelector('[data-sms-destination]');
        if (destination) destination.textContent = validLocalMobile(currentNumber()) ? currentNumber() : t('Enter your mobile number above.');
    };
    const cooldown = () => {
        if (!root.isConnected || signal.aborted) return;
        const seconds = Math.max(0, Math.ceil((resendAt - Date.now()) / 1000));
        root.querySelector('[data-sms-cooldown]').textContent = seconds ? t('You can request another code in :seconds seconds.', { seconds }) : '';
        refresh();
        if (seconds) setTimeout(cooldown, 1000);
    };
    const error = (message, field = code) => {
        result.textContent = message;
        result.setAttribute('role', 'alert');
        field?.setAttribute('aria-invalid', 'true');
        field?.focus();
    };
    number?.addEventListener('input', () => {
        generation++;
        proof.value = '';
        code.value = '';
        verified = false;
        result.textContent = '';
        refresh();
    }, { signal });
    code.addEventListener('input', () => { code.removeAttribute('aria-invalid'); refresh(); }, { signal });
    root.querySelector('[data-sms-edit]')?.addEventListener('click', event => {
        event.preventDefault();
        number?.scrollIntoView({ block: 'center' });
        number?.focus();
    }, { signal });
    root.addEventListener('click', async event => {
        const button = event.target.closest('[data-sms-request], [data-sms-confirm], [data-sms-save]');
        if (!button || pending || button.disabled) return;
        const action = button === requestButton ? 'request' : button === confirmButton ? 'confirm' : 'save';
        if (action === 'request' && !validLocalMobile(currentNumber())) {
            error(t('Enter an 11-digit Philippine mobile number beginning with 09.'), number);
            return;
        }
        if (action === 'confirm' && (!uuid(proof.value) || !validOtp(code.value))) return;
        pending = true;
        result.textContent = '';
        refresh();
        const version = generation;
        const destination = currentNumber();
        const data = { scope: root.dataset.scope };
        let url;
        if (action === 'request') {
            url = root.dataset.requestUrl;
            data.contact_number = number?.value;
            proof.value = '';
            code.value = '';
            verified = false;
        } else if (action === 'confirm') {
            url = root.dataset.confirmUrl;
            data.verification_id = proof.value;
            data.code = code.value;
        } else {
            url = root.dataset.preferencesUrl;
            data.sms_transactional_opt_in = root.querySelector('[name="sms_transactional_opt_in"]').checked;
            data.sms_announcement_opt_in = root.querySelector('[name="sms_announcement_opt_in"]').checked;
            data.sms_verification_id = proof.value || null;
        }
        const stale = () => signal.aborted || !root.isConnected || version !== generation || currentNumber() !== destination;
        try {
            const response = await fetch(url, { method: 'POST', credentials: 'same-origin', headers: {
                'Content-Type': 'application/json', Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? document.querySelector('input[name="_token"]')?.value ?? '',
            }, body: JSON.stringify(data) });
            const body = await response.json();
            if (stale()) return;
            if (!response.ok || body?.errors) {
                const field = body?.errors?.contact_number ? number : code;
                error(Object.values(body?.errors ?? {}).flat().join(' ') || t('SMS request could not be completed. Check your account before retrying.'), field);
                return;
            }
            const completed = action === 'request' ? response.status === 202 && uuid(body?.verification_id)
                : action === 'confirm' ? body?.verified === true : body?.saved === true;
            if (!completed || typeof body?.message !== 'string' || !body.message.trim()) throw new Error('Invalid SMS response');
            if (action === 'request') {
                proof.value = body.verification_id;
                resendAt = Date.now() + 60_000;
                cooldown();
            }
            if (action === 'confirm') { verified = true; code.value = ''; }
            if (button.hasAttribute('data-sms-save')) proof.value = '';
            code.removeAttribute('aria-invalid');
            number?.removeAttribute('aria-invalid');
            result.textContent = body.message;
            result.setAttribute('role', 'status');
            await notify({ id: crypto.randomUUID(), level: 'success', title: body.message, message: '' });
        } catch {
            // No automatic mutation retry: acceptance may have occurred before connection loss.
            if (!stale()) error(t('SMS request could not be completed. Check your account before retrying.'), action === 'request' ? number : code);
        } finally {
            pending = false;
            if (!signal.aborted) refresh();
        }
    }, { signal });
    refresh();
}

if (!window.hoaSmsEnrollmentInitialized) {
    window.hoaSmsEnrollmentInitialized = true;
    const initialize = () => {
        document.querySelectorAll('[data-local-mobile], [data-sms-code]').forEach(input => {
            if (digitBindings.has(input)) return;
            digitBindings.add(input);
            const mobile = input.hasAttribute('data-local-mobile');
            bindDigits(input, mobile ? 11 : 6, () => t(mobile ? 'Enter an 11-digit Philippine mobile number beginning with 09.' : 'Enter the six-digit verification code using numbers only.'));
        });
        document.querySelectorAll('[data-sms-enrollment]').forEach(bind);
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true });
    else initialize();
    document.addEventListener('livewire:navigated', initialize);
    new MutationObserver(initialize).observe(document.documentElement, { childList: true, subtree: true });
}
