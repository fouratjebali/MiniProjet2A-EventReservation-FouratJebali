let adminReservations = [];
let currentEvent = null;
let reservationSearchHandler = null;

document.addEventListener('DOMContentLoaded', async () => {
    setupReservationsPage();

    const authenticated = await checkAdminAuth();
    if (!authenticated) {
        return;
    }

    await loadReservationsPage();
});

function setupReservationsPage() {
    const filterStatus = document.getElementById('filterStatus');
    const searchInput = document.getElementById('searchInput');

    filterStatus?.addEventListener('change', displayReservations);

    reservationSearchHandler = debounce(() => {
        displayReservations();
    }, 250);

    searchInput?.addEventListener('input', reservationSearchHandler);
}

async function checkAdminAuth() {
    if (!api.isAuthenticated()) {
        redirectToAdminLogin();
        return false;
    }

    if (!api.isAdmin()) {
        redirectToAdminLogin();
        return false;
    }

    const email = api.user?.email || 'Admin connecte';
    const adminEmail = document.getElementById('adminEmail');
    if (adminEmail) {
        adminEmail.innerHTML = `
            <i class="fas fa-user-shield"></i>
            <span>${escapeHtml(email)}</span>
        `;
    }

    return true;
}

function redirectToAdminLogin() {
    const loginUrl = new URL('/admin/', window.location.origin);
    loginUrl.searchParams.set('redirect', `${window.location.pathname}${window.location.search}`);
    window.location.href = loginUrl.toString();
}

function renderAdminAccessState(title, message) {
    const eventInfo = document.getElementById('eventInfo');
    const statsCards = document.getElementById('statsCards');
    const reservationsSection = document.getElementById('reservationsSection');
    const reservationsContainer = document.getElementById('reservationsContainer');

    const block = `
        <div class="admin-access-state card">
            <div class="card-content">
                <span class="badge badge-warning">Acces admin requis</span>
                <h2 class="card-title" style="margin-top: 14px;">${escapeHtml(title)}</h2>
                <p class="text-gray">${escapeHtml(message)}</p>
            </div>
        </div>
    `;

    if (eventInfo) {
        eventInfo.innerHTML = block;
    }

    if (statsCards) {
        statsCards.style.display = 'grid';
        statsCards.innerHTML = `<div style="grid-column: 1 / -1;">${block}</div>`;
    }

    if (reservationsSection) {
        reservationsSection.style.display = 'block';
    }

    if (reservationsContainer) {
        reservationsContainer.innerHTML = `
            <div class="admin-empty-state">
                <p class="text-gray">Ajoutez un token admin valide puis rechargez la page.</p>
            </div>
        `;
    }
}

async function loadReservationsPage() {
    const eventId = getEventIdFromUrl();
    const eventInfo = document.getElementById('eventInfo');
    const statsCards = document.getElementById('statsCards');
    const reservationsContainer = document.getElementById('reservationsContainer');

    if (!eventId) {
        renderMissingEventState();
        return;
    }

    showLoading(eventInfo, 'Chargement de l evenement...');
    showLoading(statsCards, 'Chargement des statistiques...');
    showLoading(reservationsContainer, 'Chargement des reservations...');

    try {
        const [eventResult, reservationResult] = await Promise.all([
            api.getEvent(eventId),
            api.getEventReservations(eventId),
        ]);

        if (!reservationResult.ok || !reservationResult.data) {
            renderReservationLoadError(formatReservationLoadError(reservationResult));
            return;
        }

        const eventPayload = eventResult.ok && eventResult.data
            ? eventResult.data
            : reservationResult.data.event;

        currentEvent = normalizeEvent(eventPayload, reservationResult.data);
        adminReservations = Array.isArray(reservationResult.data.reservations)
            ? reservationResult.data.reservations
            : [];

        renderEventInfo(reservationResult.data.stats || {});
        renderStatsCards(reservationResult.data.stats || {});
        displayReservations();
        syncPublicEventLink();
    } catch (error) {
        console.error('Failed to load admin reservations page', error);
        renderReservationLoadError('Impossible de charger les reservations de cet evenement.');
    }
}

function formatReservationLoadError(result) {
    const backendMessage = result?.data?.error || result?.data?.message || '';

    if (result?.status === 401) {
        return 'Le token admin est absent, invalide ou expire. Regenez un JWT admin puis remplacez la valeur jwt_token dans le navigateur.';
    }

    if (result?.status === 403) {
        return 'Le token courant ne porte pas vraiment ROLE_ADMIN cote backend. Verifiez que jwt_token est bien un token admin et que auth_user contient ROLE_ADMIN.';
    }

    if (backendMessage) {
        return backendMessage;
    }

    return 'Impossible de charger les reservations de cet evenement.';
}

function getEventIdFromUrl() {
    const params = new URLSearchParams(window.location.search);

    return params.get('eventId') || params.get('id') || '';
}

