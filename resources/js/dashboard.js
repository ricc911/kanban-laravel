const DEFAULT_FOLDER_COLOR = '#4f6f9f';
const DEFAULT_PROJECT_COLOR = '#2563eb';
const STORAGE_WORKSPACE_KEY = 'kanban.dashboard.workspace';

const state = {
    user: null,
    workspaces: [],
    workspaceId: null,
    folders: [],
    boards: [],
    currentFolderId: null,
    viewingArchived: false,
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
    workspaceSelect: document.querySelector('[data-workspace-select]'),
    folderPath: document.querySelector('[data-folder-path]'),
    folderSection: document.querySelector('[data-folder-section]'),
    folders: document.querySelector('[data-folders]'),
    boards: document.querySelector('[data-boards]'),
    rootButton: document.querySelector('[data-root]'),
    archivedButton: document.querySelector('[data-archived]'),
    newProjectButton: document.querySelector('[data-new-project]'),
    newFolderButton: document.querySelector('[data-new-folder]'),
    projectModal: document.querySelector('[data-project-modal]'),
    projectForm: document.querySelector('[data-project-form]'),
    projectModalTitle: document.querySelector('[data-project-modal-title]'),
    projectModalNote: document.querySelector('[data-project-modal-note]'),
    projectName: document.querySelector('[data-project-name]'),
    projectDescription: document.querySelector('[data-project-description]'),
    projectColor: document.querySelector('[data-project-color]'),
    projectColorText: document.querySelector('[data-project-color-text]'),
    projectColorPreset: document.querySelector('[data-project-color-preset]'),
    folderModal: document.querySelector('[data-folder-modal]'),
    folderForm: document.querySelector('[data-folder-form]'),
    folderModalTitle: document.querySelector('[data-folder-modal-title]'),
    folderModalNote: document.querySelector('[data-folder-modal-note]'),
    folderName: document.querySelector('[data-folder-name]'),
    folderColor: document.querySelector('[data-folder-color]'),
    folderColorText: document.querySelector('[data-folder-color-text]'),
    folderColorPreset: document.querySelector('[data-folder-color-preset]'),
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
    const payload = contentType.includes('application/json') ? await response.json() : null;

    if (!response.ok) {
        const validationMessage = payload?.errors
            ? Object.values(payload.errors).flat().join(' ')
            : null;
        throw new Error(validationMessage ?? payload?.message ?? `Errore HTTP ${response.status}.`);
    }

    return payload;
}

function showMessage(target, message = '', isError = true) {
    if (!target) return;
    target.textContent = message;
    target.hidden = !message;
    target.classList.toggle('message', Boolean(message));
    target.style.color = isError ? '#fca5a5' : '#86efac';
}

function refreshIcons() {
    if (window.lucide?.createIcons) {
        window.lucide.createIcons();
    }
}

function icon(name) {
    const node = document.createElement('i');
    node.dataset.lucide = name;
    node.className = 'icon';
    return node;
}

function textNode(value) {
    return document.createTextNode(value ?? '');
}

function normalizeFolder(folder) {
    return {
        ...folder,
        id: Number(folder.id),
        parent_id: folder.parent_id === null || folder.parent_id === undefined ? null : Number(folder.parent_id),
        name: folder.name ?? 'Cartella',
        color: folder.color || DEFAULT_FOLDER_COLOR,
        archived: Boolean(folder.archived),
    };
}

function normalizeBoard(board) {
    return {
        ...board,
        id: Number(board.id),
        folder_id: board.folder_id === null || board.folder_id === undefined ? null : Number(board.folder_id),
        name: board.name ?? 'Board',
        description: board.description ?? '',
        color: board.color || DEFAULT_PROJECT_COLOR,
        archived: Boolean(board.archived),
        task_count: Number(board.tasks_count ?? board.task_count ?? 0),
    };
}

function activeWorkspace() {
    return state.workspaces.find((workspace) => Number(workspace.id) === Number(state.workspaceId)) ?? null;
}

