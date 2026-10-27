import { translate, translationKey } from './translations';

const PROFILE_FIELDS = [
    'first_name', 'middle_name', 'last_name', 'suffix', 'sex', 'date_of_birth',
    'contact_number', 'email',
    'residency_date', 'emergency_contact_name', 'emergency_contact_number',
    'profile_photo_upload',
];
const REQUIRED_PROFILE_FIELDS = PROFILE_FIELDS.filter((field) => !['middle_name', 'suffix', 'profile_photo_upload'].includes(field));
const NAME_FIELDS = ['first_name', 'middle_name', 'last_name', 'emergency_contact_name'];
const PHONE_FIELDS = ['contact_number', 'emergency_contact_number'];
const NAME_PATTERN = /^[\p{L}\s'.-]+$/u;
const PHONE_PATTERN = /^09\d{9}$/;
const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
// Livewire can remove browser-created messages during an unrelated morph.
// Remember feedback only (never field values/passwords), and restore it without remounting a form.
const feedback = new WeakMap();

const today = () => new Date().toISOString().slice(0, 10);
const canonicalText = (value) => value.normalize('NFKC').trim().replace(/\s+/gu, ' ');
const canonicalPhone = (value) => value.replace(/[\s().-]+/gu, '').trim();
const canonicalEmail = (value) => value.normalize('NFKC').trim().toLowerCase();

const inputFor = (wrapper) => wrapper?.querySelector('input:not([type="hidden"]), select, textarea') || null;

const ensureStatus = (wrapper, field) => {
    let status = wrapper.querySelector(`[data-realtime-status="${field}"]`);
    if (!status) {
        status = document.createElement('p');
        status.className = 'hoa-realtime-status';
        status.dataset.realtimeStatus = field;
        status.dataset.state = 'neutral';
        status.setAttribute('role', 'status');
        status.setAttribute('aria-live', 'polite');
        status.setAttribute('aria-atomic', 'true');
        status.hidden = true;
        wrapper.append(status);
    }

    return status;
};

const setStatus = (wrapper, field, message = '', state = 'neutral') => {
    const form = wrapper?.closest('form');
    if (form) {
        const remembered = feedback.get(form) || new Map();
        remembered.set(field, { message, state, profileField: wrapper.dataset.profileFieldWrapper, passwordField: wrapper.dataset.passwordFieldWrapper });
        feedback.set(form, remembered);
    }
    const status = ensureStatus(wrapper, field);
    status.dataset.i18n = translationKey(message);
    status.dataset.i18nPrefix = message === '' ? '' : `${state === 'success' ? '✓' : state === 'error' ? '!' : '…'} `;
    status.textContent = status.dataset.i18nPrefix + translate(message, document.documentElement.lang);
    status.dataset.state = state;
    status.hidden = message === '';

    const input = inputFor(wrapper);
    if (input) {
        input.setAttribute('aria-invalid', state === 'error' ? 'true' : 'false');
        const describedBy = new Set((input.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean));
        status.id ||= `realtime-${field}-status`;
        describedBy.add(status.id);
        input.setAttribute('aria-describedby', [...describedBy].join(' '));
    }
};

const showClientSummary = (form, messages, kind) => {
    let summary = form.querySelector(`[data-client-validation-summary="${kind}"]`);
    if (messages.length === 0) {
        summary?.remove();
        return;
    }
    if (!summary) {
        summary = document.createElement('div');
        summary.className = 'hoa-validation-summary';
        summary.dataset.clientValidationSummary = kind;
        summary.setAttribute('role', 'alert');
        summary.setAttribute('tabindex', '-1');
        form.prepend(summary);
    }
    summary.replaceChildren();
    const heading = document.createElement('strong');
    heading.dataset.i18n = kind === 'password' ? 'Please correct the following password fields.' : 'Please correct the following profile fields.';
    heading.textContent = translate(heading.dataset.i18n, document.documentElement.lang);
    const list = document.createElement('ul');
    messages.forEach((message) => {
        const item = document.createElement('li');
        item.dataset.i18n = translationKey(message);
        item.textContent = translate(message, document.documentElement.lang);
        list.append(item);
    });
    summary.append(heading, list);
    summary.focus();
};

const valueFor = (fields, field) => fields[field]?.value?.trim() || '';

const validateProfileField = (fields, field) => {
    const input = fields[field];
    if (!input) {
        return '';
    }

    const value = valueFor(fields, field);
    if (REQUIRED_PROFILE_FIELDS.includes(field) && value === '') {
        return 'This field is required.';
    }
    if (value === '') {
        return '';
    }

    if (NAME_FIELDS.includes(field) && !NAME_PATTERN.test(value)) {
        return 'Use letters, spaces, apostrophes, periods, or hyphens only.';
    }
    if (['first_name', 'middle_name', 'last_name', 'emergency_contact_name'].includes(field) && value.length > 100) {
        return 'Use no more than 100 characters.';
    }
    if (PHONE_FIELDS.includes(field) && !PHONE_PATTERN.test(canonicalPhone(value))) {
        return 'Enter an 11-digit Philippine mobile number beginning with 09.';
    }
    if (field === 'email' && (!EMAIL_PATTERN.test(value) || value.length > 255)) {
        return 'Enter a valid email address of no more than 255 characters.';
    }
    if (field === 'date_of_birth' && value >= today()) {
        return 'The date of birth must be before today.';
    }
    if (field === 'residency_date') {
        if (value > today()) {
            return 'The move-in date cannot be in the future.';
        }
        const birthDate = valueFor(fields, 'date_of_birth');
        if (birthDate && value < birthDate) {
            return 'The move-in date cannot be earlier than the date of birth.';
        }
    }
    if (field === 'profile_photo_upload' && input.files?.length) {
        const file = input.files[0];
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
            return 'Upload a JPEG, PNG, or WebP image.';
        }
        if (file.size > 2 * 1024 * 1024) {
            return 'The profile photo must not be larger than 2 MB.';
        }
        if (input.dataset.dimensionError) {
            return input.dataset.dimensionError;
        }
    }

    return '';
};

