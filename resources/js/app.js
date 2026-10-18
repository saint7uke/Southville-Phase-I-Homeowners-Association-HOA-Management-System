import './bootstrap';
import Swal from 'sweetalert2';

document.querySelectorAll('[data-confirm-profile]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
        if (form.dataset.confirmed === 'true') {
            return;
        }

        event.preventDefault();

        const result = await Swal.fire({
            title: 'Save profile changes?',
            text: 'Your contact and property details will be updated.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Save changes',
            cancelButtonText: 'Keep editing',
            reverseButtons: true,
            focusCancel: true,
        });

        if (result.isConfirmed) {
            form.dataset.confirmed = 'true';
            form.requestSubmit();
        }
    });
});

document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const input = document.getElementById(button.dataset.passwordToggle);
        if (! input) {
            return;
        }

        const selectionStart = input.selectionStart;
        const selectionEnd = input.selectionEnd;
        const showing = input.type === 'text';
        input.type = showing ? 'password' : 'text';
        button.setAttribute('aria-pressed', String(!showing));
        button.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
        button.querySelectorAll('.password-eye').forEach((icon) => {
            icon.hidden = icon.classList.contains(showing ? 'password-eye-hide' : 'password-eye-show');
        });
        input.focus({ preventScroll: true });
        if (selectionStart !== null && selectionEnd !== null) {
            input.setSelectionRange(selectionStart, selectionEnd);
        }
    });
});