function currentFolder() {
    return state.folders.find((folder) => folder.id === state.currentFolderId) ?? null;
}

function folderChildren(parentId, archived = state.viewingArchived) {
    return state.folders.filter((folder) => folder.parent_id === parentId && folder.archived === archived);
}

function folderTreeIds(folderId) {
    const ids = [folderId];
    const stack = [folderId];

    while (stack.length > 0) {
        const currentId = stack.pop();
        const children = state.folders.filter((folder) => folder.parent_id === currentId);
        children.forEach((folder) => {
            ids.push(folder.id);
            stack.push(folder.id);
        });
    }

    return ids;
}

function folderAncestors(folderId) {
    const ancestors = [];
    let folder = state.folders.find((item) => item.id === folderId) ?? null;
    const seen = new Set();

    while (folder && !seen.has(folder.id)) {
        ancestors.unshift(folder);
        seen.add(folder.id);
        folder = state.folders.find((item) => item.id === folder.parent_id) ?? null;
    }

    return ancestors;
}

function boardCountForFolder(folderId) {
    const ids = folderTreeIds(folderId);

    return state.boards.filter((board) => ids.includes(board.folder_id) && board.archived === state.viewingArchived).length;
}

function visibleBoards() {
    return state.boards.filter((board) => {
        if (board.archived !== state.viewingArchived) return false;
        return state.viewingArchived ? board.folder_id === state.currentFolderId : board.folder_id === state.currentFolderId;
    });
}

function formatDate(value) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';

    return new Intl.DateTimeFormat('it-IT', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
}

function setAuthenticatedView() {
    elements.auth.hidden = true;
    elements.dashboard.hidden = false;
    elements.userEmail.textContent = state.user?.email ?? '';
    renderWorkspaceOptions();
    renderDashboard();
}

function setGuestView() {
    state.user = null;
    state.workspaces = [];
    state.workspaceId = null;
    state.folders = [];
    state.boards = [];
    state.currentFolderId = null;
    state.viewingArchived = false;
    elements.dashboard.hidden = true;
    elements.auth.hidden = false;
    elements.registerForm.reset();
    elements.loginForm.reset();
    refreshIcons();
}

function renderWorkspaceOptions() {
    elements.workspaceSelect.replaceChildren();

    state.workspaces.forEach((workspace) => {
        const option = document.createElement('option');
        option.value = workspace.id;
        option.textContent = workspace.name;
        option.selected = Number(workspace.id) === Number(state.workspaceId);
        elements.workspaceSelect.append(option);
    });

    elements.workspaceSelect.hidden = state.workspaces.length <= 1;
}

function renderFolderPath() {
    elements.folderPath.replaceChildren();

    if (state.viewingArchived && !state.currentFolderId) {
        const archivedLabel = document.createElement('button');
        archivedLabel.type = 'button';
        archivedLabel.textContent = 'Progetti archiviati';
        archivedLabel.dataset.archivedPath = 'true';
        elements.folderPath.append(archivedLabel);
        return;
    }

    const root = document.createElement('button');
    root.type = 'button';
    root.dataset.folderPathRoot = 'true';
    root.textContent = 'Principale';
    elements.folderPath.append(root);

    folderAncestors(state.currentFolderId).forEach((folder) => {
        const separator = document.createElement('span');
        separator.className = 'folder-path-separator';
        separator.textContent = '/';

        const button = document.createElement('button');
        button.type = 'button';
        button.dataset.folderPath = String(folder.id);
        button.textContent = folder.name;

        elements.folderPath.append(separator, button);
    });
}

