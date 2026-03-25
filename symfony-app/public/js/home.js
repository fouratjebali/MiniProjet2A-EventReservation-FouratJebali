let currentPage = 1;
let currentFilters = {
    upcoming: true,
    available: true,
};

document.addEventListener('DOMContentLoaded', async () => {
    setAuthUI(false);
    handleEmailVerificationFeedback();
    await checkAuth();
    await loadEvents();
    setupFilters();
});

function handleEmailVerificationFeedback() {
    const params = new URLSearchParams(window.location.search);
    const state = params.get('verified');

    if (!state) {
        return;
    }

    const messages = {
        success: ['Email verifie avec succes. Vous pouvez maintenant vous connecter.', 'success'],
        already: ['Votre email est deja verifie. Vous pouvez vous connecter.', 'info'],
        failed: ['Le lien de verification est invalide ou expire.', 'error'],
        invalid: ['Le compte demande est introuvable.', 'error'],
        missing: ['Le lien de verification est incomplet.', 'error'],
    };

    const [message, type] = messages[state] || ['Etat de verification inconnu.', 'warning'];
    showToast(message, type);

    const cleanUrl = new URL(window.location.href);
    cleanUrl.searchParams.delete('verified');
    window.history.replaceState({}, '', cleanUrl);
}

function setAuthUI(isAuthenticated, email = '') {
    document.getElementById('authButtons').style.display = isAuthenticated ? 'none' : 'flex';
    document.getElementById('userMenu').style.display = isAuthenticated ? 'flex' : 'none';
    document.getElementById('myReservationsLink').style.display = isAuthenticated ? 'block' : 'none';
    document.getElementById('userEmail').textContent = isAuthenticated ? email : '';
}

async function checkAuth() {
    if (!api.isAuthenticated()) {
        setAuthUI(false);
        return;
    }

    try {
        const { ok, data } = await api.getCurrentUser();

        if (ok && data?.email) {
            setAuthUI(true, data.email);
            return;
        }
    } catch (error) {
        console.error('Failed to fetch current user', error);
    }

    api.clearTokens();
    setAuthUI(false);
}

function setupFilters() {
    document.getElementById('filterUpcoming').addEventListener('change', (e) => {
        currentFilters.upcoming = e.target.checked;
        currentPage = 1;
        loadEvents();
    });

    document.getElementById('filterAvailable').addEventListener('change', (e) => {
        currentFilters.available = e.target.checked;
        currentPage = 1;
        loadEvents();
    });

    document.getElementById('sortBy').addEventListener('change', () => {
        loadEvents();
    });
}

