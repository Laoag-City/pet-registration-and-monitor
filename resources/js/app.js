import './bootstrap';

document.addEventListener('DOMContentLoaded', () => {
    // Prevent a second browser submission while the first navigation is pending.
    // Do not disable named submit buttons: their values may select Review/Edit actions.
    document.querySelectorAll('form[data-prevent-repeat]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (event.defaultPrevented) return;
            if (form.dataset.submitting === 'true') {
                event.preventDefault();
                return;
            }
            form.dataset.submitting = 'true';
            form.setAttribute('aria-busy', 'true');
        });
    });
    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        const input = document.querySelector(button.dataset.passwordToggle);
        const icon = button.querySelector('i');

        if (!input) {
            return;
        }

        button.addEventListener('click', () => {
            const revealed = input.type === 'text';
            input.type = revealed ? 'password' : 'text';

            if (icon) {
                icon.classList.toggle('bi-eye', revealed);
                icon.classList.toggle('bi-eye-slash', !revealed);
            }

            button.setAttribute('aria-label', revealed ? 'Show password' : 'Hide password');
            input.focus({ preventScroll: true });
        });
    });
});

window.addEventListener('pageshow', () => {
    document.querySelectorAll('form[data-prevent-repeat]').forEach((form) => {
        delete form.dataset.submitting;
        form.removeAttribute('aria-busy');
    });
});