function renderFolders() {
    const children = folderChildren(state.currentFolderId, state.viewingArchived);
    elements.folders.replaceChildren();
    elements.folderSection.hidden = children.length === 0;

    children.forEach((folder) => {
        const card = document.createElement('article');
        card.className = 'folder-card';
        card.role = 'button';
        card.tabIndex = 0;
        card.dataset.folder = String(folder.id);
        card.style.setProperty('--folder-color', folder.color);

        const dot = document.createElement('span');
        dot.className = 'folder-dot';

        const name = document.createElement('span');
        name.className = 'folder-card-name';
        name.append(textNode(folder.name));

        const count = document.createElement('span');
        count.className = 'folder-count';
        count.textContent = `${boardCountForFolder(folder.id)} Kan`;

        const actions = document.createElement('span');
        actions.className = 'folder-card-actions';

        const edit = document.createElement('button');
        edit.type = 'button';
        edit.title = 'Modifica cartella';
        edit.dataset.editFolder = String(folder.id);
        edit.append(icon('pencil'));

        const archive = document.createElement('button');
        archive.type = 'button';
        archive.title = 'Archivio non collegato in questa fase';
        archive.dataset.disabledWrite = 'true';
        archive.append(icon('archive'));

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.title = 'Eliminazione non collegata in questa fase';
        remove.dataset.disabledWrite = 'true';
        remove.append(icon('trash-2'));

        actions.append(edit, archive, remove);
        card.append(dot, name, count, actions);
        elements.folders.append(card);
    });
}

function renderBoards() {
    const boards = visibleBoards();
    elements.boards.replaceChildren();

    if (boards.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'empty';
        empty.textContent = state.viewingArchived
            ? 'Nessun progetto archiviato in questa posizione.'
            : 'Nessun progetto in questa posizione.';
        elements.boards.append(empty);
        return;
    }

    boards.forEach((board) => {
        const card = document.createElement('article');
        card.className = 'project-card';
        card.style.setProperty('--project-color', board.color);

        const edit = document.createElement('button');
        edit.type = 'button';
        edit.className = 'project-action project-button edit-project with-icon';
        edit.title = 'Modifica progetto';
        edit.dataset.editProject = String(board.id);
        edit.append(icon('pencil'));

        const title = document.createElement('h3');
        title.className = 'project-name';
        title.textContent = board.name;

        const meta = document.createElement('p');
        meta.className = 'project-meta';
        meta.textContent = `${board.task_count} eventi`;

        const updated = document.createElement('p');
        updated.className = 'project-updated';
        const updatedAt = formatDate(board.updated_at);
        updated.textContent = updatedAt ? `Aggiornato ${updatedAt}` : '';

        const actions = document.createElement('div');
        actions.className = 'project-actions';

        const open = document.createElement('button');
        open.type = 'button';
        open.className = 'project-action open-project';
        open.dataset.openBoard = String(board.id);
        open.append(textNode('Apri Kanban '));
        open.append(textNode('->'));

        const sideActions = document.createElement('div');
        sideActions.className = 'project-side-actions';

        const archive = document.createElement('button');
        archive.type = 'button';
        archive.className = 'project-action project-button archive-project with-icon';
        archive.dataset.disabledWrite = 'true';
        archive.append(icon('archive'));
        archive.append(textNode(board.archived ? 'Ripristina' : 'Archivia'));

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'project-action project-button delete-project with-icon';
        remove.dataset.disabledWrite = 'true';
        remove.append(icon('trash-2'));
        remove.append(textNode('Elimina'));

        sideActions.append(archive, remove);
        actions.append(open, sideActions);
        card.append(edit, title, meta, updated, actions);
        elements.boards.append(card);
    });
}

function renderDashboard() {
    const workspace = activeWorkspace();
    showMessage(elements.dashboardMessage, '');

    if (!workspace) {
        elements.workspaceSelect.hidden = true;
        elements.folderPath.replaceChildren();
        elements.folders.replaceChildren();
        elements.boards.replaceChildren();
        elements.folderSection.hidden = true;
        const empty = document.createElement('div');
        empty.className = 'empty';
        empty.textContent = 'Non hai ancora workspace disponibili.';
        elements.boards.append(empty);
        refreshIcons();
        return;
    }

    renderWorkspaceOptions();
    renderFolderPath();
    renderFolders();
    renderBoards();

    elements.rootButton.hidden = !state.viewingArchived && state.currentFolderId === null;
    elements.archivedButton.hidden = state.viewingArchived && state.currentFolderId === null;
    elements.rootButton.classList.toggle('active', !state.viewingArchived);
    elements.archivedButton.classList.toggle('active', state.viewingArchived);
    elements.newFolderButton.hidden = state.viewingArchived;
    refreshIcons();
}

