let dashboardStats = null;
let eventsChart = null;
let occupancyChart = null;

document.addEventListener('DOMContentLoaded', async () => {
    const authenticated = await checkAdminAuth();

    if (!authenticated) {
        return;
    }

    await loadDashboard();
});

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

function renderAccessMessage(title, message, details = []) {
    const statsContainer = document.getElementById('statsContainer');
    const recentEventsContainer = document.getElementById('recentEventsContainer');
    const charts = [
        document.getElementById('eventsChartCanvas')?.parentElement,
        document.getElementById('occupancyChartCanvas')?.parentElement,
    ].filter(Boolean);

    const detailsHtml = details.length > 0
        ? `
            <ul class="admin-list" style="margin-top: 16px;">
                ${details.map((detail) => `<li><i class="fas fa-circle-info text-warning"></i><span>${escapeHtml(detail)}</span></li>`).join('')}
            </ul>
        `
        : '';

    const block = `
        <div class="admin-access-state card">
            <div class="card-content">
                <span class="badge badge-warning">Acces admin requis</span>
                <h2 class="card-title" style="margin-top: 14px;">${escapeHtml(title)}</h2>
                <p class="text-gray">${escapeHtml(message)}</p>
                ${detailsHtml}
            </div>
        </div>
    `;

    if (statsContainer) {
        statsContainer.innerHTML = `<div style="grid-column: 1 / -1;">${block}</div>`;
    }

    charts.forEach((chartShell) => {
        chartShell.innerHTML = `
            <div class="admin-empty-state">
                <p class="text-gray">Le dashboard attend une authentification admin valide.</p>
            </div>
        `;
    });

    if (recentEventsContainer) {
        recentEventsContainer.innerHTML = `
            <div class="admin-empty-state">
                <p class="text-gray">Corrigez le localStorage admin puis rechargez la page.</p>
            </div>
        `;
    }
}

async function loadDashboard() {
    const statsContainer = document.getElementById('statsContainer');
    const recentEventsContainer = document.getElementById('recentEventsContainer');

    showLoading(statsContainer, 'Chargement des statistiques...');
    showLoading(recentEventsContainer, 'Chargement des evenements recents...');

    try {
        dashboardStats = await api.getDashboardStats();

        displayStatsCards();
        displayCharts();
        displayRecentEvents();
    } catch (error) {
        console.error('Error loading dashboard:', error);
        showError(statsContainer, 'Impossible de charger les statistiques du dashboard');
        showError(recentEventsContainer, 'Impossible de charger les evenements recents');
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

function displayStatsCards() {
    const container = document.getElementById('statsContainer');

    const stats = [
        {
            icon: 'fa-calendar-days',
            value: dashboardStats.events.total,
            label: 'Evenements totaux',
            color: 'primary',
        },
        {
            icon: 'fa-clock',
            value: dashboardStats.events.upcoming,
            label: 'Evenements a venir',
            color: 'success',
        },
        {
            icon: 'fa-ticket',
            value: dashboardStats.reservations.total,
            label: 'Reservations totales',
            color: 'info',
        },
        {
            icon: 'fa-chart-pie',
            value: `${dashboardStats.reservations.occupancy}%`,
            label: 'Taux d occupation',
            color: 'warning',
        },
    ];

    container.innerHTML = stats.map((stat) => `
        <article class="stat-card ${stat.color}">
            <div class="stat-icon ${stat.color}">
                <i class="fas ${stat.icon}"></i>
            </div>
            <div class="stat-value">${escapeHtml(stat.value)}</div>
            <div class="stat-label">${escapeHtml(stat.label)}</div>
        </article>
    `).join('');
}

function displayCharts() {
    if (typeof Chart === 'undefined') {
        return;
    }

    if (eventsChart) {
        eventsChart.destroy();
    }

    if (occupancyChart) {
        occupancyChart.destroy();
    }

    const eventsCanvas = document.getElementById('eventsChartCanvas');
    const occupancyCanvas = document.getElementById('occupancyChartCanvas');

    if (!eventsCanvas || !occupancyCanvas) {
        return;
    }

    eventsChart = new Chart(eventsCanvas.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: ['A venir', 'Passes'],
            datasets: [{
                data: [dashboardStats.events.upcoming, dashboardStats.events.past],
                backgroundColor: ['#10B981', '#94A3B8'],
                borderWidth: 0,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        boxWidth: 10,
                    },
                },
            },
        },
    });

    occupancyChart = new Chart(occupancyCanvas.getContext('2d'), {
        type: 'bar',
        data: {
            labels: ['Reservees', 'Disponibles'],
            datasets: [{
                label: 'Places',
                data: [dashboardStats.reservations.total, dashboardStats.reservations.available],
                backgroundColor: ['#6366F1', '#E2E8F0'],
                borderRadius: 10,
                borderSkipped: false,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false,
                },
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0,
                    },
                },
            },
        },
    });
}

function displayRecentEvents() {
    const container = document.getElementById('recentEventsContainer');

    if (!dashboardStats.recentEvents || dashboardStats.recentEvents.length === 0) {
        container.innerHTML = `
            <div class="admin-empty-state">
                <p class="text-gray">Aucun evenement recent pour le moment.</p>
                <a href="/api/events" class="btn btn-primary btn-sm" target="_blank" rel="noopener noreferrer">
                    <i class="fas fa-arrow-up-right-from-square"></i>
                    Ouvrir l API events
                </a>
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
                ${dashboardStats.recentEvents.map((event) => createEventRow(event)).join('')}
            </tbody>
        </table>
    `;
}

function createEventRow(event) {
    const occupancy = event.seats > 0
        ? Math.min(100, Math.round((Number(event.reservations_count || 0) / Number(event.seats || 1)) * 100))
        : 0;

    const availability = event.is_available
        ? `<span class="badge badge-success">${escapeHtml(event.available_seats)} disponibles</span>`
        : '<span class="badge badge-error">Complet ou passe</span>';

    const title = escapeHtml(event.title);
    const description = escapeHtml(truncate(event.description || '', 60));
    const location = escapeHtml(event.location);
    const eventId = encodeURIComponent(event.id);

    return `
        <tr>
            <td>
                <div class="admin-table-title">${title}</div>
                <div class="admin-table-subtitle">${description}</div>
            </td>
            <td>
                <div>${escapeHtml(formatDate(event.date, 'short'))}</div>
                <div class="admin-table-subtitle">${escapeHtml(formatDate(event.date, 'time'))}</div>
            </td>
            <td>${location}</td>
            <td>
                <div>${escapeHtml(event.reservations_count)} / ${escapeHtml(event.seats)}</div>
                <div class="admin-progress">
                    <div class="admin-progress-bar" style="width: ${occupancy}%"></div>
                </div>
            </td>
            <td>${availability}</td>
            <td>
                <div class="action-buttons">
                    <a class="btn btn-outline btn-icon btn-sm"
                       href="/event.html?id=${eventId}"
                       target="_blank"
                       rel="noopener noreferrer"
                       title="Voir l evenement">
                        <i class="fas fa-eye"></i>
                    </a>
                    <a class="btn btn-secondary btn-icon btn-sm"
                       href="/admin/reservations.html?eventId=${eventId}"
                       title="Voir les reservations">
                        <i class="fas fa-ticket"></i>
                    </a>
                </div>
            </td>
        </tr>
    `;
}

async function handleLogout() {
    const shouldLogout = confirm('Etes-vous sur de vouloir vous deconnecter ?');

    if (shouldLogout) {
        await api.logout('/admin/');
    }
}
