document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('adminLoginForm');

    if (api.isAuthenticated() && api.isAdmin()) {
        window.location.href = getRedirectTarget();
        return;
    }

    form?.addEventListener('submit', handleAdminLogin);
});

function getRedirectTarget() {
    const params = new URLSearchParams(window.location.search);
    const redirect = params.get('redirect');

    if (redirect && redirect.startsWith('/admin/')) {
        return redirect;
    }

    return '/admin/dashboard.html';
}

async function handleAdminLogin(event) {
    event.preventDefault();

    const form = event.currentTarget;
    const submitButton = document.getElementById('adminLoginSubmit');
    const errorBox = document.getElementById('adminLoginError');
    const formData = new FormData(form);
    const email = String(formData.get('email') || '').trim();
    const password = String(formData.get('password') || '');

    errorBox.style.display = 'none';
    errorBox.textContent = '';

    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Connexion...';

    try {
        const result = await api.loginAdmin(email, password);

        if (!result.ok || !result.data?.token) {
            errorBox.textContent = result.data?.error || 'Connexion admin impossible.';
            errorBox.style.display = 'block';
            return;
        }

        window.location.href = getRedirectTarget();
    } catch (error) {
        console.error('Admin login failed', error);
        errorBox.textContent = 'Erreur reseau lors de la connexion admin.';
        errorBox.style.display = 'block';
    } finally {
        submitButton.disabled = false;
        submitButton.innerHTML = '<i class="fas fa-arrow-right"></i> Se connecter';
    }
}