async function loadWorkspaceData() {
    if (!state.workspaceId) {
        state.folders = [];
        state.boards = [];
        renderDashboard();
        return;
    }

    const [foldersResponse, boardsResponse] = await Promise.all([
        request(`/api/workspaces/${state.workspaceId}/folders`),
        request(`/api/workspaces/${state.workspaceId}/boards`),
    ]);

    state.folders = (foldersResponse.data ?? []).map(normalizeFolder);
    state.boards = (boardsResponse.data ?? []).map(normalizeBoard);

    if (state.currentFolderId && !state.folders.some((folder) => folder.id === state.currentFolderId)) {
        state.currentFolderId = null;
    }

    renderDashboard();
}

async function loadAuthenticatedUser() {
    const userResponse = await request('/api/user');
    state.user = userResponse.data ?? userResponse;

    const workspaceResponse = await request('/api/workspaces');
    state.workspaces = workspaceResponse.data ?? [];

    const storedWorkspace = Number(localStorage.getItem(STORAGE_WORKSPACE_KEY));
    const fallbackWorkspace = state.workspaces[0]?.id ?? null;
    state.workspaceId = state.workspaces.some((workspace) => Number(workspace.id) === storedWorkspace)
        ? storedWorkspace
        : fallbackWorkspace;

    await loadWorkspaceData();
    setAuthenticatedView();
}

function openProjectModal(board = null) {
    elements.projectModalTitle.textContent = board ? 'Modifica progetto' : 'Nuovo progetto';
    elements.projectModalNote.hidden = true;
    elements.projectModalNote.textContent = '';
    elements.projectName.value = board?.name ?? '';
    elements.projectDescription.value = board?.description ?? '';
    setProjectColor(board?.color ?? DEFAULT_PROJECT_COLOR);
    elements.projectModal.hidden = false;
    elements.projectModal.classList.add('open');
    elements.projectName.focus();
    refreshIcons();
}

function openFolderModal(folder = null) {
    elements.folderModalTitle.textContent = folder ? 'Modifica cartella' : 'Nuova cartella';
    elements.folderModalNote.hidden = true;
    elements.folderModalNote.textContent = '';
    elements.folderName.value = folder?.name ?? '';
    setFolderColor(folder?.color ?? DEFAULT_FOLDER_COLOR);
    elements.folderModal.hidden = false;
    elements.folderModal.classList.add('open');
    elements.folderName.focus();
    refreshIcons();
}

function closeModals() {
    [elements.projectModal, elements.folderModal].forEach((modal) => {
        modal.hidden = true;
        modal.classList.remove('open');
    });
}

function syncPreset(select, value) {
    const match = Array.from(select.options).find((option) => option.value.toLowerCase() === value.toLowerCase());
    select.value = match?.value ?? '';
}

function setProjectColor(value) {
    const color = /^#[0-9a-f]{6}$/i.test(value) ? value : DEFAULT_PROJECT_COLOR;
    elements.projectColor.value = color;
    elements.projectColorText.value = color;
    syncPreset(elements.projectColorPreset, color);
}

function setFolderColor(value) {
    const color = /^#[0-9a-f]{6}$/i.test(value) ? value : DEFAULT_FOLDER_COLOR;
    elements.folderColor.value = color;
    elements.folderColorText.value = color;
    syncPreset(elements.folderColorPreset, color);
}

