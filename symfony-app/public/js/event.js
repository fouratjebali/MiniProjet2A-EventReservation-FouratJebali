let currentEvent = null;

document.addEventListener('DOMContentLoaded', async () => {
    setAuthUI(false);
    await checkAuth();
    await loadEvent();
});

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
            prefillReservationForm(data.email);
            return;
        }
    } catch (error) {
        console.error('Failed to load current user', error);
    }

    api.clearTokens();
    setAuthUI(false);
}

function getEventIdFromUrl() {
    const params = new URLSearchParams(window.location.search);
    return params.get('id');
}

async function loadEvent() {
    const eventId = getEventIdFromUrl();
    const container = document.getElementById('eventContainer');

    if (!eventId) {
        showError(container, 'Identifiant d\'evenement manquant');
        return;
    }

    showLoading(container, 'Chargement de l\'evenement...');

    try {
        const { ok, data } = await api.getEvent(eventId);

        if (!ok || !data) {
            showError(container, data?.error || 'Impossible de charger l\'evenement');
            return;
        }

        currentEvent = data;
        renderEvent(data);
        renderReservationSummary(data);
    } catch (error) {
        console.error('Failed to load event', error);
        showError(container, 'Impossible de charger l\'evenement');
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

function renderEvent(event) {
    const container = document.getElementById('eventContainer');
    const title = escapeHtml(event.title);
    const description = escapeHtml(event.description).replaceAll('\n', '<br>');
    const location = escapeHtml(event.location);
    const imageUrl = escapeHtml(getImageUrl(event.image));
    const dateLabel = escapeHtml(formatDate(event.date, 'full'));
    const availableSeats = Number(event.available_seats || 0);
    const isAvailable = Boolean(event.is_available);

    const reserveButton = isAvailable
        ? `<button type="button" class="btn btn-success btn-lg" onclick="openReservationModal()">Reserver maintenant</button>`
        : '<button type="button" class="btn btn-secondary btn-lg" disabled>Reservation indisponible</button>';

    container.innerHTML = `
        <div class="grid" style="grid-template-columns: minmax(0, 2fr) minmax(280px, 1fr); gap: 30px;">
            <article class="card">
                <img src="${imageUrl}" alt="${title}" class="card-image" style="max-height: 420px; object-fit: cover;" onerror="this.src='/images/event-placeholder.jpg'">
                <div class="card-content">
                    <div style="display: flex; justify-content: space-between; gap: 12px; align-items: start; margin-bottom: 16px; flex-wrap: wrap;">
                        <h1 class="card-title" style="font-size: 2rem; margin: 0;">${title}</h1>
                        <span class="badge ${isAvailable ? 'badge-success' : 'badge-error'}">
                            ${isAvailable ? `${availableSeats} places disponibles` : 'Complet'}
                        </span>
                    </div>

                    <div style="display: grid; gap: 10px; margin-bottom: 20px; color: var(--gray-700);">
                        <div><strong>Date :</strong> ${dateLabel}</div>
                        <div><strong>Lieu :</strong> ${location}</div>
                        <div><strong>Places totales :</strong> ${Number(event.seats || 0)}</div>
                    </div>

                    <div>
                        <h2 style="margin-bottom: 12px;">Description</h2>
                        <p class="card-text" style="white-space: normal;">${description}</p>
                    </div>
                </div>
            </article>

            <aside class="card">
                <div class="card-content" style="display: grid; gap: 16px;">
                    <h2 class="card-title" style="margin: 0;">Reservation</h2>
                    <p class="text-gray">
                        ${isAvailable
                            ? 'Cet evenement est ouvert a la reservation. Connectez-vous pour reserver votre place.'
                            : 'Cet evenement n\'est actuellement plus reservable.'}
                    </p>
                    ${reserveButton}
                    <a href="/" class="btn btn-outline">Retour a la liste</a>
                </div>
            </aside>
        </div>
    `;
}

function renderReservationSummary(event) {
    const summary = document.getElementById('eventSummary');
    summary.innerHTML = `
        <strong>${escapeHtml(event.title)}</strong><br>
        <span class="text-gray">${escapeHtml(formatDate(event.date, 'medium'))} a ${escapeHtml(formatDate(event.date, 'time'))}</span><br>
        <span class="text-gray">${escapeHtml(event.location)}</span>
    `;
}

function prefillReservationForm(email = '') {
    const form = document.getElementById('reservationForm');

    if (!form) {
        return;
    }

    const emailInput = form.querySelector('[name="email"]');
    if (email && emailInput) {
        emailInput.value = email;
    }
}

function openReservationModal() {
    if (!currentEvent) {
        return;
    }

    if (!api.isAuthenticated()) {
        showToast('Connectez-vous d\'abord pour reserver', 'info');
        window.location.href = '/';
        return;
    }

    document.getElementById('reservationError').style.display = 'none';
    document.getElementById('reservationError').textContent = '';
    document.getElementById('reservationSuccess').style.display = 'none';
    document.getElementById('reservationSuccess').textContent = '';
    document.getElementById('reservationModal').classList.add('active');
}

function closeReservationModal() {
    document.getElementById('reservationModal').classList.remove('active');
}

window.onclick = function onWindowClick(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('active');
    }
};

async function handleReservation(event) {
    event.preventDefault();

    if (!currentEvent) {
        return;
    }

    const form = event.target;
    const formData = new FormData(form);
    const payload = {
        event_id: currentEvent.id,
        name: formData.get('name'),
        email: formData.get('email'),
        phone: formData.get('phone'),
    };

    const errorDiv = document.getElementById('reservationError');
    const successDiv = document.getElementById('reservationSuccess');
    errorDiv.style.display = 'none';
    errorDiv.textContent = '';
    successDiv.style.display = 'none';
    successDiv.textContent = '';

    if (!isValidEmail(payload.email)) {
        errorDiv.textContent = 'Veuillez saisir un email valide';
        errorDiv.style.display = 'block';
        return;
    }

    if (!isValidPhone(payload.phone)) {
        errorDiv.textContent = 'Veuillez saisir un numero de telephone valide';
        errorDiv.style.display = 'block';
        return;
    }

    const submitBtn = form.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<div class="loading"></div> Reservation...';

    try {
        const { ok, data } = await api.createReservation(payload);

        if (ok) {
            successDiv.textContent = data?.message || 'Reservation creee avec succes';
            successDiv.style.display = 'block';
            showToast('Reservation creee avec succes', 'success');
            form.reset();
            prefillReservationForm(api.user?.email || '');
            setTimeout(() => {
                closeReservationModal();
                loadEvent();
            }, 1000);
            return;
        }

        errorDiv.textContent = data?.error || 'Impossible de creer la reservation';
        errorDiv.style.display = 'block';
    } catch (error) {
        console.error('Failed to create reservation', error);
        errorDiv.textContent = 'Impossible de creer la reservation pour le moment';
        errorDiv.style.display = 'block';
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Confirmer la reservation';
    }
}

async function handleLogout() {
    if (confirm('Etes-vous sur de vouloir vous deconnecter ?')) {
        await api.logout();
    }
}
