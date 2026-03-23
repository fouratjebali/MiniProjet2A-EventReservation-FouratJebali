const API_BASE_URL = `${window.location.origin}/api`;

class API {
    constructor() {
        this.baseURL = API_BASE_URL;
        this.token = localStorage.getItem('jwt_token');
        this.refreshToken = localStorage.getItem('refresh_token');
        this.user = this.getStoredUser();
    }

    async request(endpoint, options = {}) {
        const url = `${this.baseURL}${endpoint}`;
        const headers = {
            Accept: 'application/json',
            ...(options.headers || {}),
        };

        if (this.token && !options.skipAuth) {
            headers.Authorization = `Bearer ${this.token}`;
        }

        if (!(options.body instanceof FormData) && !headers['Content-Type']) {
            headers['Content-Type'] = 'application/json';
        }

        const config = {
            ...options,
            headers,
        };

        try {
            let response = await fetch(url, config);

            if (response.status === 401 && this.refreshToken && !endpoint.includes('/token/refresh')) {
                const refreshed = await this.refreshAccessToken();
                if (refreshed) {
                    config.headers.Authorization = `Bearer ${this.token}`;
                    response = await fetch(url, config);
                }
            }

            return response;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }

    async parseResponse(response) {
        const contentType = response.headers.get('content-type') || '';

        if (!contentType.includes('application/json')) {
            return null;
        }

        return response.json();
    }

    getStoredUser() {
        const rawUser = localStorage.getItem('auth_user');

        if (!rawUser) {
            return null;
        }

        try {
            return JSON.parse(rawUser);
        } catch (error) {
            console.warn('Could not parse stored auth user', error);
            localStorage.removeItem('auth_user');

            return null;
        }
    }

    setToken(token, refreshToken = null, user = null) {
        this.token = token;
        localStorage.setItem('jwt_token', token);

        if (refreshToken) {
            this.refreshToken = refreshToken;
            localStorage.setItem('refresh_token', refreshToken);
        }

        if (user) {
            this.user = user;
            localStorage.setItem('auth_user', JSON.stringify(user));
        }
    }

    setUser(user) {
        this.user = user;

        if (user) {
            localStorage.setItem('auth_user', JSON.stringify(user));
        } else {
            localStorage.removeItem('auth_user');
        }
    }

    clearTokens() {
        this.token = null;
        this.refreshToken = null;
        this.user = null;
        localStorage.removeItem('jwt_token');
        localStorage.removeItem('refresh_token');
        localStorage.removeItem('auth_user');
    }

    isAuthenticated() {
        return !!this.token;
    }

    isAdmin() {
        return Array.isArray(this.user?.roles) && this.user.roles.includes('ROLE_ADMIN');
    }

    async refreshAccessToken() {
        try {
            const response = await fetch(`${this.baseURL}/token/refresh`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ refresh_token: this.refreshToken }),
            });

            const data = await this.parseResponse(response);

            if (response.ok && data?.token) {
                this.setToken(data.token, data.refresh_token ?? null, data.user ?? this.user);

                return true;
            }

