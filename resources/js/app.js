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

        const showing = input.type === 'text';
        input.type = showing ? 'password' : 'text';
        button.setAttribute('aria-pressed', String(! showing));
        button.textContent = showing ? 'Show password' : 'Hide password';
    });
});

document.querySelectorAll('[data-review-registration]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
        if (form.dataset.confirmed === 'true') {
            form.querySelector('[type="submit"]')?.setAttribute('disabled', 'disabled');
            return;
        }

        event.preventDefault();
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
            form.requestSubmit();
        }
    });
});