const inspectPhotoDimensions = (input) => new Promise((resolve) => {
    delete input.dataset.dimensionError;
    if (!input.files?.length || validateProfileField({ profile_photo_upload: input }, 'profile_photo_upload')) {
        resolve();
        return;
    }

    const image = new Image();
    const objectUrl = URL.createObjectURL(input.files[0]);
    image.onload = () => {
        if (image.naturalWidth < 100 || image.naturalHeight < 100 || image.naturalWidth > 4000 || image.naturalHeight > 4000) {
            input.dataset.dimensionError = 'The profile photo must be between 100 and 4000 pixels on each side.';
        }
        URL.revokeObjectURL(objectUrl);
        resolve();
    };
    image.onerror = () => {
        input.dataset.dimensionError = 'Upload a valid JPEG, PNG, or WebP image.';
        URL.revokeObjectURL(objectUrl);
        resolve();
    };
    image.src = objectUrl;
});

const normalizeOnBlur = (field, input) => {
    if (!input || input.type === 'file' || input.tagName === 'SELECT') {
        return;
    }

    const original = input.value;
    if (NAME_FIELDS.includes(field)) {
        input.value = canonicalText(original);
    } else if (PHONE_FIELDS.includes(field)) {
        input.value = canonicalPhone(original);
    } else if (field === 'email') {
        input.value = canonicalEmail(original);
    }

    if (input.value !== original) {
        input.dispatchEvent(new Event('input', { bubbles: true }));
    }
};

