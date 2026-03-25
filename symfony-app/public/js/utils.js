function parseApiDate(dateString) {
    if (!dateString) {
        return null;
    }

    if (dateString instanceof Date) {
        return dateString;
    }

    const normalized = String(dateString).trim().replace(' ', 'T');
    const date = new Date(normalized);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return date;
}

function formatDate(dateString, format = 'full') {
    const date = parseApiDate(dateString);

    if (!date) {
        return '';
    }

    if (format === 'time') {
        return date.toLocaleTimeString('fr-FR', {
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    const options = {
        short: { day: 'numeric', month: 'short', year: 'numeric' },
        medium: { day: 'numeric', month: 'long', year: 'numeric' },
        full: {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        },
    };

    return date.toLocaleDateString('fr-FR', options[format] || options.full);
}

function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type}`;
    toast.style.position = 'fixed';
    toast.style.top = '20px';
    toast.style.right = '20px';
    toast.style.minWidth = '300px';
    toast.style.zIndex = '9999';
    toast.style.animation = 'slideIn 0.3s ease-out';
    toast.textContent = message;

    document.body.appendChild(toast);

    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease-in';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

function showConfirmDialog({
    title = 'Confirmation',
    message = 'Voulez-vous continuer ?',
    confirmText = 'Confirmer',
    cancelText = 'Annuler',
    confirmVariant = 'primary',
    icon = 'fa-circle-question',
} = {}) {
    return new Promise((resolve) => {
        const overlay = document.createElement('div');
        overlay.className = 'confirm-overlay';

        const dialog = document.createElement('div');
        dialog.className = 'confirm-dialog';
        dialog.setAttribute('role', 'dialog');
        dialog.setAttribute('aria-modal', 'true');
        dialog.setAttribute('aria-labelledby', 'confirm-dialog-title');

        dialog.innerHTML = `
            <div class="confirm-dialog__header">
                <div class="confirm-dialog__icon">
                    <i class="fas ${icon}"></i>
                </div>
                <div>
                    <h3 id="confirm-dialog-title" class="confirm-dialog__title">${title}</h3>
                    <p class="confirm-dialog__message">${message}</p>
                </div>
            </div>
            <div class="confirm-dialog__actions">
                <button type="button" class="btn btn-secondary confirm-dialog__button" data-action="cancel">
                    ${cancelText}
                </button>
                <button type="button" class="btn btn-${confirmVariant} confirm-dialog__button" data-action="confirm">
                    ${confirmText}
                </button>
            </div>
        `;

        overlay.appendChild(dialog);
        document.body.appendChild(overlay);
        document.body.classList.add('confirm-dialog-open');

        const confirmButton = dialog.querySelector('[data-action="confirm"]');
        const cancelButton = dialog.querySelector('[data-action="cancel"]');

        function cleanup(result) {
            document.removeEventListener('keydown', onKeyDown);
            document.body.classList.remove('confirm-dialog-open');
            overlay.remove();
            resolve(result);
        }

        function onKeyDown(event) {
            if (event.key === 'Escape') {
                cleanup(false);
            }
        }

        overlay.addEventListener('click', (event) => {
            if (event.target === overlay) {
                cleanup(false);
            }
        });

        cancelButton?.addEventListener('click', () => cleanup(false));
        confirmButton?.addEventListener('click', () => cleanup(true));
        document.addEventListener('keydown', onKeyDown);

        confirmButton?.focus();
    });
}

function showLoading(element, text = 'Chargement...') {
    element.replaceChildren();

    const wrapper = document.createElement('div');
    wrapper.style.display = 'flex';
    wrapper.style.alignItems = 'center';
    wrapper.style.justifyContent = 'center';
    wrapper.style.gap = '10px';
    wrapper.style.padding = '20px';

    const spinner = document.createElement('div');
    spinner.className = 'loading';

    const label = document.createElement('span');
    label.textContent = text;

    wrapper.append(spinner, label);
    element.appendChild(wrapper);
}

function showError(element, message) {
    element.replaceChildren();

    const alert = document.createElement('div');
    alert.className = 'alert alert-error';

    const strong = document.createElement('strong');
    strong.textContent = 'Erreur: ';

    const text = document.createTextNode(message);

    alert.append(strong, text);
    element.appendChild(alert);
}

function isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function isValidPhone(phone) {
    const re = /^[0-9+\s\-()]+$/;
    return re.test(phone) && phone.replace(/\D/g, '').length >= 8;
}

function truncate(text, length = 100) {
    if (text.length <= length) return text;
    return text.substring(0, length) + '...';
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function displayFormErrors(form, errors) {
    form.querySelectorAll('.form-error').forEach((el) => el.remove());
    form.querySelectorAll('.error').forEach((el) => el.classList.remove('error'));

    Object.keys(errors).forEach((field) => {
        const input = form.querySelector(`[name="${field}"]`);
        if (input) {
            input.classList.add('error');
            const errorDiv = document.createElement('div');
            errorDiv.className = 'form-error';
            errorDiv.textContent = errors[field];
            input.parentNode.appendChild(errorDiv);
        }
    });
}

function getImageUrl(filename) {
    if (!filename) return '/images/event-placeholder.svg';
    return `/uploads/events/${filename}`;
}

if (!document.getElementById('utils-toast-animations')) {
    const style = document.createElement('style');
    style.id = 'utils-toast-animations';
    style.textContent = `
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
}
