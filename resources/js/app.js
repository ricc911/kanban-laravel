const state = {
    user: null,
    workspaces: [],
};

const elements = {
    auth: document.querySelector('[data-auth]'),
    dashboard: document.querySelector('[data-dashboard]'),
    registerForm: document.querySelector('[data-register-form]'),
    loginForm: document.querySelector('[data-login-form]'),
    logoutButton: document.querySelector('[data-logout]'),
    authMessage: document.querySelector('[data-auth-message]'),
    dashboardMessage: document.querySelector('[data-dashboard-message]'),
    userEmail: document.querySelector('[data-user-email]'),
    workspaces: document.querySelector('[data-workspaces]'),
};

function csrfToken() {
    const cookie = document.cookie
        .split('; ')
        .find((value) => value.startsWith('XSRF-TOKEN='));

    return cookie ? decodeURIComponent(cookie.split('=').slice(1).join('=')) : null;
}

async function initialiseCsrf() {
    const response = await fetch('/sanctum/csrf-cookie', {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
    });

    if (!response.ok) {
        throw new Error('Impossibile inizializzare la protezione CSRF.');
    }
}

async function request(url, options = {}) {
    const method = (options.method ?? 'GET').toUpperCase();
    const headers = {
        Accept: 'application/json',
        ...(options.body ? { 'Content-Type': 'application/json' } : {}),
        ...(options.headers ?? {}),
    };

    if (!['GET', 'HEAD', 'OPTIONS'].includes(method)) {
        await initialiseCsrf();
        headers['X-XSRF-TOKEN'] = csrfToken() ?? '';
    }

    const response = await fetch(url, {
        ...options,
        method,
        headers,
        credentials: 'same-origin',
    });
    const contentType = response.headers.get('content-type') ?? '';
    const payload = contentType.includes('application/json')
        ? await response.json()
        : null;

    if (!response.ok) {
        const validationMessage = payload?.errors
            ? Object.values(payload.errors).flat().join(' ')
            : null;
        throw new Error(validationMessage ?? payload?.message ?? `Errore HTTP ${response.status}.`);
    }

    return payload;
}

function showMessage(target, message = '', isError = true) {
    target.textContent = message;
    target.classList.toggle('hidden', !message);
    target.classList.toggle('text-red-600', isError);
    target.classList.toggle('text-emerald-600', !isError);
}

function setAuthenticatedView() {
    elements.auth.classList.add('hidden');
    elements.dashboard.classList.remove('hidden');
    elements.userEmail.textContent = state.user.email;
    renderWorkspaces();
}

function setGuestView() {
    state.user = null;
    state.workspaces = [];
    elements.dashboard.classList.add('hidden');
    elements.auth.classList.remove('hidden');
    elements.registerForm.reset();
    elements.loginForm.reset();
}

function renderWorkspaces() {
    elements.workspaces.replaceChildren();

    if (state.workspaces.length === 0) {
        const empty = document.createElement('p');
        empty.className = 'rounded-xl border border-dashed border-slate-300 p-6 text-slate-500';
        empty.textContent = 'Non hai ancora workspace disponibili.';
        elements.workspaces.append(empty);
        return;
    }

    state.workspaces.forEach((workspace) => {
        const item = document.createElement('li');
        item.className = 'rounded-xl border border-slate-200 bg-white p-5 shadow-sm';

        const name = document.createElement('h3');
        name.className = 'font-semibold text-slate-900';
        name.textContent = workspace.name;

        const meta = document.createElement('p');
        meta.className = 'mt-2 text-sm text-slate-500';
        meta.textContent = `${workspace.type} · ruolo: ${workspace.pivot?.role ?? 'member'}`;

        item.append(name, meta);
        elements.workspaces.append(item);
    });
}

async function loadAuthenticatedUser() {
    const userResponse = await request('/api/user');
    state.user = userResponse.data ?? userResponse;

    const workspaceResponse = await request('/api/workspaces');
    state.workspaces = workspaceResponse.data ?? [];
    setAuthenticatedView();
}

elements.registerForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    showMessage(elements.authMessage, '');

    try {
        await request('/register', {
            method: 'POST',
            body: JSON.stringify(Object.fromEntries(new FormData(event.currentTarget))),
        });
        await loadAuthenticatedUser();
    } catch (error) {
        showMessage(elements.authMessage, error.message);
    }
});

elements.loginForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    showMessage(elements.authMessage, '');

    try {
        await request('/login', {
            method: 'POST',
            body: JSON.stringify(Object.fromEntries(new FormData(event.currentTarget))),
        });
        await loadAuthenticatedUser();
    } catch (error) {
        showMessage(elements.authMessage, error.message);
    }
});

elements.logoutButton.addEventListener('click', async () => {
    showMessage(elements.dashboardMessage, '');

    try {
        await request('/logout', { method: 'POST' });
        setGuestView();
    } catch (error) {
        showMessage(elements.dashboardMessage, error.message);
    }
});

loadAuthenticatedUser().catch(() => setGuestView());


/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import './echo';