document.querySelectorAll('[data-review-registration]').forEach((form) => {
    const password = form.querySelector('#password');
    const confirmation = form.querySelector('#password_confirmation');
    const email = form.querySelector('#email');
    const emailMessage = form.querySelector('#email-availability-message');
    const ownershipType = form.querySelector('#ownership_type');
    const addressMessage = form.querySelector('#address-availability-message');
    const landlordName = form.querySelector('#landlord_name');
    const landlordMessage = form.querySelector('#landlord-availability-message');
    const addressFields = ['house_number', 'street', 'block', 'lot']
        .map((id) => form.querySelector(`#${id}`))
        .filter(Boolean);
    const leaseStart = form.querySelector('#lease_start_date');
    const leaseEnd = form.querySelector('#lease_end_date');
    const submit = form.querySelector('[type="submit"]');
    const requirements = {
        length: (value) => value.length >= 12,
        uppercase: (value) => /[A-Z]/.test(value),
        lowercase: (value) => /[a-z]/.test(value),
        number: (value) => /\d/.test(value),
        symbol: (value) => /[^A-Za-z0-9]/.test(value),
    };
    let emailAvailable = null;
    let emailCheckPending = false;
    let emailCheckController = null;
    let emailCheckTimer = null;
    let addressAvailable = null;
    let addressCheckPending = false;
    let addressCheckController = null;
    let addressCheckTimer = null;
    let addressRequestSequence = 0;
    const addressCache = new Map();
    let landlordValid = null;
    let landlordCheckPending = false;
    let landlordCheckController = null;
    let landlordCheckTimer = null;
    let landlordRequestSequence = 0;
    const landlordCache = new Map();
    let registrationSubmissionPending = false;

    const updateEmailMessage = (message, state = 'neutral') => {
        if (!emailMessage || !email) {
            return;
        }

        emailMessage.textContent = message;
        emailMessage.hidden = message === '';
        emailMessage.dataset.state = state;
        email.classList.toggle('is-invalid', state === 'error');
        email.classList.toggle('is-valid', state === 'success');
        email.setAttribute('aria-invalid', state === 'error' ? 'true' : 'false');
    };

    const checkEmailAvailability = async () => {
        if (!email || !emailMessage) {
            return true;
        }

        const value = email.value.trim().toLowerCase();
        emailAvailable = null;
        if (!value || !email.checkValidity()) {
            updateEmailMessage('');
            return true;
        }

        emailCheckController?.abort();
        emailCheckController = new AbortController();
        emailCheckPending = true;
        updateEmailMessage('Checking email availability…');

        try {
            const response = await fetch(`${form.dataset.emailAvailabilityUrl}?email=${encodeURIComponent(value)}`, {
                headers: { Accept: 'application/json' },
                signal: emailCheckController.signal,
            });
            if (!response.ok) {
                emailAvailable = null;
                updateEmailMessage('Email availability could not be checked. You can still submit to validate it.');
                return true;
            }

            const result = await response.json();
            emailAvailable = result.available === true;
            updateEmailMessage(
                emailAvailable ? 'Email address is available.' : 'This email address is already registered or reserved.',
                emailAvailable ? 'success' : 'error',
            );
            return emailAvailable;
        } catch (error) {
            if (error.name !== 'AbortError') {
                emailAvailable = null;
                updateEmailMessage('Email availability could not be checked. You can still submit to validate it.');
            }
            return true;
        } finally {
            emailCheckPending = false;
        }
    };

    email?.addEventListener('input', () => {
        window.clearTimeout(emailCheckTimer);
        emailAvailable = null;
        updateEmailMessage('');
        emailCheckTimer = window.setTimeout(() => checkEmailAvailability(), 350);
    });
    email?.addEventListener('blur', checkEmailAvailability);

    const canonicalAddressPart = (value, prefixes = []) => {
        let part = value
            .normalize('NFKC')
            .trim()
            .toUpperCase()
            .replace(/[^\p{L}\p{N}]+/gu, ' ')
            .replace(/\s+/g, ' ');
        const first = part.split(' ')[0];
        if (prefixes.includes(first)) {
            part = part.slice(first.length).trim();
        }

        return part;
    };

    const addressSignature = () => {
        const values = Object.fromEntries(addressFields.map((field) => [field.id, field.value]));
        const suffixes = { ST: 'STREET', RD: 'ROAD', AVE: 'AVENUE', AV: 'AVENUE', DR: 'DRIVE', LN: 'LANE', CT: 'COURT', BLVD: 'BOULEVARD', HWY: 'HIGHWAY' };
        let street = canonicalAddressPart(values.street || '');
        const words = street.split(' ');
        if (suffixes[words.at(-1)]) {
            words[words.length - 1] = suffixes[words.at(-1)];
            street = words.join(' ');
        }

        return JSON.stringify([
            ownershipType?.value || '',
            canonicalAddressPart(values.house_number || '', ['HOUSE', 'UNIT', 'NUMBER', 'NO']),
            street,
            canonicalAddressPart(values.block || '', ['BLOCK', 'BLK', 'B']),
            canonicalAddressPart(values.lot || '', ['LOT', 'L']),
        ]);
    };

    const updateAddressMessage = (message, state = 'neutral') => {
        if (!addressMessage) {
            return;
        }

        addressMessage.textContent = message;
        addressMessage.hidden = message === '';
        addressMessage.dataset.state = state;
        addressFields.forEach((field) => {
            field.classList.toggle('is-invalid', state === 'error');
            field.classList.toggle('is-valid', state === 'success');
            field.setAttribute('aria-invalid', state === 'error' ? 'true' : 'false');
        });
    };

    const addressIsComplete = () => addressFields.length === 4
        && addressFields.every((field) => field.value.trim() !== '' && field.checkValidity());

    const checkAddressAvailability = async (force = false) => {
        if (!addressMessage || !form.dataset.addressAvailabilityUrl || !addressIsComplete()) {
            addressAvailable = null;
            updateAddressMessage('');
            return true;
        }

        const signature = addressSignature();
        if (!force && addressCache.has(signature)) {
            const cached = addressCache.get(signature);
            addressAvailable = cached.available;
            updateAddressMessage(cached.message, cached.available ? 'success' : 'error');
            return cached.available;
        }

        addressCheckController?.abort();
        addressCheckController = new AbortController();
        const sequence = ++addressRequestSequence;
        addressCheckPending = true;
        addressAvailable = null;
        updateAddressMessage('Checking address availability…');

        const payload = Object.fromEntries(addressFields.map((field) => [field.name, field.value]));
        payload.ownership_type = ownershipType?.value || 'Owner';

        try {
            const response = await fetch(form.dataset.addressAvailabilityUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': form.querySelector('input[name="_token"]')?.value || '',
                },
                body: JSON.stringify(payload),
                signal: addressCheckController.signal,
            });
            if (sequence !== addressRequestSequence) {
                return true;
            }
            if (!response.ok) {
                addressAvailable = null;
                updateAddressMessage('Address availability could not be checked. You can still submit to validate it.');
                return true;
            }

            const result = await response.json();
            if (sequence !== addressRequestSequence) {
                return true;
            }
            addressAvailable = result.available === true;
            const allowedMessage = payload.ownership_type === 'Tenant'
                ? 'This address can be used for a Tenant application.'
                : payload.ownership_type === 'Co-owner'
                    ? 'This address can be used for a Co-owner application.'
                    : 'This address is available.';
            const outcome = {
                available: addressAvailable,
                message: result.message || (addressAvailable
                    ? allowedMessage
                    : 'This address is already registered. Please use a different address.'),
            };
            addressCache.set(signature, outcome);
            updateAddressMessage(outcome.message, outcome.available ? 'success' : 'error');
            return outcome.available;
        } catch (error) {
            if (error.name !== 'AbortError' && sequence === addressRequestSequence) {
                addressAvailable = null;
                updateAddressMessage('Address availability could not be checked. You can still submit to validate it.');
            }
            return true;
        } finally {
            if (sequence === addressRequestSequence) {
                addressCheckPending = false;
            }
        }
    };

    const scheduleAddressCheck = () => {
        window.clearTimeout(addressCheckTimer);
        addressCheckController?.abort();
        addressRequestSequence += 1;
        addressCheckPending = false;
        addressAvailable = null;
        updateAddressMessage('');
        if (addressIsComplete()) {
            addressCheckTimer = window.setTimeout(() => checkAddressAvailability(), 500);
        }
    };

    const updateLandlordMessage = (message, state = 'neutral') => {
        if (!landlordMessage || !landlordName) {
            return;
        }

        landlordMessage.textContent = message;
        landlordMessage.hidden = message === '';
        landlordMessage.dataset.state = state;
        landlordName.classList.toggle('is-invalid', state === 'error');
        landlordName.classList.toggle('is-valid', state === 'success');
        landlordName.setAttribute('aria-invalid', state === 'error' ? 'true' : 'false');
    };

    const landlordCheckIsComplete = () => ownershipType?.value === 'Tenant'
        && addressIsComplete()
        && landlordName?.value.trim() !== ''
        && landlordName.checkValidity();

    const landlordSignature = () => JSON.stringify([
        addressSignature(),
        landlordName?.value.normalize('NFKC').trim().toUpperCase().replace(/[^\p{L}\p{N}]+/gu, ' ').replace(/\s+/g, ' ') || '',
    ]);

    const checkLandlordAvailability = async (force = false) => {
        if (ownershipType?.value !== 'Tenant') {
            landlordValid = true;
            updateLandlordMessage('');
            return true;
        }
        if (!landlordMessage || !form.dataset.landlordAvailabilityUrl || !landlordCheckIsComplete()) {
            landlordValid = null;
            updateLandlordMessage('');
            return false;
        }

        const signature = landlordSignature();
        if (!force && landlordCache.has(signature)) {
            const cached = landlordCache.get(signature);
            landlordValid = cached.valid;
            updateLandlordMessage(cached.message, cached.valid ? 'success' : 'error');
            return cached.valid;
        }

        landlordCheckController?.abort();
        landlordCheckController = new AbortController();
        const sequence = ++landlordRequestSequence;
        landlordCheckPending = true;
        landlordValid = null;
        updateLandlordMessage('Checking registered homeowner…');

        const payload = Object.fromEntries(addressFields.map((field) => [field.name, field.value]));
        payload.landlord_name = landlordName.value;

        try {
            const response = await fetch(form.dataset.landlordAvailabilityUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': form.querySelector('input[name="_token"]')?.value || '',
                },
                body: JSON.stringify(payload),
                signal: landlordCheckController.signal,
            });
            if (sequence !== landlordRequestSequence) {
                return true;
            }
            if (!response.ok) {
                landlordValid = null;
                updateLandlordMessage('Landlord validation could not be completed. You can still submit for server validation.');
                return true;
            }

            const result = await response.json();
            landlordValid = result.valid === true;
            const outcome = {
                valid: landlordValid,
                message: result.message || (landlordValid
                    ? 'Registered homeowner found for this address.'
                    : 'No active homeowner with this name was found at the entered address. Please check the name or contact the HOA.'),
            };
            landlordCache.set(signature, outcome);
            updateLandlordMessage(outcome.message, outcome.valid ? 'success' : 'error');
            return outcome.valid;
        } catch (error) {
            if (error.name !== 'AbortError' && sequence === landlordRequestSequence) {
                landlordValid = null;
                updateLandlordMessage('Landlord validation could not be completed. You can still submit for server validation.');
            }
            return true;
        } finally {
            if (sequence === landlordRequestSequence) {
                landlordCheckPending = false;
            }
        }
    };

    const scheduleLandlordCheck = () => {
        window.clearTimeout(landlordCheckTimer);
        landlordCheckController?.abort();
        landlordRequestSequence += 1;
        landlordCheckPending = false;
        landlordValid = ownershipType?.value === 'Tenant' ? null : true;
        updateLandlordMessage('');
        if (landlordCheckIsComplete()) {
            landlordCheckTimer = window.setTimeout(() => checkLandlordAvailability(), 500);
        }
    };

    addressFields.forEach((field) => {
        field.addEventListener('input', () => {
            scheduleAddressCheck();
            scheduleLandlordCheck();
        });
        field.addEventListener('blur', () => {
            window.clearTimeout(addressCheckTimer);
            checkAddressAvailability();
            window.clearTimeout(landlordCheckTimer);
            checkLandlordAvailability();
        });
    });
    landlordName?.addEventListener('input', scheduleLandlordCheck);
    landlordName?.addEventListener('blur', () => {
        window.clearTimeout(landlordCheckTimer);
        checkLandlordAvailability();
    });
    ownershipType?.addEventListener('change', () => {
        scheduleAddressCheck();
        scheduleLandlordCheck();
    });
    form.addEventListener('ownership-type-changed', () => {
        scheduleAddressCheck();
        scheduleLandlordCheck();
    });

    const updatePasswordState = () => {
        if (!password || !confirmation) {
            return true;
        }

        const value = password.value;
        const passwordValid = Object.values(requirements).every((check) => check(value));
        const confirmationValid = confirmation.value.length > 0 && confirmation.value === value;

        Object.entries(requirements).forEach(([name, check]) => {
            const item = form.querySelector(`[data-password-requirement="${name}"]`);
            if (!item) {
                return;
            }

            const valid = check(value);
            item.classList.toggle('is-valid', valid);
            item.classList.toggle('is-invalid', value.length > 0 && !valid);
        });

        password.classList.toggle('is-valid', passwordValid);
        password.classList.toggle('is-invalid', value.length > 0 && !passwordValid);
        password.setAttribute('aria-invalid', passwordValid || value.length === 0 ? 'false' : 'true');

        const confirmationStatus = form.querySelector('#password-confirmation-status');
        if (confirmationStatus) {
            confirmationStatus.textContent = confirmation.value.length === 0
                ? ''
                : confirmationValid ? 'Passwords match.' : 'Passwords do not match.';
            confirmationStatus.classList.toggle('hoa-error-text', confirmation.value.length > 0 && !confirmationValid);
        }

        confirmation.classList.toggle('is-valid', confirmationValid);
        confirmation.classList.toggle('is-invalid', confirmation.value.length > 0 && !confirmationValid);
        confirmation.setAttribute('aria-invalid', confirmationValid || confirmation.value.length === 0 ? 'false' : 'true');

        return passwordValid && confirmationValid;
    };

    password?.addEventListener('input', updatePasswordState);
    confirmation?.addEventListener('input', updatePasswordState);

    const updateLeaseDateState = () => {
        if (!leaseStart || !leaseEnd) {
            return true;
        }

        const tenant = ownershipType?.value === 'Tenant';
        leaseEnd.min = leaseStart.value || '';
        const invalid = tenant
            && leaseStart.value !== ''
            && leaseEnd.value !== ''
            && leaseEnd.value <= leaseStart.value;
        const messageId = 'lease-end-date-error';
        let message = form.querySelector(`#${messageId}`);

        if (invalid && !message) {
            message = document.createElement('p');
            message.id = messageId;
            message.className = 'hoa-error-text';
            message.setAttribute('role', 'alert');
            leaseEnd.insertAdjacentElement('afterend', message);
        }

        if (message) {
            message.textContent = invalid
                ? 'The lease end date must be after the lease start date.'
                : '';
            message.hidden = !invalid;
        }

        leaseEnd.classList.toggle('is-invalid', invalid);
        leaseEnd.setAttribute('aria-invalid', invalid ? 'true' : 'false');

        return !invalid;
    };

    const setRealtimeFieldMessage = (field, message) => {
        if (!field) {
            return;
        }

        const messageId = `${field.id}-realtime-error`;
        let messageElement = form.querySelector(`#${messageId}`);
        if (!messageElement) {
            messageElement = document.createElement('p');
            messageElement.id = messageId;
            messageElement.className = 'hoa-error-text';
            messageElement.setAttribute('role', 'alert');
            field.insertAdjacentElement('afterend', messageElement);
        }

        messageElement.textContent = message;
        messageElement.hidden = message === '';
        field.classList.toggle('is-invalid', message !== '');
        field.setAttribute('aria-invalid', message !== '' ? 'true' : 'false');
    };

    const updateRealtimeFieldState = () => {
        const tenant = ownershipType?.value === 'Tenant';
        let valid = true;
        const contactFields = [form.querySelector('#contact_number'), form.querySelector('#landlord_contact')];
        contactFields.forEach((field) => {
            if (!field || (field.id === 'landlord_contact' && !tenant)) {
                return;
            }

            const invalid = field.value !== '' && !/^09\d{9}$/.test(field.value);
            setRealtimeFieldMessage(field, invalid ? 'Enter an 11-digit mobile number beginning with 09.' : '');
            valid = valid && !invalid;
        });

        const dateOfBirth = form.querySelector('#date_of_birth');
        if (dateOfBirth) {
            const invalid = dateOfBirth.value !== '' && dateOfBirth.value > dateOfBirth.max;
            setRealtimeFieldMessage(dateOfBirth, invalid ? 'Date of birth must be before today.' : '');
            valid = valid && !invalid;
        }

        const requiredTenantFields = [
            [form.querySelector('#landlord_name'), 'Enter the landlord name.'],
            [form.querySelector('#lease_start_date'), 'Enter the lease start date.'],
            [form.querySelector('#lease_end_date'), 'Enter the lease end date.'],
        ];
        requiredTenantFields.forEach(([field, message]) => {
            if (!field || !tenant) {
                return;
            }

            const invalid = field.value.trim() === '';
            setRealtimeFieldMessage(field, invalid ? message : '');
            valid = valid && !invalid;
        });

        const document = form.querySelector('#verification_document');
        if (document?.files?.length) {
            const file = document.files[0];
            const allowed = ['application/pdf', 'image/jpeg', 'image/png'];
            const invalid = file.size > 10 * 1024 * 1024 || !allowed.includes(file.type);
            setRealtimeFieldMessage(document, invalid ? 'Upload a PDF, JPG, JPEG, or PNG file up to 10 MB.' : '');
            valid = valid && !invalid;
        }

        return valid;
    };

    leaseStart?.addEventListener('input', updateLeaseDateState);
    leaseStart?.addEventListener('change', updateLeaseDateState);
    leaseEnd?.addEventListener('input', updateLeaseDateState);
    leaseEnd?.addEventListener('change', updateLeaseDateState);
    ownershipType?.addEventListener('change', updateLeaseDateState);
    form.addEventListener('ownership-type-changed', updateLeaseDateState);
    form.addEventListener('ownership-type-changed', updateRealtimeFieldState);
    form.querySelectorAll('input, select').forEach((field) => {
        field.addEventListener('input', updateRealtimeFieldState);
        field.addEventListener('change', updateRealtimeFieldState);
    });

    form.addEventListener('submit', async (event) => {
        if (form.dataset.confirmed === 'true') {
            submit?.setAttribute('disabled', 'disabled');
            if (submit) {
                submit.textContent = 'Submitting application…';
            }
            return;
        }

        event.preventDefault();

        if (registrationSubmissionPending) {
            return;
        }

        registrationSubmissionPending = true;
        window.clearTimeout(addressCheckTimer);
        window.clearTimeout(landlordCheckTimer);
        const [emailIsAvailable, propertyAddressIsAvailable, landlordIsValid] = await Promise.all([
            emailAvailable === true ? true : checkEmailAvailability(),
            checkAddressAvailability(true),
            checkLandlordAvailability(true),
        ]);
        if (!updatePasswordState() || !updateLeaseDateState() || !updateRealtimeFieldState() || !emailIsAvailable || !propertyAddressIsAvailable || !landlordIsValid || emailCheckPending || addressCheckPending || landlordCheckPending) {
            registrationSubmissionPending = false;
            const passwordMeetsRequirements = password
                && Object.values(requirements).every((check) => check(password.value));
            if (!updateLeaseDateState()) {
                leaseEnd?.focus();
            } else if (!landlordIsValid) {
                landlordName?.focus();
            } else if (!propertyAddressIsAvailable) {
                addressFields[0]?.focus();
            } else if (!emailIsAvailable) {
                email?.focus();
            } else {
                (passwordMeetsRequirements ? confirmation : password)?.focus();
            }
            return;
        }

        const data = new FormData(form);
        const result = await Swal.fire({
            title: 'Review your application',
            text: `${data.get('first_name')} ${data.get('last_name')} — Block ${data.get('block')}, Lot ${data.get('lot')}. Submit this information for HOA review?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Submit application',
            cancelButtonText: 'Keep editing',
            reverseButtons: true,
            focusCancel: true,
        });

        if (result.isConfirmed) {
            form.dataset.confirmed = 'true';
            submit?.setAttribute('disabled', 'disabled');
            if (submit) {
                submit.textContent = 'Submitting application…';
            }
            form.requestSubmit();
        } else {
            registrationSubmissionPending = false;
        }
    });
});

document.querySelectorAll('[data-auth-submit]').forEach((form) => {
    form.addEventListener('submit', () => {
        const button = form.querySelector('[type="submit"]');
        if (!button) {
            return;
        }

        button.disabled = true;
        button.dataset.originalLabel = button.textContent;
        button.textContent = 'Signing in…';
    });
});
