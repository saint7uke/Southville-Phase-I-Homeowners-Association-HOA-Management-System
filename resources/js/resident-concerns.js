function setup() {
    document.querySelectorAll('[data-resident-concern]').forEach(form => {
        const kind = form.querySelector('[data-concern-kind]');
        const category = form.querySelector('[data-concern-category]');
        const sync = () => {
            const complaint = kind.value === 'Complaint';
            for (const [selector, visible] of [['[data-concern-complaint]', complaint], ['[data-concern-inquiry]', !complaint], ['[data-concern-other]', complaint && category.value === 'Others']]) {
                const element = form.querySelector(selector);
                element.hidden = !visible;
                element.querySelectorAll('input,select').forEach(input => { input.disabled = !visible; input.required = visible && input.type !== 'file'; });
            }
            const description = form.querySelector('[data-concern-description]');
            description.name = complaint ? 'description' : 'message';
            description.minLength = complaint ? 20 : 1;
        };
        kind.addEventListener('change', sync);
        category.addEventListener('change', () => { form.querySelector('[name=category_other]').value = ''; sync(); });
        sync();
    });
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', setup); else setup();