function normalizeEvent(eventPayload, reservationPayload) {
    const fallbackEvent = reservationPayload?.event || {};

    return {
        id: eventPayload?.id || fallbackEvent.id || '',
        title: eventPayload?.title || fallbackEvent.title || 'Evenement',
        description: eventPayload?.description || '',
        date: eventPayload?.date || fallbackEvent.date || '',
        location: eventPayload?.location || fallbackEvent.location || '',
        seats: Number(eventPayload?.seats || 0),
        image: eventPayload?.image || null,
        available_seats: Number(eventPayload?.available_seats || 0),
        reservations_count: Number(eventPayload?.reservations_count || reservationPayload?.stats?.total || 0),
        is_available: Boolean(eventPayload?.is_available),
    };
}

function syncPublicEventLink() {
    const publicEventLink = document.getElementById('publicEventLink');

    if (!publicEventLink || !currentEvent?.id) {
        return;
    }

    publicEventLink.href = `/event.html?id=${encodeURIComponent(currentEvent.id)}`;
}

function renderMissingEventState() {
    const eventInfo = document.getElementById('eventInfo');
    const statsCards = document.getElementById('statsCards');
    const reservationsSection = document.getElementById('reservationsSection');

    eventInfo.innerHTML = `
        <div class="card-content">
            <div class="admin-empty-state">
                <p class="text-gray">Aucun identifiant d evenement n a ete fourni. Ouvrez cette page depuis la gestion des evenements ou le dashboard admin.</p>
                <a href="/admin/event.html" class="btn btn-primary btn-sm">
                    <i class="fas fa-calendar-days"></i>
                    Aller a la gestion des evenements
                </a>
            </div>
        </div>
    `;

    if (statsCards) {
        statsCards.style.display = 'none';
        statsCards.innerHTML = '';
    }

    if (reservationsSection) {
        reservationsSection.style.display = 'none';
    }
}

function renderReservationLoadError(message) {
    const eventInfo = document.getElementById('eventInfo');
    const statsCards = document.getElementById('statsCards');
    const reservationsSection = document.getElementById('reservationsSection');
    const reservationsContainer = document.getElementById('reservationsContainer');

    if (eventInfo) {
        eventInfo.innerHTML = `
            <div class="card-content">
                <div class="admin-empty-state">
                    <p class="text-gray">${escapeHtml(message)}</p>
                    <a href="/admin/event.html" class="btn btn-primary btn-sm">
                        <i class="fas fa-calendar-days"></i>
                        Retour a la gestion des evenements
                    </a>
                </div>
            </div>
        `;
    }

    if (statsCards) {
        statsCards.style.display = 'none';
        statsCards.innerHTML = '';
    }

    if (reservationsSection) {
        reservationsSection.style.display = 'none';
    }

    if (reservationsContainer) {
        reservationsContainer.innerHTML = '';
    }
}