function showReadOnlyWriteMessage(target, message) {
    target.textContent = message;
    target.hidden = false;
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

elements.workspaceSelect.addEventListener('change', async (event) => {
    state.workspaceId = Number(event.target.value);
    state.currentFolderId = null;
    state.viewingArchived = false;
    localStorage.setItem(STORAGE_WORKSPACE_KEY, String(state.workspaceId));
    await loadWorkspaceData();
});

elements.rootButton.addEventListener('click', () => {
    state.currentFolderId = null;
    state.viewingArchived = false;
    renderDashboard();
});

elements.archivedButton.addEventListener('click', () => {
    state.currentFolderId = null;
    state.viewingArchived = true;
    renderDashboard();
});

elements.newProjectButton.addEventListener('click', () => openProjectModal());
elements.newFolderButton.addEventListener('click', () => openFolderModal());

elements.projectForm.addEventListener('submit', (event) => {
    event.preventDefault();
    showReadOnlyWriteMessage(
        elements.projectModalNote,
        'La dashboard Laravel usa solo le API di lettura in questa fase.'
    );
});

elements.folderForm.addEventListener('submit', (event) => {
    event.preventDefault();
    showReadOnlyWriteMessage(
        elements.folderModalNote,
        'La dashboard Laravel usa solo le API di lettura in questa fase.'
    );
});

elements.projectColorPreset.addEventListener('change', (event) => {
    if (event.target.value) setProjectColor(event.target.value);
});
elements.projectColor.addEventListener('input', (event) => setProjectColor(event.target.value));
elements.projectColorText.addEventListener('input', (event) => {
    if (/^#[0-9a-f]{6}$/i.test(event.target.value)) setProjectColor(event.target.value);
});

elements.folderColorPreset.addEventListener('change', (event) => {
    if (event.target.value) setFolderColor(event.target.value);
});
elements.folderColor.addEventListener('input', (event) => setFolderColor(event.target.value));
elements.folderColorText.addEventListener('input', (event) => {
    if (/^#[0-9a-f]{6}$/i.test(event.target.value)) setFolderColor(event.target.value);
});

document.addEventListener('click', (event) => {
    if (event.target.closest('[data-close-modal]')) {
        closeModals();
        return;
    }

    const folderCard = event.target.closest('[data-folder]');
    if (folderCard && !event.target.closest('.folder-card-actions')) {
        const folder = state.folders.find((item) => item.id === Number(folderCard.dataset.folder));
        if (!folder) return;
        state.currentFolderId = folder.id;
        state.viewingArchived = folder.archived;
        renderDashboard();
        return;
    }

    const folderPath = event.target.closest('[data-folder-path]');
    if (folderPath) {
        const folder = state.folders.find((item) => item.id === Number(folderPath.dataset.folderPath));
        if (!folder) return;
        state.currentFolderId = folder.id;
        state.viewingArchived = folder.archived;
        renderDashboard();
        return;
    }

    if (event.target.closest('[data-folder-path-root]')) {
        state.currentFolderId = null;
        state.viewingArchived = false;
        renderDashboard();
        return;
    }

    if (event.target.closest('[data-archived-path]')) {
        state.currentFolderId = null;
        state.viewingArchived = true;
        renderDashboard();
        return;
    }

    const editFolder = event.target.closest('[data-edit-folder]');
    if (editFolder) {
        const folder = state.folders.find((item) => item.id === Number(editFolder.dataset.editFolder));
        if (folder) openFolderModal(folder);
        return;
    }

    const editProject = event.target.closest('[data-edit-project]');
    if (editProject) {
        const board = state.boards.find((item) => item.id === Number(editProject.dataset.editProject));
        if (board) openProjectModal(board);
        return;
    }

    if (event.target.closest('[data-open-board]')) {
        showMessage(elements.dashboardMessage, 'La schermata Kanban verra migrata nel prossimo step.', false);
        return;
    }

    if (event.target.closest('[data-disabled-write]')) {
        showMessage(elements.dashboardMessage, 'Azione non collegata: questa dashboard usa solo le API Laravel di lettura.', true);
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        closeModals();
        return;
    }

    if ((event.key === 'Enter' || event.key === ' ') && event.target.matches('[data-folder]')) {
        event.preventDefault();
        event.target.click();
    }
});

loadAuthenticatedUser()
    .catch(() => setGuestView())
    .finally(refreshIcons);
