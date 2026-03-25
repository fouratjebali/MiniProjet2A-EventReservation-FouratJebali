let reservationToCancel = null;

document.addEventListener('DOMContentLoaded', async () => {
    await checkAuth();
    await loadReservations();
});

async function checkAuth() {
    if (!api.isAuthenticated()) {
        window.location.href = '/';
        return;
    }

    try {
        const { ok, data } = await api.getCurrentUser();

        if (ok && data?.email) {
            document.getElementById('userEmail').textContent = data.email;
            return;
        }
    } catch (error) {
        console.error('Failed to load current user', error);
    }

    api.clearTokens();
    window.location.href = '/';
}

async function loadReservations() {
    const container = document.getElementById('reservationsContainer');
    showLoading(container, 'Chargement de vos reservations...');

    try {
        const { ok, data } = await api.getMyReservations();

        if (!ok || !data) {
            showError(container, 'Impossible de charger vos reservations');
            return;
        }

        displayReservations(data.reservations || []);
    } catch (error) {
        console.error('Failed to load reservations', error);
        showError(container, 'Impossible de charger vos reservations');
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

function displayReservations(reservations) {
    const container = document.getElementById('reservationsContainer');

    if (reservations.length === 0) {
        container.innerHTML = `
            <div style="text-align: center; padding: 60px 20px; background: white; border-radius: var(--radius-xl); box-shadow: var(--shadow-sm);">
                <div style="font-size: 4rem; margin-bottom: 20px;">Reservation</div>
                <h3 style="color: var(--gray-700); margin-bottom: 15px;">Aucune reservation</h3>
                <p class="text-gray" style="margin-bottom: 30px;">
                    Vous n'avez pas encore reserve d'evenements
                </p>
                <a href="/" class="btn btn-primary">
                    Decouvrir les evenements
                </a>
            </div>
        `;
        return;
    }

    const activeReservations = reservations.filter((reservation) => reservation.status === 'confirmed');
    const cancelledReservations = reservations.filter((reservation) => reservation.status === 'cancelled');

    container.innerHTML = `
        ${activeReservations.length > 0 ? `
            <h2 style="font-size: var(--text-2xl); margin-bottom: 20px; color: var(--gray-900);">
                Reservations actives (${activeReservations.length})
            </h2>
            <div class="grid grid-cols-2" style="margin-bottom: 60px;">
                ${activeReservations.map((reservation) => createReservationCard(reservation)).join('')}
            </div>
        ` : ''}

        ${cancelledReservations.length > 0 ? `
            <h2 style="font-size: var(--text-2xl); margin-bottom: 20px; color: var(--gray-600);">
                Reservations annulees (${cancelledReservations.length})
            </h2>
            <div class="grid grid-cols-2">
                ${cancelledReservations.map((reservation) => createReservationCard(reservation, true)).join('')}
            </div>
        ` : ''}
    `;
}

function createReservationCard(reservation, isCancelled = false) {
    const eventDate = parseApiDate(reservation.event?.date);
    const isPast = eventDate ? eventDate < new Date() : false;
    const title = escapeHtml(reservation.event?.title || 'Evenement');
    const location = escapeHtml(reservation.event?.location || '');
    const name = escapeHtml(reservation.name || '');
    const email = escapeHtml(reservation.email || '');
    const phone = escapeHtml(reservation.phone || '');
    const reservationId = encodeURIComponent(reservation.id);
    const reservedAt = escapeHtml(formatDate(reservation.created_at, 'short'));
    const eventDateLabel = escapeHtml(formatDate(reservation.event?.date, 'full'));

    let badge = '<span class="badge badge-success">Confirmee</span>';
    if (isCancelled) {
        badge = '<span class="badge badge-error">Annulee</span>';
    } else if (isPast) {
        badge = '<span class="badge badge-warning">Passee</span>';
    }

    return `
        <div class="card" style="${isCancelled ? 'opacity: 0.6;' : ''}">
            <div class="card-content">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                    <h3 class="card-title">${title}</h3>
                    ${badge}
                </div>

                <div style="background: var(--gray-50); padding: 15px; border-radius: var(--radius-md); margin-bottom: 15px;">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                        <span style="font-size: 1.2rem;">Date</span>
                        <span class="text-gray">${eventDateLabel}</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                        <span style="font-size: 1.2rem;">Lieu</span>
                        <span class="text-gray">${location}</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 1.2rem;">Nom</span>
                        <span class="text-gray">${name}</span>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr; gap: 10px; font-size: var(--text-sm);">
                    <div>
                        <strong>Email:</strong> ${email}
                    </div>
                    <div>
                        <strong>Telephone:</strong> ${phone}
                    </div>
                    <div>
                        <strong>Reserve le:</strong> ${reservedAt}
                    </div>
                </div>

                ${!isCancelled && !isPast ? `
                    <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--gray-200);">
                        <button
                            type="button"
                            class="btn btn-error btn-sm"
                            style="width: 100%;"
                            onclick="openCancelModal('${reservationId}')">
                            Annuler la reservation
                        </button>
                    </div>
                ` : ''}
            </div>
        </div>
    `;
}

function openCancelModal(reservationId) {
    reservationToCancel = reservationId;
    document.getElementById('cancelModal').classList.add('active');

    const confirmButton = document.getElementById('confirmCancelBtn');
    confirmButton.disabled = false;
    confirmButton.textContent = 'Oui, annuler';
    confirmButton.onclick = () => confirmCancel();
}

function closeCancelModal() {
    document.getElementById('cancelModal').classList.remove('active');
    reservationToCancel = null;
}

async function confirmCancel() {
    if (!reservationToCancel) {
        return;
    }

    const button = document.getElementById('confirmCancelBtn');
    button.disabled = true;
    button.innerHTML = '<div class="loading"></div> Annulation...';

    try {
        const { ok, data } = await api.cancelReservation(reservationToCancel);

        if (ok) {
            showToast('Reservation annulee avec succes', 'success');
            closeCancelModal();
            await loadReservations();
            return;
        }

        showToast(data?.error || 'Erreur lors de l\'annulation', 'error');
    } catch (error) {
        console.error('Failed to cancel reservation', error);
        showToast('Erreur lors de l\'annulation', 'error');
    } finally {
        button.disabled = false;
        button.textContent = 'Oui, annuler';
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

window.onclick = function onWindowClick(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('active');
    }
};