function renderEventInfo(stats) {
    const eventInfo = document.getElementById('eventInfo');
    const occupancy = currentEvent.seats > 0
        ? Math.min(100, Math.round((Number(stats.total || 0) / Math.max(1, currentEvent.seats)) * 100))
        : 0;

    const image = currentEvent.image
        ? `<img class="admin-event-hero-image" src="${escapeHtml(getImageUrl(currentEvent.image))}" alt="${escapeHtml(currentEvent.title)}">`
        : `
            <div class="admin-event-hero-placeholder">
                <i class="fas fa-image"></i>
            </div>
        `;

    eventInfo.innerHTML = `
        <div class="card-content">
            <div class="admin-event-hero">
                <div class="admin-event-visual">
                    ${image}
                </div>

                <div class="admin-event-copy">
                    <div class="admin-event-copy-header">
                        <div>
                            <span class="badge ${currentEvent.is_available ? 'badge-success' : 'badge-warning'}">
                                ${currentEvent.is_available ? 'Reservations ouvertes' : 'Evenement clos'}
                            </span>
                            <h2 class="card-title admin-section-title">${escapeHtml(currentEvent.title)}</h2>
                        </div>
                        <a href="/admin/event.html?edit=${encodeURIComponent(currentEvent.id)}" class="btn btn-outline btn-sm">
                            <i class="fas fa-pen"></i>
                            Modifier cet evenement
                        </a>
                    </div>

                    <p class="admin-event-description">${escapeHtml(currentEvent.description || 'Aucune description disponible pour cet evenement.')}</p>

                    <div class="admin-event-meta">
                        <div class="admin-event-meta-item">
                            <i class="fas fa-calendar-day"></i>
                            <div>
                                <strong>${escapeHtml(formatDate(currentEvent.date, 'medium'))}</strong>
                                <span>${escapeHtml(formatDate(currentEvent.date, 'time'))}</span>
                            </div>
                        </div>
                        <div class="admin-event-meta-item">
                            <i class="fas fa-location-dot"></i>
                            <div>
                                <strong>${escapeHtml(currentEvent.location || 'Lieu non renseigne')}</strong>
                                <span>Lieu de l evenement</span>
                            </div>
                        </div>
                        <div class="admin-event-meta-item">
                            <i class="fas fa-users"></i>
                            <div>
                                <strong>${escapeHtml(String(currentEvent.seats || 0))} places</strong>
                                <span>${escapeHtml(String(currentEvent.available_seats || 0))} encore disponibles</span>
                            </div>
                        </div>
                    </div>

                    <div class="admin-progress admin-event-progress">
                        <div class="admin-progress-bar" style="width: ${occupancy}%"></div>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function renderStatsCards(stats) {
    const statsCards = document.getElementById('statsCards');
    statsCards.style.display = 'grid';

    const confirmed = Number(stats.confirmed || 0);
    const cancelled = Number(stats.cancelled || 0);
    const total = Number(stats.total || 0);
    const seats = Number(currentEvent?.seats || 0);
    const remaining = Math.max(0, seats - confirmed);

    const cards = [
        {
            icon: 'fa-ticket',
            value: total,
            label: 'Reservations totales',
            color: 'primary',
        },
        {
            icon: 'fa-circle-check',
            value: confirmed,
            label: 'Confirmees',
            color: 'success',
        },
        {
            icon: 'fa-ban',
            value: cancelled,
            label: 'Annulees',
            color: 'warning',
        },
        {
            icon: 'fa-chair',
            value: remaining,
            label: 'Places restantes',
            color: 'info',
        },
    ];

    statsCards.innerHTML = cards.map((card) => `
        <article class="stat-card ${card.color}">
            <div class="stat-icon ${card.color}">
                <i class="fas ${card.icon}"></i>
            </div>
            <div class="stat-value">${escapeHtml(card.value)}</div>
            <div class="stat-label">${escapeHtml(card.label)}</div>
        </article>
    `).join('');
}

function getFilteredReservations() {
    const filterStatus = document.getElementById('filterStatus')?.value || 'all';
    const searchTerm = (document.getElementById('searchInput')?.value || '').trim().toLowerCase();

    return adminReservations.filter((reservation) => {
        const matchesStatus = filterStatus === 'all' || reservation.status === filterStatus;
        const haystack = [
            reservation.name,
            reservation.email,
            reservation.phone,
            reservation.status,
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        const matchesSearch = searchTerm === '' || haystack.includes(searchTerm);

        return matchesStatus && matchesSearch;
    });
}

function displayReservations() {
    const reservationsSection = document.getElementById('reservationsSection');
    const reservationsContainer = document.getElementById('reservationsContainer');
    const reservations = getFilteredReservations();

    if (reservationsSection) {
        reservationsSection.style.display = 'block';
    }

    if (reservations.length === 0) {
        reservationsContainer.innerHTML = `
            <div class="admin-empty-state">
                <p class="text-gray">Aucune reservation ne correspond au filtre actuel.</p>
            </div>
        `;
        return;
    }

    reservationsContainer.innerHTML = `
        <table class="admin-table admin-reservations-table">
            <thead>
                <tr>
                    <th>Participant</th>
                    <th>Contact</th>
                    <th>Inscription</th>
                    <th>Statut</th>
                    <th>Compte</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                ${reservations.map((reservation) => createReservationRow(reservation)).join('')}
            </tbody>
        </table>
    `;
}

function createReservationRow(reservation) {
    const badgeClass = reservation.status === 'cancelled' ? 'badge-error' : 'badge-success';
    const badgeLabel = reservation.status === 'cancelled' ? 'Annulee' : 'Confirmee';
    const userState = reservation.user_id
        ? '<span class="badge badge-info">Compte lie</span>'
        : '<span class="badge badge-warning">Sans compte</span>';
    const encodedEmail = encodeURIComponent(reservation.email || '');
    const encodedPhone = encodeURIComponent(reservation.phone || '');

    return `
        <tr>
            <td>
                <div class="admin-table-title">${escapeHtml(reservation.name || 'Participant')}</div>
                <div class="admin-table-subtitle">${escapeHtml(reservation.id)}</div>
            </td>
            <td>
                <div class="admin-contact-stack">
                    <a href="mailto:${encodedEmail}" class="admin-contact-link">${escapeHtml(reservation.email || 'Email indisponible')}</a>
                    <a href="tel:${encodedPhone}" class="admin-table-subtitle admin-contact-link">${escapeHtml(reservation.phone || 'Telephone indisponible')}</a>
                </div>
            </td>
            <td>
                <div>${escapeHtml(formatDate(reservation.created_at, 'short'))}</div>
                <div class="admin-table-subtitle">${escapeHtml(formatDate(reservation.created_at, 'time'))}</div>
            </td>
            <td>
                <span class="badge ${badgeClass}">${badgeLabel}</span>
            </td>
            <td>${userState}</td>
            <td>
                <div class="action-buttons">
                    <a class="btn btn-outline btn-icon btn-sm" href="mailto:${encodedEmail}" title="Envoyer un email">
                        <i class="fas fa-envelope"></i>
                    </a>
                    <a class="btn btn-secondary btn-icon btn-sm" href="tel:${encodedPhone}" title="Appeler">
                        <i class="fas fa-phone"></i>
                    </a>
                </div>
            </td>
        </tr>
    `;
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

async function handleLogout() {
    const shouldLogout = confirm('Etes-vous sur de vouloir vous deconnecter ?');

    if (shouldLogout) {
        await api.logout('/admin/');
    }
}