const initializeProfileForm = (form) => {
    if (form.dataset.validationInitialized === 'true') {
        return;
    }
    form.dataset.validationInitialized = 'true';

    const wrappers = Object.fromEntries(PROFILE_FIELDS.map((field) => [
        field,
        form.querySelector(`[data-profile-field-wrapper="${field}"]`),
    ]));
    const fields = Object.fromEntries(Object.entries(wrappers).map(([field, wrapper]) => [field, inputFor(wrapper)]));
    const submit = form.querySelector('[data-profile-submit]');
    const initialEmail = canonicalEmail(valueFor(fields, 'email'));
    const remoteState = { email: 'unchanged' };
    const cache = { email: new Map() };
    const timers = { email: null };
    const controllers = { email: null };
    const sequence = { email: 0 };

    const locallyValid = () => PROFILE_FIELDS.every((field) => validateProfileField(fields, field) === '');
    const remotelyValid = () => ['unchanged', 'valid', 'unknown'].includes(remoteState.email);
    const updateSubmit = () => {
        if (submit) {
            submit.disabled = !locallyValid() || !remotelyValid()
                || remoteState.email === 'pending';
        }
    };

    const showLocalResult = (field, interacted = true) => {
        const wrapper = wrappers[field];
        if (!wrapper) {
            return true;
        }
        const error = validateProfileField(fields, field);
        if (interacted) {
            setStatus(wrapper, field, error || (valueFor(fields, field) ? 'Looks good.' : ''), error ? 'error' : 'success');
        }
        updateSubmit();
        return error === '';
    };

    const requestAvailability = async () => {
        const kind = 'email';
        const signature = canonicalEmail(valueFor(fields, 'email'));
        const unchanged = signature === initialEmail;
        const targetWrapper = wrappers.email;

        if (unchanged) {
            remoteState[kind] = 'unchanged';
            setStatus(targetWrapper, 'email', 'Current email address.', 'success');
            updateSubmit();
            return true;
        }
        if (validateProfileField(fields, 'email') !== '') {
            remoteState[kind] = 'invalid';
            updateSubmit();
            return false;
        }
        if (cache[kind].has(signature)) {
            const result = cache[kind].get(signature);
            remoteState[kind] = result.valid ? 'valid' : 'invalid';
            setStatus(targetWrapper, 'email', result.message, result.valid ? 'success' : 'error');
            updateSubmit();
            return result.valid;
        }

        controllers[kind]?.abort();
        controllers[kind] = new AbortController();
        const requestSequence = ++sequence[kind];
        remoteState[kind] = 'pending';
        setStatus(targetWrapper, 'email', 'Checking email availability.', 'neutral');
        updateSubmit();

        const payload = { field: 'email', email: valueFor(fields, 'email') };

        try {
            const response = await fetch(form.dataset.availabilityUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify(payload),
                signal: controllers[kind].signal,
            });
            const result = await response.json();
            if (requestSequence !== sequence[kind]) {
                return true;
            }
            if (!response.ok && typeof result.valid !== 'boolean') {
                throw new Error('Availability request failed.');
            }

            const outcome = { valid: result.valid === true, message: String(result.message || 'This value could not be validated.') };
            cache[kind].set(signature, outcome);
            remoteState[kind] = outcome.valid ? 'valid' : 'invalid';
            setStatus(targetWrapper, 'email', outcome.message, outcome.valid ? 'success' : 'error');
            updateSubmit();
            return outcome.valid;
        } catch (error) {
            if (error.name === 'AbortError') {
                return true;
            }
            if (requestSequence === sequence[kind]) {
                remoteState[kind] = 'unknown';
                setStatus(targetWrapper, 'email', 'The conflict check is temporarily unavailable. Final validation will run when you save.', 'neutral');
                updateSubmit();
            }
            return true;
        }
    };

    const scheduleRemote = () => {
        const kind = 'email';
        window.clearTimeout(timers[kind]);
        controllers[kind]?.abort();
        sequence[kind] += 1;
        remoteState[kind] = 'pending';
        updateSubmit();
        timers[kind] = window.setTimeout(() => requestAvailability(kind), 500);
    };

    PROFILE_FIELDS.forEach((field) => {
        const input = fields[field];
        if (!input) {
            return;
        }
        let localTimer = null;
        input.addEventListener('input', () => {
            window.clearTimeout(localTimer);
            localTimer = window.setTimeout(() => showLocalResult(field), 250);
            if (field === 'email') {
                scheduleRemote();
            }
        });
        input.addEventListener('change', async () => {
            if (field === 'profile_photo_upload') {
                await inspectPhotoDimensions(input);
            }
            showLocalResult(field);
        });
        input.addEventListener('blur', () => {
            normalizeOnBlur(field, input);
            showLocalResult(field);
            if (field === 'email') {
                window.clearTimeout(timers.email);
                requestAvailability();
            }
        });
    });

    form.addEventListener('submit', (event) => {
        const invalidFields = PROFILE_FIELDS.filter((field) => !showLocalResult(field));
        const invalidField = invalidFields[0];
        const remotePending = remoteState.email === 'pending';
        const remoteInvalid = !remotelyValid();
        if (!invalidField && !remotePending && !remoteInvalid) {
            showClientSummary(form, [], 'profile');
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
        const messages = invalidFields.map((field) => validateProfileField(fields, field));
        if (remoteState.email === 'invalid') {
            messages.push('Resolve the email address conflict before saving.');
        }
        if (remotePending) {
            messages.push('Wait for the availability checks to finish.');
        }
        showClientSummary(form, [...new Set(messages)], 'profile');
        const focusField = invalidField
            || 'email';
        fields[focusField]?.focus();
    }, true);

    updateSubmit();
    const serverSummary = form.querySelector('[data-profile-error-summary]');
    if (serverSummary) {
        serverSummary.focus();
        form.querySelector('[aria-invalid="true"]')?.focus();
    }
};

