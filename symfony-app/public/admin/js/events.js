let adminEvents = [];
let eventToDelete = null;
let searchHandler = null;

document.addEventListener('DOMContentLoaded', async () => {
    setupEventPage();

    const authenticated = await checkAdminAuth();
    if (!authenticated) {
        return;
    }

    await loadEvents();
    handleEditFromQuery();
});

function setupEventPage() {
    const form = document.getElementById('eventForm');
    const imageInput = document.getElementById('imageInput');
    const filterStatus = document.getElementById('filterStatus');
    const searchInput = document.getElementById('searchInput');

    form?.addEventListener('submit', handleSaveEvent);
    imageInput?.addEventListener('change', previewImage);
    filterStatus?.addEventListener('change', () => {
        displayEvents();
    });

    searchHandler = debounce(() => {
        displayEvents();
    }, 250);

    searchInput?.addEventListener('input', searchHandler);

    window.addEventListener('click', (event) => {
        if (event.target.classList.contains('modal')) {
            event.target.classList.remove('active');
        }
    });
}

async function checkAdminAuth() {
    if (!api.isAuthenticated()) {
        renderAdminAccessState(
            'Token admin manquant',
            'Cette page a besoin d un jwt_token admin et d un auth_user contenant ROLE_ADMIN avant de charger la gestion des evenements.'
        );
        return false;
    }

    if (!api.isAdmin()) {
        renderAdminAccessState(
            'Role admin non detecte',
            'Le token existe, mais auth_user ne contient pas ROLE_ADMIN ou la valeur locale est invalide.'
        );
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

function renderAdminAccessState(title, message) {
    const container = document.getElementById('eventsContainer');
    const formSection = document.getElementById('eventFormSection');
    const createButton = document.getElementById('createEventBtn');

    formSection.style.display = 'none';
    createButton.disabled = true;

    container.innerHTML = `
        <div class="admin-access-state card">
            <div class="card-content">
                <span class="badge badge-warning">Acces admin requis</span>
                <h2 class="card-title" style="margin-top: 14px;">${escapeHtml(title)}</h2>
                <p class="text-gray">${escapeHtml(message)}</p>
            </div>
        </div>
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

async function loadEvents() {
    const container = document.getElementById('eventsContainer');
    showLoading(container, 'Chargement des evenements...');

    try {
        const result = await api.getEvents({ limit: 100 });

        if (!result.ok || !result.data) {
            showError(container, result.data?.error || 'Impossible de charger les evenements');
            return;
        }

        adminEvents = result.data.events || [];
        displayEvents();
    } catch (error) {
        console.error('Failed to load admin events', error);
        showError(container, 'Impossible de charger les evenements');
    }
}

function getFilteredEvents() {
    const filterStatus = document.getElementById('filterStatus')?.value || 'all';
    const searchTerm = (document.getElementById('searchInput')?.value || '').trim().toLowerCase();
    const now = new Date();

    return adminEvents.filter((event) => {
        const eventDate = parseApiDate(event.date);
        const matchesSearch = searchTerm === ''
            || String(event.title).toLowerCase().includes(searchTerm)
            || String(event.location).toLowerCase().includes(searchTerm)
            || String(event.description).toLowerCase().includes(searchTerm);

        let matchesStatus = true;
        if (filterStatus === 'upcoming') {
            matchesStatus = eventDate ? eventDate > now : false;
        } else if (filterStatus === 'past') {
            matchesStatus = eventDate ? eventDate <= now : false;
        }

        return matchesSearch && matchesStatus;
    });
}

function displayEvents() {
    const container = document.getElementById('eventsContainer');
    const events = getFilteredEvents();

    if (events.length === 0) {
        container.innerHTML = `
            <div class="admin-empty-state">
                <p class="text-gray">Aucun evenement ne correspond au filtre actuel.</p>
                <button type="button" class="btn btn-primary btn-sm" onclick="showCreateEventForm()">
                    <i class="fas fa-plus"></i>
                    Creer un evenement
                </button>
            </div>
        `;
        return;
    }

    container.innerHTML = `
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Evenement</th>
                    <th>Date</th>
                    <th>Lieu</th>
                    <th>Places</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                ${events.map((event) => createEventRow(event)).join('')}
            </tbody>
        </table>
    `;
}

function createEventRow(event) {
    const title = escapeHtml(event.title);
    const description = escapeHtml(truncate(event.description || '', 70));
    const location = escapeHtml(event.location);
    const eventId = encodeURIComponent(event.id);
    const dateShort = escapeHtml(formatDate(event.date, 'short'));
    const timeShort = escapeHtml(formatDate(event.date, 'time'));
    const occupancy = Number(event.seats || 0) > 0
        ? Math.min(100, Math.round((Number(event.reservations_count || 0) / Number(event.seats || 1)) * 100))
        : 0;
    const availabilityBadge = event.is_available
        ? `<span class="badge badge-success">${escapeHtml(event.available_seats)} disponibles</span>`
        : '<span class="badge badge-error">Complet ou passe</span>';

    return `
        <tr>
            <td>
                <div class="admin-table-title">${title}</div>
                <div class="admin-table-subtitle">${description}</div>
            </td>
            <td>
                <div>${dateShort}</div>
                <div class="admin-table-subtitle">${timeShort}</div>
            </td>
            <td>${location}</td>
            <td>
                <div>${escapeHtml(event.reservations_count)} / ${escapeHtml(event.seats)}</div>
                <div class="admin-progress">
                    <div class="admin-progress-bar" style="width: ${occupancy}%"></div>
                </div>
            </td>
            <td>${availabilityBadge}</td>
            <td>
                <div class="action-buttons">
                    <button type="button" class="btn btn-outline btn-icon btn-sm" onclick="editEvent('${eventId}')" title="Modifier">
                        <i class="fas fa-pen"></i>
                    </button>
                    <a class="btn btn-secondary btn-icon btn-sm" href="/admin/reservations.html?eventId=${eventId}" title="Reservations">
                        <i class="fas fa-ticket"></i>
                    </a>
                    <a class="btn btn-secondary btn-icon btn-sm" href="/event.html?id=${eventId}" target="_blank" rel="noopener noreferrer" title="Voir">
                        <i class="fas fa-eye"></i>
                    </a>
                    <button type="button" class="btn btn-error btn-icon btn-sm" onclick="openDeleteModal('${eventId}')" title="Supprimer">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
    `;
}

function showCreateEventForm() {
    const form = document.getElementById('eventForm');
    form.reset();

    document.getElementById('eventId').value = '';
    document.getElementById('formTitle').textContent = 'Creer un evenement';
    document.getElementById('submitBtn').innerHTML = '<i class="fas fa-floppy-disk"></i> Enregistrer';
    document.getElementById('eventFormSection').style.display = 'block';

    clearFormMessages();
    resetImagePreview();
    document.getElementById('eventTitle').focus();
    document.getElementById('eventFormSection').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function hideEventForm() {
    document.getElementById('eventFormSection').style.display = 'none';
    clearFormMessages();
}

function clearFormMessages() {
    const error = document.getElementById('formError');
    const success = document.getElementById('formSuccess');
    error.style.display = 'none';
    error.textContent = '';
    success.style.display = 'none';
    success.textContent = '';
}

function resetImagePreview(imageUrl = null) {
    const preview = document.getElementById('imagePreview');

    if (!imageUrl) {
        preview.innerHTML = `
            <div class="image-preview-placeholder">
                <i class="fas fa-image"></i>
                <p>Aucune image selectionnee</p>
            </div>
        `;
        return;
    }

    preview.innerHTML = `<img src="${escapeHtml(imageUrl)}" alt="Apercu image evenement">`;
}

function previewImage(event) {
    const file = event.target.files?.[0];

    if (!file) {
        resetImagePreview();
        return;
    }

    const reader = new FileReader();
    reader.onload = (loadEvent) => {
        resetImagePreview(loadEvent.target?.result || null);
    };
    reader.readAsDataURL(file);
}

function findEventById(eventId) {
    return adminEvents.find((event) => event.id === eventId) || null;
}

async function editEvent(eventId) {
    const event = findEventById(eventId) || (await api.getEvent(eventId)).data;

    if (!event) {
        showToast('Evenement introuvable', 'error');
        return;
    }

    document.getElementById('eventId').value = event.id;
    document.getElementById('eventTitle').value = event.title || '';
    document.getElementById('eventDescription').value = event.description || '';
    document.getElementById('eventLocation').value = event.location || '';
    document.getElementById('eventSeats').value = event.seats || '';
    document.getElementById('eventDate').value = formatDateTimeLocal(event.date);
    document.getElementById('formTitle').textContent = 'Modifier un evenement';
    document.getElementById('submitBtn').innerHTML = '<i class="fas fa-pen"></i> Mettre a jour';

    clearFormMessages();
    resetImagePreview(event.image ? getImageUrl(event.image) : null);
    document.getElementById('imageInput').value = '';
    document.getElementById('eventFormSection').style.display = 'block';
    document.getElementById('eventFormSection').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function formatDateTimeLocal(dateString) {
    const date = parseApiDate(dateString);

    if (!date) {
        return '';
    }

    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');

    return `${year}-${month}-${day}T${hours}:${minutes}`;
}

async function handleSaveEvent(event) {
    event.preventDefault();

    const form = document.getElementById('eventForm');
    const submitBtn = document.getElementById('submitBtn');
    const eventId = document.getElementById('eventId').value;
    const formData = new FormData(form);
    const imageInput = document.getElementById('imageInput');
    const imageFile = imageInput.files?.[0];

    if (imageFile) {
        formData.set('imageFile', imageFile);
    } else {
        formData.delete('imageFile');
    }

    clearFormMessages();
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enregistrement...';

    try {
        const result = eventId
            ? await api.updateEvent(eventId, formData)
            : await api.createEvent(formData);

        if (!result.ok) {
            showFormError(result.data);
            return;
        }

        const savedEvent = result.data?.event || null;
        const successMessage = eventId ? 'Evenement mis a jour avec succes.' : 'Evenement cree avec succes.';

        document.getElementById('formSuccess').textContent = successMessage;
        document.getElementById('formSuccess').style.display = 'block';
        showToast(successMessage, 'success');

        hideEventForm();
        await loadEvents();

        if (savedEvent?.id) {
            const url = new URL(window.location.href);
            url.searchParams.delete('edit');
            window.history.replaceState({}, '', url);
        }
    } catch (error) {
        console.error('Failed to save event', error);
        document.getElementById('formError').textContent = 'Erreur lors de l enregistrement de l evenement.';
        document.getElementById('formError').style.display = 'block';
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = eventId
            ? '<i class="fas fa-pen"></i> Mettre a jour'
            : '<i class="fas fa-floppy-disk"></i> Enregistrer';
    }
}

function showFormError(data) {
    const formError = document.getElementById('formError');
    const details = data?.details;

    if (details && typeof details === 'object') {
        const messages = Object.values(details).map((value) => String(value));
        formError.innerHTML = messages.map((message) => `<div>${escapeHtml(message)}</div>`).join('');
    } else {
        formError.textContent = data?.error || 'Validation echouee';
    }

    formError.style.display = 'block';
}

function openDeleteModal(eventId) {
    eventToDelete = eventId;
    const event = findEventById(eventId);
    const warning = document.getElementById('deleteWarning');

    warning.innerHTML = event
        ? `<div class="alert alert-warning"><strong>${escapeHtml(event.title)}</strong></div>`
        : '';

    document.getElementById('deleteModal').classList.add('active');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('active');
    eventToDelete = null;
    document.getElementById('deleteWarning').innerHTML = '';
}

async function confirmDelete() {
    if (!eventToDelete) {
        return;
    }

    const button = document.getElementById('confirmDeleteBtn');
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Suppression...';

    try {
        const result = await api.deleteEvent(eventToDelete);

        if (!result.ok) {
            showToast(result.data?.error || 'Suppression impossible', 'error');
            return;
        }

        showToast('Evenement supprime avec succes', 'success');
        closeDeleteModal();
        await loadEvents();
    } catch (error) {
        console.error('Failed to delete event', error);
        showToast('Erreur lors de la suppression', 'error');
    } finally {
        button.disabled = false;
        button.innerHTML = '<i class="fas fa-trash"></i> Supprimer';
    }
}

function handleEditFromQuery() {
    const params = new URLSearchParams(window.location.search);
    const eventId = params.get('edit');

    if (eventId) {
        editEvent(eventId);
    }
}

async function handleLogout() {
    const shouldLogout = confirm('Etes-vous sur de vouloir vous deconnecter ?');

    if (shouldLogout) {
        await api.logout();
    }
}

document.getElementById('confirmDeleteBtn')?.addEventListener('click', confirmDelete);
