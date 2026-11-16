// Legacy display/provider identity only. Never use this to validate new user input.
export function localMobile(value) {
    const compact = String(value ?? '').replace(/[\s().-]+/gu, '').trim();
    if (/^09\d{9}$/.test(compact)) return compact;
    if (/^\+?639\d{9}$/.test(compact)) return `0${compact.replace(/^\+?63/, '')}`;
    return compact;
}

export const validLocalMobile = value => typeof value === 'string' && value.length === 11 && /^09[0-9]{9}$/.test(value);
export const validOtp = value => typeof value === 'string' && value.length === 6 && /^[0-9]{6}$/.test(value);
export const editableDigits = (value, length) => typeof value === 'string' && value.length <= length && !/[^0-9]/.test(value);

// Reject the entire invalid edit rather than silently converting it into another number.
export function bindDigits(input, length, message) {
    let previous = editableDigits(input.value, length) ? input.value : '';
    const reject = event => {
        event.preventDefault();
        input.setCustomValidity(message());
        input.reportValidity();
        // The rejected edit never entered the field. Do not leave a stale form
        // error, especially on an optional OTP control inside registration.
        input.setCustomValidity('');
    };
    input.addEventListener('beforeinput', event => {
        if (event.data == null || event.inputType?.startsWith('delete')) return;
        const next = input.value.slice(0, input.selectionStart ?? input.value.length) + event.data + input.value.slice(input.selectionEnd ?? input.value.length);
        if (!editableDigits(next, length)) reject(event);
    });
    input.addEventListener('paste', event => {
        const value = event.clipboardData?.getData('text') ?? '';
        const next = input.value.slice(0, input.selectionStart ?? 0) + value + input.value.slice(input.selectionEnd ?? input.value.length);
        if (!editableDigits(next, length)) reject(event);
    });
    input.addEventListener('input', () => {
        const valid = editableDigits(input.value, length);
        if (!valid) input.value = previous;
        else previous = input.value;
        if (!valid) {
            input.setCustomValidity(message());
            input.reportValidity();
        }
        input.setCustomValidity('');
    });
}