const initializePasswordForm = (form) => {
    if (form.dataset.validationInitialized === 'true') {
        return;
    }
    form.dataset.validationInitialized = 'true';

    const names = ['current_password', 'password', 'password_confirmation'];
    const wrappers = Object.fromEntries(names.map((field) => [field, form.querySelector(`[data-password-field-wrapper="${field}"]`)]));
    const fields = Object.fromEntries(Object.entries(wrappers).map(([field, wrapper]) => [field, inputFor(wrapper)]));
    const submit = form.querySelector('[data-password-submit]');

    const errorFor = (field) => {
        const value = fields[field]?.value || '';
        if (!value) {
            return 'This field is required.';
        }
        if (value.length > 255) {
            return 'Use no more than 255 characters.';
        }
        if (field === 'password' && (value.length < 12 || !/[A-Z]/.test(value) || !/[a-z]/.test(value) || !/\d/.test(value) || !/[^A-Za-z0-9]/.test(value))) {
            return 'Use at least 12 characters with uppercase, lowercase, number, and symbol.';
        }
        if (field === 'password_confirmation' && value !== fields.password?.value) {
            return 'The password confirmation does not match.';
        }
        return '';
    };
    const update = (field, interacted = true) => {
        const error = errorFor(field);
        if (interacted) {
            const success = field === 'current_password' ? 'Current password entered; it will be verified when submitted.' : 'Looks good.';
            setStatus(wrappers[field], `password-${field}`, error || success, error ? 'error' : 'success');
        }
        if (submit) {
            submit.disabled = names.some((name) => errorFor(name) !== '');
        }
        return error === '';
    };

    names.forEach((field) => {
        fields[field]?.addEventListener('input', () => {
            update(field);
            if (field === 'password' && fields.password_confirmation?.value) {
                update('password_confirmation');
            }
        });
        fields[field]?.addEventListener('blur', () => update(field));
    });
    form.addEventListener('submit', (event) => {
        const invalidFields = names.filter((field) => !update(field));
        const invalid = invalidFields[0];
        if (invalid) {
            event.preventDefault();
            event.stopImmediatePropagation();
            showClientSummary(form, [...new Set(invalidFields.map(errorFor))], 'password');
            fields[invalid]?.focus();
        } else {
            showClientSummary(form, [], 'password');
        }
    }, true);
    update('current_password', false);

    const serverSummary = form.querySelector('[data-password-error-summary]');
    if (serverSummary) {
        serverSummary.focus();
        form.querySelector('[aria-invalid="true"]')?.focus();
    }
};

const initialize = () => {
    document.querySelectorAll('[data-profile-validation-form]').forEach(initializeProfileForm);
    document.querySelectorAll('[data-password-validation-form]').forEach(initializePasswordForm);
    document.querySelectorAll('[data-profile-validation-form], [data-password-validation-form]').forEach(form => {
        feedback.get(form)?.forEach(({ message, state, profileField, passwordField }, field) => {
            const wrapper = form.querySelector(profileField ? `[data-profile-field-wrapper="${profileField}"]` : `[data-password-field-wrapper="${passwordField}"]`);
            if (wrapper && !wrapper.querySelector(`[data-realtime-status="${field}"]`)) setStatus(wrapper, field, message, state);
        });
    });
};

document.addEventListener('DOMContentLoaded', initialize);
document.addEventListener('livewire:navigated', initialize);
window.addEventListener('hoa:locale', initialize);
new MutationObserver(initialize).observe(document.documentElement, { childList: true, subtree: true });
