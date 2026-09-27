import * as bootstrap from 'bootstrap';

window.bootstrap = bootstrap;

const TOAST_ICONS = {
    success: 'bi-check-circle-fill',
    danger: 'bi-exclamation-octagon-fill',
    warning: 'bi-exclamation-triangle-fill',
    info: 'bi-info-circle-fill',
};

/**
 * Show a Bootstrap toast. Text is inserted with textContent to avoid XSS.
 */
export function notify(message, type = 'success') {
    const container = document.getElementById('toast-container');

    if (!container || !message) {
        return;
    }

    const variant = TOAST_ICONS[type] ? type : 'info';
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-bg-${variant} border-0`;
    toast.setAttribute('role', variant === 'danger' ? 'alert' : 'status');
    toast.setAttribute('aria-live', variant === 'danger' ? 'assertive' : 'polite');
    toast.setAttribute('aria-atomic', 'true');

    const wrapper = document.createElement('div');
    wrapper.className = 'd-flex';

    const body = document.createElement('div');
    body.className = 'toast-body';

    const icon = document.createElement('i');
    icon.className = `bi ${TOAST_ICONS[variant]} me-2`;
    icon.setAttribute('aria-hidden', 'true');

    body.append(icon, document.createTextNode(message));

    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'btn-close btn-close-white me-2 m-auto';
    close.setAttribute('data-bs-dismiss', 'toast');
    close.setAttribute('aria-label', 'Close');

    wrapper.append(body, close);
    toast.append(wrapper);
    container.append(toast);

    toast.addEventListener('hidden.bs.toast', () => toast.remove());
    bootstrap.Toast.getOrCreateInstance(toast, { delay: 5000 }).show();
}

window.notify = notify;

document.addEventListener('livewire:init', () => {
    Livewire.on('notify', (event) => {
        const payload = Array.isArray(event) ? event[0] : event;
        notify(payload?.message, payload?.type);
    });
});

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-flash-message]').forEach((element) => {
        notify(element.dataset.flashMessage, element.dataset.flashType);
    });
});