            this.clearTokens();
            return false;
        } catch (error) {
            this.clearTokens();
            return false;
        }
    }

    async register(email, password) {
        const response = await this.request('/auth/register', {
            method: 'POST',
            body: JSON.stringify({ email, password }),
            skipAuth: true,
        });

        const data = await this.parseResponse(response);

        if (response.ok && data?.token) {
            this.setToken(data.token, data.refresh_token ?? null, data.user ?? null);
        }

        return { ok: response.ok, data };
    }

    async login(email, password) {
        const response = await this.request('/auth/login', {
            method: 'POST',
            body: JSON.stringify({ email, password }),
            skipAuth: true,
        });

        const data = await this.parseResponse(response);

        if (response.ok && data?.token) {
            this.setToken(data.token, data.refresh_token ?? null, data.user ?? null);
        }

        return { ok: response.ok, data };
    }

    async logout() {
        if (this.refreshToken) {
            await this.request('/auth/logout', {
                method: 'POST',
                body: JSON.stringify({ refresh_token: this.refreshToken }),
            });
        }

        this.clearTokens();
        window.location.href = '/';
    }

    async getCurrentUser() {
        const response = await this.request('/auth/me');
        const data = await this.parseResponse(response);

        if (response.ok && data) {
            this.setUser(data);
        }

        return { ok: response.ok, data };
    }

    async getEvents(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const endpoint = `/events${queryString ? `?${queryString}` : ''}`;

        const response = await this.request(endpoint, { skipAuth: true });
        const data = await this.parseResponse(response);

        return { ok: response.ok, data };
    }

    async getEvent(id) {
        const response = await this.request(`/events/${id}`, { skipAuth: true });
        const data = await this.parseResponse(response);

        return { ok: response.ok, data };
    }

    async createEvent(eventData) {
        const response = await this.request('/events', {
            method: 'POST',
            body: JSON.stringify(eventData),
        });

        const data = await this.parseResponse(response);
        return { ok: response.ok, data };
    }

    async updateEvent(id, eventData) {
        const response = await this.request(`/events/${id}`, {
            method: 'PUT',
            body: JSON.stringify(eventData),
        });

        const data = await this.parseResponse(response);
        return { ok: response.ok, data };
    }

    async deleteEvent(id) {
        const response = await this.request(`/events/${id}`, {
            method: 'DELETE',
        });

        const data = await this.parseResponse(response);
        return { ok: response.ok, data };
    }

    async uploadEventImage(eventId, imageFile) {
        const formData = new FormData();
        formData.append('imageFile', imageFile);

        const response = await this.request(`/events/${eventId}/upload-image`, {
            method: 'POST',
            body: formData,
        });

        const data = await this.parseResponse(response);
        return { ok: response.ok, data };
    }

    async createReservation(reservationData) {
        const response = await this.request('/reservations', {
            method: 'POST',
            body: JSON.stringify(reservationData),
        });

        const data = await this.parseResponse(response);
        return { ok: response.ok, data };
    }

    async getMyReservations() {
        const response = await this.request('/reservations/my-reservations');
        const data = await this.parseResponse(response);
        return { ok: response.ok, data };
    }

    async getReservation(id) {
        const response = await this.request(`/reservations/${id}`);
        const data = await this.parseResponse(response);
        return { ok: response.ok, data };
    }

    async cancelReservation(id) {
        const response = await this.request(`/reservations/${id}/cancel`, {
            method: 'POST',
        });

        const data = await this.parseResponse(response);
        return { ok: response.ok, data };
    }

    async getEventReservations(eventId) {
        const response = await this.request(`/reservations/event/${eventId}`);
        const data = await this.parseResponse(response);
        return { ok: response.ok, data };
    }

    async getPasskeyRegisterOptions(email) {
        const response = await this.request('/auth/passkey/register/options', {
            method: 'POST',
            body: JSON.stringify({ email }),
            skipAuth: true,
        });

        const data = await this.parseResponse(response);
        return { ok: response.ok, data };
    }

    async verifyPasskeyRegistration(email, credential) {
        const response = await this.request('/auth/passkey/register/verify', {
            method: 'POST',
            body: JSON.stringify({ email, credential }),
            skipAuth: true,
        });

        const data = await this.parseResponse(response);

        if (response.ok && data?.token) {
            this.setToken(data.token, data.refresh_token ?? null, data.user ?? null);
        }

        return { ok: response.ok, data };
    }

    async getPasskeyLoginOptions() {
        const response = await this.request('/auth/passkey/login/options', {
            method: 'POST',
            skipAuth: true,
        });

        const data = await this.parseResponse(response);
        return { ok: response.ok, data };
    }

    async verifyPasskeyLogin(credential) {
        const response = await this.request('/auth/passkey/login/verify', {
            method: 'POST',
            body: JSON.stringify({ credential }),
            skipAuth: true,
        });

        const data = await this.parseResponse(response);

        if (response.ok && data?.token) {
            this.setToken(data.token, data.refresh_token ?? null, data.user ?? null);
        }

        return { ok: response.ok, data };
    }
}

const api = new API();