async function loadEvents() {
    const container = document.getElementById('eventsContainer');
    showLoading(container, 'Chargement des événements...');

    const params = {
        page: currentPage,
        limit: 9,
    };

    if (currentFilters.upcoming) {
        params.upcoming = true;
    }

    if (currentFilters.available) {
        params.available = true;
    }

    try {
        const { ok, data } = await api.getEvents(params);

        if (!ok || !data) {
            showError(container, 'Impossible de charger les événements');
            return;
        }

        displayEvents(data.events || []);
        displayPagination(data.pagination || { pages: 1, page: 1 });
    } catch (error) {
        console.error('Failed to load events', error);
        showError(container, 'Impossible de charger les événements');
    }
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

function displayEvents(events) {
    const container = document.getElementById('eventsContainer');

    if (events.length === 0) {
        container.innerHTML = `
            <div style="text-align: center; padding: 60px 20px;">
                <div style="font-size: 4rem; margin-bottom: 20px;">Evenement</div>
                <h3 style="color: var(--gray-700); margin-bottom: 10px;">Aucun événement trouvé</h3>
                <p class="text-gray">Modifiez vos filtres pour voir plus d'événements</p>
            </div>
        `;
        return;
    }

    const sortBy = document.getElementById('sortBy').value;

    const sortedEvents = [...events].sort((a, b) => {
        switch (sortBy) {
            case 'title':
                return String(a.title).localeCompare(String(b.title), 'fr');
            case 'seats':
                return Number(b.available_seats) - Number(a.available_seats);
            case 'date':
            default: {
                const firstDate = parseApiDate(a.date);
                const secondDate = parseApiDate(b.date);
                return (firstDate?.getTime() || 0) - (secondDate?.getTime() || 0);
            }
        }
    });

    container.innerHTML = `
        <div class="grid grid-cols-3">
            ${sortedEvents.map((event) => createEventCard(event)).join('')}
        </div>
    `;
}

function createEventCard(event) {
    const imageUrl = escapeHtml(getImageUrl(event.image));
    const title = escapeHtml(event.title);
    const description = escapeHtml(truncate(event.description, 120));
    const location = escapeHtml(event.location);
    const eventId = encodeURIComponent(event.id);
    const availabilityBadge = event.is_available
        ? `<span class="badge badge-success">${Number(event.available_seats)} places disponibles</span>`
        : '<span class="badge badge-error">Complet</span>';

    return `
        <div class="card">
            <img src="${imageUrl}" alt="${title}" class="card-image"
                 onerror="this.src='/images/event-placeholder.svg'">
            <div class="card-content">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                    <h3 class="card-title">${title}</h3>
                    ${availabilityBadge}
                </div>

                <p class="card-text">${description}</p>

                <div style="display: flex; flex-direction: column; gap: 8px; margin: 15px 0; padding: 15px; background: var(--gray-50); border-radius: var(--radius-md);">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 1.2rem;">Date</span>
                        <span class="text-gray">${escapeHtml(formatDate(event.date, 'medium'))}</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 1.2rem;">Heure</span>
                        <span class="text-gray">${escapeHtml(formatDate(event.date, 'time'))}</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 1.2rem;">Lieu</span>
                        <span class="text-gray">${location}</span>
                    </div>
                </div>

                <button
                    class="btn btn-primary"
                    style="width: 100%;"
                    onclick="goToEvent('${eventId}')">
                    Voir les détails
                </button>
            </div>
        </div>
    `;
}

function displayPagination(pagination) {
    const container = document.getElementById('paginationContainer');

    if (!pagination || pagination.pages <= 1) {
        container.innerHTML = '';
        return;
    }

    const pages = [];
    for (let i = 1; i <= pagination.pages; i += 1) {
        pages.push(i);
    }

    container.innerHTML = `
        <div style="display: flex; justify-content: center; gap: 10px; align-items: center;">
            <button
                class="btn btn-secondary btn-sm"
                ${pagination.page === 1 ? 'disabled' : ''}
                onclick="changePage(${pagination.page - 1})">
                Precedent
            </button>

            ${pages.map((page) => `
                <button
                    class="btn ${page === pagination.page ? 'btn-primary' : 'btn-secondary'} btn-sm"
                    onclick="changePage(${page})">
                    ${page}
                </button>
            `).join('')}

            <button
                class="btn btn-secondary btn-sm"
                ${pagination.page === pagination.pages ? 'disabled' : ''}
                onclick="changePage(${pagination.page + 1})">
                Suivant
            </button>
        </div>
    `;
}

function changePage(page) {
    currentPage = page;
    loadEvents();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function goToEvent(eventId) {
    window.location.href = `/event.html?id=${eventId}`;
}

function showLoginModal() {
    document.getElementById('loginModal').classList.add('active');
}

function closeLoginModal() {
    document.getElementById('loginModal').classList.remove('active');
    document.getElementById('loginForm').reset();
    document.getElementById('loginError').style.display = 'none';
    document.getElementById('loginError').textContent = '';
}

function showRegisterModal() {
    document.getElementById('registerModal').classList.add('active');
}

function closeRegisterModal() {
    document.getElementById('registerModal').classList.remove('active');
    document.getElementById('registerForm').reset();
    document.getElementById('registerError').style.display = 'none';
    document.getElementById('registerError').textContent = '';
}

window.onclick = function onWindowClick(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('active');
    }
};

async function handleLogin(event) {
    event.preventDefault();

    const form = event.target;
    const formData = new FormData(form);
    const email = formData.get('email');
    const password = formData.get('password');

    const submitBtn = form.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<div class="loading"></div> Connexion...';

    try {
        const { ok, data } = await api.login(email, password);

        if (ok) {
            showToast('Connexion reussie !', 'success');
            closeLoginModal();
            setTimeout(() => location.reload(), 500);
            return;
        }

        const errorDiv = document.getElementById('loginError');
        errorDiv.textContent = data?.error || 'Identifiants invalides';
        errorDiv.style.display = 'block';
    } catch (error) {
        const errorDiv = document.getElementById('loginError');
        errorDiv.textContent = 'Impossible de se connecter pour le moment';
        errorDiv.style.display = 'block';
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Se connecter';
    }
}

async function handleRegister(event) {
    event.preventDefault();

    const form = event.target;
    const formData = new FormData(form);
    const email = formData.get('email');
    const password = formData.get('password');
    const passwordConfirm = formData.get('password_confirm');

    if (password !== passwordConfirm) {
        const errorDiv = document.getElementById('registerError');
        errorDiv.textContent = 'Les mots de passe ne correspondent pas';
        errorDiv.style.display = 'block';
        return;
    }

    const submitBtn = form.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<div class="loading"></div> Inscription...';

    try {
        const { ok, data } = await api.register(email, password);

        if (ok) {
            showToast(data?.message || 'Inscription reussie. Verifiez maintenant votre email.', 'success');
            closeRegisterModal();
            return;
        }

        const errorDiv = document.getElementById('registerError');
        errorDiv.textContent = data?.error || 'Erreur lors de l\'inscription';
        errorDiv.style.display = 'block';
    } catch (error) {
        const errorDiv = document.getElementById('registerError');
        errorDiv.textContent = 'Impossible de s\'inscrire pour le moment';
        errorDiv.style.display = 'block';
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'S\'inscrire';
    }
}

async function handleLogout() {
    const shouldLogout = await showConfirmDialog({
        title: 'Deconnexion',
        message: 'Etes-vous sur de vouloir vous deconnecter ?',
        confirmText: 'Oui, me deconnecter',
        cancelText: 'Rester connecte',
        confirmVariant: 'error',
        icon: 'fa-right-from-bracket',
    });

    if (shouldLogout) {
        await api.logout();
    }
}
