import { subscribeToUserRealtime, subscribeToWorkspaceRealtime } from './realtime';
import { activityDayKey, formatActivity, formatActivityDate, formatActivityDay, formatActivityDetails } from './activity-log';

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
    draggedBoardId: null,
    draggedFolderId: null,
    initialRouteApplied: false,
    confirmResolve: null,
    userRealtimeCleanup: null,
    workspaceRealtimeCleanups: new Map(),
    invitations: [],
    internalNotifications: [],
    activities: [],
    activityHasMore: false,
    activityDayExpandedByUser: false,
    activityPage: 1,
    activityOpenDay: null,
    activityStandalone: false,
    dashboardMessageTimeout: null,
};

const elements = {
    auth: document.querySelector('[data-auth]'),
    dashboard: document.querySelector('[data-dashboard]'),
    registerForm: document.querySelector('[data-register-form]'),
    loginForm: document.querySelector('[data-login-form]'),
    logoutButton: document.querySelector('[data-logout]'),
    accountButton: document.querySelector('[data-account]'),
    accountModal: document.querySelector('[data-account-modal]'),
    profileForm: document.querySelector('[data-profile-form]'),
    accountName: document.querySelector('[data-account-name]'),
    accountLastName: document.querySelector('[data-account-last-name]'),
    accountUsername: document.querySelector('[data-account-username]'),
    accountEmail: document.querySelector('[data-account-email]'),
    passwordForm: document.querySelector('[data-password-form]'),
    currentPassword: document.querySelector('[data-current-password]'),
    newPassword: document.querySelector('[data-new-password]'),
    confirmPassword: document.querySelector('[data-confirm-password]'),
    accountMessage: document.querySelector('[data-account-message]'),
    authMessage: document.querySelector('[data-auth-message]'),
    dashboardMessage: document.querySelector('[data-dashboard-message]'),
    dashboardMessageText: document.querySelector('[data-dashboard-message-text]'),
    closeDashboardMessage: document.querySelector('[data-close-dashboard-message]'),
    workspaceSelect: document.querySelector('[data-workspace-select]'),
    newWorkspaceButton: document.querySelector('[data-new-workspace]'),
    newWorkspaceModal: document.querySelector('[data-new-workspace-modal]'),
    newWorkspaceForm: document.querySelector('[data-new-workspace-form]'),
    newWorkspaceName: document.querySelector('[data-new-workspace-name]'),
    notificationsButton: document.querySelector('[data-notifications]'),
    notificationCount: document.querySelector('[data-notification-count]'),
    notificationsPanel: document.querySelector('[data-notifications-panel]'),
    notificationsList: document.querySelector('[data-notifications-list]'),
    manageWorkspaceButton: document.querySelector('[data-manage-workspace]'),
    activityModal: document.querySelector('[data-activity-modal]'),
    openActivityButton: document.querySelector('[data-open-activity]'),
    activityList: document.querySelector('[data-workspace-history-content] [data-activity-list]'),
    activityMore: document.querySelector('[data-workspace-history-content] [data-activity-more]'),
    standaloneActivityList: document.querySelector('[data-activity-modal] [data-activity-list]'),
    standaloneActivityMore: document.querySelector('[data-activity-modal] [data-activity-more]'),
    workspaceHistoryToggle: document.querySelector('[data-workspace-history-toggle]'),
    workspaceHistoryContent: document.querySelector('[data-workspace-history-content]'),
    workspaceModal: document.querySelector('[data-workspace-modal]'),
    workspaceDetailName: document.querySelector('[data-workspace-detail-name]'),
    workspaceDetailOwner: document.querySelector('[data-workspace-detail-owner]'),
    editWorkspaceButton: document.querySelector('[data-edit-workspace]'),
    workspaceNameForm: document.querySelector('[data-workspace-name-form]'),
    workspaceNameInput: document.querySelector('[data-workspace-name-input]'),
    cancelWorkspaceName: document.querySelector('[data-cancel-workspace-name]'),
    workspaceMembers: document.querySelector('[data-workspace-members]'),
    pendingInvitations: document.querySelector('[data-pending-invitations]'),
    inviteForm: document.querySelector('[data-invite-form]'),
    inviteEmail: document.querySelector('[data-invite-email]'),
    inviteRole: document.querySelector('[data-invite-role]'),
    leaveWorkspace: document.querySelector('[data-leave-workspace]'),
    deleteWorkspace: document.querySelector('[data-delete-workspace]'),
    folderPath: document.querySelector('[data-folder-path]'),
    folderSection: document.querySelector('[data-folder-section]'),
    folders: document.querySelector('[data-folders]'),
    boards: document.querySelector('[data-boards]'),
    rootButton: document.querySelector('[data-root]'),
    archivedButton: document.querySelector('[data-archived]'),
    newProjectButton: document.querySelector('[data-new-project]'),
    newFolderButton: document.querySelector('[data-new-folder]'),
    status: document.querySelector('[data-status]'),
    quickDropRoot: document.querySelector('[data-quick-drop="root"]'),
    quickDropArchive: document.querySelector('[data-quick-drop="archive"]'),
    quickDropZones: [...document.querySelectorAll('[data-quick-drop]')],
    projectModal: document.querySelector('[data-project-modal]'),
    projectForm: document.querySelector('[data-project-form]'),
    projectModalTitle: document.querySelector('[data-project-modal-title]'),
    projectModalNote: document.querySelector('[data-project-modal-note]'),
    projectName: document.querySelector('[data-project-name]'),
    projectColor: document.querySelector('[data-project-color]'),
    projectColorText: document.querySelector('[data-project-color-text]'),
    projectColorPreset: document.querySelector('[data-project-color-preset]'),
    projectSubmit: document.querySelector('[data-project-submit]'),
    folderModal: document.querySelector('[data-folder-modal]'),
    folderForm: document.querySelector('[data-folder-form]'),
    folderModalTitle: document.querySelector('[data-folder-modal-title]'),
    folderModalNote: document.querySelector('[data-folder-modal-note]'),
    folderName: document.querySelector('[data-folder-name]'),
    folderColor: document.querySelector('[data-folder-color]'),
    folderColorText: document.querySelector('[data-folder-color-text]'),
    folderColorPreset: document.querySelector('[data-folder-color-preset]'),
    confirmModal: document.querySelector('[data-confirm-modal]'),
    confirmMessage: document.querySelector('[data-confirm-message]'),
    confirmButtons: [...document.querySelectorAll('[data-confirm-result]')],
    closeConfirm: document.querySelector('[data-close-confirm]'),
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

function refreshIcons() {
    if (window.lucide?.createIcons) {
        window.lucide.createIcons();
    }
}

function showMessage(target, message = '', isError = true) {
    if (!target) return;
    if (target === elements.dashboardMessage && elements.dashboardMessageText) {
        elements.dashboardMessageText.textContent = message;
    } else {
        target.textContent = message;
    }
    target.hidden = !message;
    target.style.color = isError ? '#fca5a5' : '#86efac';
    if (target === elements.dashboardMessage) {
        window.clearTimeout(state.dashboardMessageTimeout);
        if (message) {
            state.dashboardMessageTimeout = window.setTimeout(() => showMessage(target, ''), 30000);
        }
    }
}

function closeDashboardMessage() {
    window.clearTimeout(state.dashboardMessageTimeout);
    state.dashboardMessageTimeout = null;
    showMessage(elements.dashboardMessage, '');
}

elements.closeDashboardMessage?.addEventListener('click', closeDashboardMessage);

function icon(name) {
    const node = document.createElement('i');
    node.className = 'icon';
    node.dataset.lucide = name;
    return node;
}

function normalizeColor(value, fallback = DEFAULT_FOLDER_COLOR) {
    return /^#[0-9a-f]{6}$/i.test(value ?? '') ? value : fallback;
}

function normalizeFolder(folder) {
    return {
        ...folder,
        id: Number(folder.id),
        parent_id: folder.parent_id === null || folder.parent_id === undefined ? null : Number(folder.parent_id),
        name: folder.name ?? 'Cartella',
        color: normalizeColor(folder.color, DEFAULT_FOLDER_COLOR),
        archived: Boolean(folder.archived),
    };
}

function normalizeBoard(board) {
    return {
        ...board,
        id: Number(board.id),
        folder_id: board.folder_id === null || board.folder_id === undefined ? null : Number(board.folder_id),
        name: board.name ?? 'Board',
        color: normalizeColor(board.color, DEFAULT_PROJECT_COLOR),
        archived: Boolean(board.archived),
        task_count: Number(board.tasks_count ?? board.task_count ?? 0),
    };
}

function normalizeWorkspace(workspace) {
    return {
        ...workspace,
        current_user_role: workspace.current_user_role ?? workspace.pivot?.role ?? null,
        id: Number(workspace.id),
        owner_id: Number(workspace.owner_id),
    };
}

function sameWorkspace(payload, workspaceId) {
    return Number(payload?.workspace_id) === Number(workspaceId);
}

function upsertWorkspace(workspace) {
    const normalized = normalizeWorkspace(workspace);
    state.workspaces = [
        ...state.workspaces.filter((item) => Number(item.id) !== normalized.id),
        normalized,
    ].sort((left, right) => left.name.localeCompare(right.name, 'it'));
    renderWorkspaceOptions();
}

function upsertFolder(folder) {
    const normalized = normalizeFolder(folder);
    state.folders = [
        ...state.folders.filter((item) => item.id !== normalized.id),
        normalized,
    ];
}

function upsertBoard(board) {
    const normalized = normalizeBoard(board);
    state.boards = [
        ...state.boards.filter((item) => item.id !== normalized.id),
        normalized,
    ];
}

function applyRemoteFolder(payload, archiveTree = false) {
    const folder = payload?.folder;
    if (!folder || !sameWorkspace(folder, state.workspaceId)) return;

    upsertFolder(folder);
    if (archiveTree) {
        const ids = folderTreeIds(folder.id);
        state.folders = state.folders.map((item) => (
            ids.includes(item.id) ? { ...item, archived: folder.archived } : item
        ));
        state.boards = state.boards.map((board) => (
            ids.includes(board.folder_id) ? { ...board, archived: folder.archived } : board
        ));
    }
    renderDashboard();
}

function applyRemoteFolderDeleted(payload) {
    if (!sameWorkspace(payload, state.workspaceId)) return;

    const folderId = Number(payload.folder_id ?? payload.folder?.id);
    const ids = folderTreeIds(folderId);
    state.folders = state.folders.filter((folder) => !ids.includes(folder.id));
    state.boards = state.boards.filter((board) => !ids.includes(board.folder_id));
    if (ids.includes(state.currentFolderId)) {
        state.currentFolderId = null;
        state.viewingArchived = false;
    }
    renderDashboard();
}

function applyRemoteBoard(payload, removed = false) {
    const board = payload?.board;
    if (removed) {
        if (!sameWorkspace(payload, state.workspaceId)) return;
        state.boards = state.boards.filter((item) => item.id !== Number(payload.board_id));
    } else {
        if (!board || !sameWorkspace(board, state.workspaceId)) return;
        upsertBoard(board);
    }
    renderDashboard();
}

function applyRemoteWorkspaceActivity(payload) {
    if (!sameWorkspace(payload, state.workspaceId) || !payload.activity) return;

    const activity = payload.activity;
    state.activities = [
        activity,
        ...state.activities.filter((item) => Number(item.id) !== Number(activity.id)),
    ];
    if (state.activityStandalone ? !elements.activityModal.hidden : (elements.workspaceHistoryContent && !elements.workspaceHistoryContent.hidden)) renderActivityList();
}

function handleRemoteWorkspaceMembership(payload) {
    if (!sameWorkspace(payload, state.workspaceId)) return;
    if (!elements.workspaceModal.hidden) {
        loadWorkspaceManagement().catch((error) => showMessage(elements.dashboardMessage, error.message));
    }
}

function handleRemoteWorkspaceUpdated(payload) {
    const workspace = payload?.workspace;
    if (!workspace) return;
    const current = state.workspaces.find((item) => Number(item.id) === Number(workspace.id));
    if (!current) return;
    const currentRole = current.current_user_role;
    Object.assign(current, normalizeWorkspace(workspace));
    current.current_user_role = currentRole;
    renderWorkspaceOptions();
    renderDashboard();
    if (!elements.workspaceModal.hidden && Number(state.workspaceId) === Number(workspace.id)) {
        elements.workspaceDetailName.textContent = current.name;
    }
}

function handleRemoteWorkspaceCreated(payload) {
    if (payload?.workspace) {
        upsertWorkspace(payload.workspace);
        syncRealtimeSubscriptions();
    }
}

function handleRemoteWorkspaceAvailable(payload) {
    handleRemoteWorkspaceCreated(payload);
    const workspace = payload?.workspace;
    if (workspace && !state.workspaceId) {
        state.workspaceId = Number(workspace.id);
        loadWorkspaceData().catch((error) => showMessage(elements.dashboardMessage, error.message));
    }
}

function handleRemoteWorkspaceAccessRemoved(payload) {
    const workspaceId = Number(payload?.workspace?.id ?? payload?.workspace_id);
    if (!workspaceId) return;

    const wasCurrent = Number(state.workspaceId) === workspaceId;
    state.workspaces = state.workspaces.filter((workspace) => Number(workspace.id) !== workspaceId);
    state.workspaceRealtimeCleanups.get(workspaceId)?.();
    state.workspaceRealtimeCleanups.delete(workspaceId);

    if (wasCurrent) {
        const personalWorkspace = state.workspaces.find((workspace) => workspace.type === 'personal');
        state.workspaceId = personalWorkspace?.id ?? state.workspaces[0]?.id ?? null;
        if (state.workspaceId) localStorage.setItem(STORAGE_WORKSPACE_KEY, String(state.workspaceId));
        state.currentFolderId = null;
        state.viewingArchived = false;
    }
    renderDashboard();
    if (wasCurrent) loadWorkspaceData().catch((error) => showMessage(elements.dashboardMessage, error.message));
}

function handleRemoteWorkspaceRoleUpdated(payload) {
    const workspace = payload?.workspace;
    if (!workspace || !state.user) return;
    const current = state.workspaces.find((item) => Number(item.id) === Number(workspace.id));
    if (!current) return;
    current.current_user_role = payload.role;
    renderWorkspaceOptions();
    renderDashboard();
    if (!elements.workspaceModal.hidden && Number(state.workspaceId) === Number(workspace.id)) {
        loadWorkspaceManagement().catch((error) => showMessage(elements.dashboardMessage, error.message));
    }
}

function handleRemoteWorkspaceDeleted(payload) {
    handleRemoteWorkspaceAccessRemoved(payload);
    showMessage(elements.dashboardMessage, 'Il workspace è stato eliminato dal proprietario. Non è più disponibile.', true);
}

function handleRemoteInvitationCreated(payload) {
    const invitation = payload?.invitation;
    if (!invitation) return;

    const workspace = {
        id: invitation.workspace_id,
        name: invitation.workspace_name,
        owner: { name: invitation.owner_name },
    };
    state.invitations = [
        normalizeInvitation({ ...invitation, workspace }),
        ...state.invitations.filter((item) => Number(item.id) !== Number(invitation.id)),
    ];
    renderNotifications();
}

function handleRemoteNotificationCreated(payload) {
    const notification = payload?.notification;
    if (!notification?.id) return;
    state.internalNotifications = [notification, ...state.internalNotifications.filter((item) => item.id !== notification.id)];
    renderNotifications();
}

function removeInvitation(invitationId) {
    state.invitations = state.invitations.filter((item) => Number(item.id) !== Number(invitationId));
    renderNotifications();
}

function handleRemoteInvitationOwnerChange(payload) {
    if (state.workspaceId !== Number(payload?.workspace_id)) return;
    if (!elements.workspaceModal.hidden) {
        loadWorkspaceManagement().catch((error) => showMessage(elements.dashboardMessage, error.message));
    }
}

function handleRemotePendingInvitationRemoved(payload) {
    handleRemoteInvitationOwnerChange(payload);
}

function syncRealtimeSubscriptions() {
    if (!state.user) return;

    if (!state.userRealtimeCleanup) {
        state.userRealtimeCleanup = subscribeToUserRealtime(state.user.id, {
            invitationCreated: handleRemoteInvitationCreated,
            notificationCreated: handleRemoteNotificationCreated,
            invitationAccepted: (payload) => removeInvitation(payload?.invitation_id),
            invitationRejected: (payload) => removeInvitation(payload?.invitation_id),
            workspaceCreated: handleRemoteWorkspaceCreated,
            workspaceAvailable: handleRemoteWorkspaceAvailable,
            workspaceAccessRemoved: handleRemoteWorkspaceAccessRemoved,
            workspaceDeleted: handleRemoteWorkspaceDeleted,
            workspaceRoleUpdated: handleRemoteWorkspaceRoleUpdated,
            pendingInvitationCreated: handleRemoteInvitationOwnerChange,
            pendingInvitationRemoved: handleRemotePendingInvitationRemoved,
            error: (error) => console.warn('Realtime utente non disponibile.', error),
        });
    }

    const workspaceIds = new Set(state.workspaces.map((workspace) => Number(workspace.id)));
    state.workspaceRealtimeCleanups.forEach((cleanup, workspaceId) => {
        if (!workspaceIds.has(Number(workspaceId))) {
            cleanup();
            state.workspaceRealtimeCleanups.delete(workspaceId);
        }
    });

    state.workspaces.forEach((workspace) => {
        const workspaceId = Number(workspace.id);
        if (state.workspaceRealtimeCleanups.has(workspaceId)) return;

        state.workspaceRealtimeCleanups.set(workspaceId, subscribeToWorkspaceRealtime(workspaceId, {
            folderCreated: (payload) => applyRemoteFolder(payload),
            folderUpdated: (payload) => applyRemoteFolder(payload),
            folderMoved: (payload) => applyRemoteFolder(payload, true),
            folderArchived: (payload) => applyRemoteFolder(payload, true),
            folderRestored: (payload) => applyRemoteFolder(payload, true),
            folderDeleted: applyRemoteFolderDeleted,
            boardCreated: (payload) => applyRemoteBoard(payload),
            boardUpdated: (payload) => applyRemoteBoard(payload),
            boardMoved: (payload) => applyRemoteBoard(payload),
            boardArchived: (payload) => applyRemoteBoard(payload),
            boardRestored: (payload) => applyRemoteBoard(payload),
            boardDeleted: (payload) => applyRemoteBoard(payload, true),
            memberJoined: handleRemoteWorkspaceMembership,
            memberRemoved: handleRemoteWorkspaceMembership,
            memberLeft: handleRemoteWorkspaceMembership,
            memberRoleUpdated: handleRemoteWorkspaceMembership,
            workspaceUpdated: handleRemoteWorkspaceUpdated,
            activityLogged: applyRemoteWorkspaceActivity,
            error: (error) => console.warn('Realtime workspace non disponibile.', error),
        }));
    });
}

function clearRealtimeSubscriptions() {
    state.userRealtimeCleanup?.();
    state.userRealtimeCleanup = null;
    state.workspaceRealtimeCleanups.forEach((cleanup) => cleanup());
    state.workspaceRealtimeCleanups.clear();
}

window.addEventListener('pagehide', clearRealtimeSubscriptions);

function activeWorkspace() {
    return state.workspaces.find((workspace) => Number(workspace.id) === Number(state.workspaceId)) ?? null;
}

function folderTreeIds(folderId) {
    if (!folderId) return [];

    const ids = [folderId];

    for (let index = 0; index < ids.length; index += 1) {
        state.folders
            .filter((folder) => folder.parent_id === ids[index])
            .forEach((folder) => ids.push(folder.id));
    }

    return ids;
}

function folderAncestors(folderId) {
    const path = [];
    const seen = new Set();
    let current = state.folders.find((folder) => folder.id === folderId) ?? null;

    while (current && !seen.has(current.id)) {
        if (state.viewingArchived && !current.archived) break;
        if (!state.viewingArchived && current.archived) break;
        path.unshift(current);
        seen.add(current.id);
        current = state.folders.find((folder) => folder.id === current.parent_id) ?? null;
    }

    return path;
}

function childrenOf(parentId) {
    if (state.viewingArchived && parentId === null) {
        return state.folders.filter((folder) => {
            if (!folder.archived) return false;
            const parent = state.folders.find((item) => item.id === folder.parent_id);
            return folder.parent_id === null || !parent?.archived;
        });
    }

    return state.folders.filter(
        (folder) => folder.parent_id === parentId && folder.archived === state.viewingArchived
    );
}

function boardCountForFolder(folderId) {
    const folderIds = folderTreeIds(folderId);

    return state.boards.filter(
        (board) => board.archived === state.viewingArchived && folderIds.includes(board.folder_id)
    ).length;
}

function visibleBoards() {
    if (state.viewingArchived && state.currentFolderId === null) {
        return state.boards.filter((board) => {
            if (!board.archived) return false;
            if (board.folder_id === null) return true;

            const folder = state.folders.find((item) => item.id === board.folder_id);
            return !folder || !folder.archived;
        });
    }

    return state.boards.filter(
        (board) => board.archived === state.viewingArchived && board.folder_id === state.currentFolderId
    );
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

function formatInvitationExpiry(value) {
    if (!value) return 'scadenza non disponibile';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return 'scadenza non disponibile';

    return `scade il ${new Intl.DateTimeFormat('it-IT', {
        day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
    }).format(date)}`;
}

function normalizeInvitation(invitation) {
    return {
        ...invitation,
        id: Number(invitation.id),
        workspace: invitation.workspace ? {
            ...invitation.workspace,
            id: Number(invitation.workspace.id),
        } : null,
    };
}

function boardUrl(board) {
    const params = new URLSearchParams();

    if (state.workspaceId) {
        params.set('workspace_id', String(state.workspaceId));
    }

    if (board.folder_id !== null && board.folder_id !== undefined) {
        params.set('return_folder', String(board.folder_id));
    }

    if (board.archived) {
        params.set('return_archived', '1');
    }

    const query = params.toString();

    return `/boards/${encodeURIComponent(board.id)}${query ? `?${query}` : ''}`;
}

function renderWorkspaceOptions() {
    elements.workspaceSelect.replaceChildren();

    state.workspaces.forEach((workspace) => {
        const option = document.createElement('option');
        option.value = workspace.id;
        option.textContent = `${workspace.name} (${workspace.type === 'shared' ? 'condiviso' : 'personale'})`;
        option.selected = Number(workspace.id) === Number(state.workspaceId);
        elements.workspaceSelect.append(option);
    });

    elements.workspaceSelect.hidden = state.workspaces.length <= 1;
    const workspace = activeWorkspace();
    document.body.classList.toggle('viewer-mode', workspace?.current_user_role === 'viewer');
    elements.manageWorkspaceButton.hidden = workspace?.type !== 'shared';
    elements.openActivityButton.hidden = workspace?.type !== 'personal';
    const viewer = workspace?.current_user_role === 'viewer';
    elements.newProjectButton.hidden = viewer || state.viewingArchived;
    elements.newFolderButton.hidden = viewer || state.viewingArchived;
}

function renderFolderPath() {
    elements.folderPath.replaceChildren();

    const root = document.createElement('button');
    root.type = 'button';
    root.textContent = state.viewingArchived ? 'Progetti archiviati' : 'Progetti';
    root.dataset.folderPathRoot = state.viewingArchived ? 'archive' : 'root';
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
    const visibleFolders = childrenOf(state.currentFolderId);
    elements.folders.replaceChildren();
    elements.folderSection.classList.toggle('is-hidden', visibleFolders.length === 0);
    elements.folderSection.hidden = visibleFolders.length === 0;

    visibleFolders.forEach((folder) => {
        const card = document.createElement('article');
        card.className = 'folder-card';
        card.draggable = true;
        card.role = 'button';
        card.tabIndex = 0;
        card.dataset.folder = String(folder.id);
        card.dataset.folderDrag = String(folder.id);
        card.dataset.folderDrop = String(folder.id);
        card.style.setProperty('--folder-color', folder.color);

        const dot = document.createElement('span');
        dot.className = 'folder-dot';

        const name = document.createElement('span');
        name.className = 'folder-card-name';
        name.textContent = folder.name;

        const count = document.createElement('span');
        count.className = 'folder-count';
        count.textContent = `${boardCountForFolder(folder.id)} Kan`;

        const actions = document.createElement('span');
        actions.className = 'folder-card-actions';

        const edit = document.createElement('button');
        edit.type = 'button';
        edit.title = 'Modifica';
        edit.ariaLabel = 'Modifica cartella';
        edit.dataset.editFolder = String(folder.id);
        edit.append(icon('pencil'));

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.title = 'Elimina';
        remove.ariaLabel = 'Elimina cartella';
        remove.dataset.deleteFolder = String(folder.id);
        remove.append(icon('trash-2'));

        actions.append(edit, remove);
        card.append(dot, name, count, actions);
        elements.folders.append(card);
    });

    attachFolderDragEvents();
}

function renderBoards() {
    const visible = visibleBoards();
    elements.boards.replaceChildren();

    if (visible.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'empty';
        empty.textContent = state.viewingArchived
            ? 'Nessun progetto archiviato.'
            : 'Nessun progetto in questa cartella.';
        elements.boards.append(empty);
    } else {
        visible.forEach((board) => {
            const card = document.createElement('article');
            card.className = `project-card${board.archived ? ' archived-card' : ''}`;
            card.draggable = true;
            card.dataset.boardDrag = String(board.id);
            card.style.setProperty('--project-color', board.color);

            const edit = document.createElement('button');
            edit.className = 'project-action project-button edit-project with-icon';
            edit.type = 'button';
            edit.title = 'Modifica progetto';
            edit.dataset.editProject = String(board.id);
            edit.append(icon('pencil'));

            const content = document.createElement('div');
            const title = document.createElement('h2');
            title.className = 'project-name';
            title.textContent = board.name;

            const meta = document.createElement('div');
            meta.className = 'project-meta';
            meta.append(
                document.createTextNode(`${board.task_count} ${board.task_count === 1 ? 'evento' : 'eventi'}`),
                document.createElement('br'),
                document.createTextNode(`Aggiornato ${formatDate(board.updated_at)}`)
            );
            content.append(title, meta);

            const actions = document.createElement('div');
            actions.className = 'project-actions';

            const open = document.createElement('a');
            open.className = 'project-action open-project with-icon';
            open.href = boardUrl(board);
            open.dataset.openBoard = String(board.id);
            open.append(document.createTextNode('Apri Kanban'), icon('arrow-right'));

            const sideActions = document.createElement('div');
            sideActions.className = 'project-side-actions';

            const archive = document.createElement('button');
            archive.className = 'project-action project-button archive-project with-icon';
            archive.type = 'button';
            archive.dataset.archiveProject = String(board.id);
            archive.append(icon(board.archived ? 'rotate-ccw' : 'archive'));
            archive.append(document.createTextNode(board.archived ? 'Ripristina' : 'Archivia'));

            const remove = document.createElement('button');
            remove.className = 'project-action project-button delete-project with-icon';
            remove.type = 'button';
            remove.dataset.deleteBoard = String(board.id);
            remove.append(icon('trash-2'), document.createTextNode('Elimina'));

            sideActions.append(archive, remove);
            actions.append(open, sideActions);
            card.append(edit, content, actions);
            elements.boards.append(card);
        });
    }

    attachProjectDragEvents();
}

function syncQuickDropVisibility() {
    const inPrincipal = !state.viewingArchived && state.currentFolderId === null;
    const inArchive = state.viewingArchived;
    const showRoot = !inPrincipal;
    const showArchive = !inArchive;

    elements.quickDropRoot.classList.toggle('is-hidden', !showRoot);
    elements.quickDropArchive.classList.toggle('is-hidden', !showArchive);
    elements.quickDropRoot.parentElement.classList.toggle('only-root', showRoot && !showArchive);
    elements.quickDropRoot.parentElement.classList.toggle('only-archive', showArchive && !showRoot);
}

function renderDashboard() {
    const workspace = activeWorkspace();

    if (!workspace) {
        elements.workspaceSelect.hidden = true;
        elements.folderPath.replaceChildren();
        elements.folders.replaceChildren();
        elements.folderSection.classList.add('is-hidden');
        elements.folderSection.hidden = true;
        elements.boards.replaceChildren();

        const empty = document.createElement('div');
        empty.className = 'empty';
        empty.textContent = 'Non hai ancora workspace disponibili.';
        elements.boards.append(empty);
        elements.status.textContent = '0 progetti';
        refreshIcons();
        return;
    }

    renderWorkspaceOptions();
    renderFolderPath();
    renderFolders();
    renderBoards();

    const visible = visibleBoards();
    elements.rootButton.hidden = !state.viewingArchived && state.currentFolderId === null;
    elements.archivedButton.hidden = state.viewingArchived;
    elements.rootButton.classList.toggle('active', !state.viewingArchived && state.currentFolderId === null);
    elements.archivedButton.classList.toggle('active', state.viewingArchived);
    elements.newFolderButton.hidden = state.viewingArchived || workspace.current_user_role === 'viewer';
    elements.status.textContent = `${visible.length} ${visible.length === 1 ? 'progetto' : 'progetti'}`;
    syncQuickDropVisibility();
    refreshIcons();
}

function applyInitialRoute() {
    if (state.initialRouteApplied) return;
    state.initialRouteApplied = true;

    const params = new URLSearchParams(window.location.search);
    const folderId = params.has('return_folder') ? Number(params.get('return_folder')) : null;
    const archived = params.get('return_archived') === '1';

    if (folderId) {
        const folder = state.folders.find((item) => item.id === folderId);
        if (folder) {
            state.currentFolderId = folder.id;
            state.viewingArchived = Boolean(folder.archived);
            return;
        }
    }

    state.viewingArchived = archived;
}

async function loadWorkspaceData() {
    if (!state.workspaceId) {
        state.folders = [];
        state.boards = [];
        renderDashboard();
        return;
    }

    elements.status.textContent = 'Caricamento progetti...';

    const [foldersResponse, boardsResponse] = await Promise.all([
        request(`/api/workspaces/${state.workspaceId}/folders`),
        request(`/api/workspaces/${state.workspaceId}/boards`),
    ]);

    state.folders = (foldersResponse.data ?? []).map(normalizeFolder);
    state.boards = (boardsResponse.data ?? []).map(normalizeBoard);
    applyInitialRoute();

    if (state.currentFolderId && !state.folders.some((folder) => folder.id === state.currentFolderId)) {
        state.currentFolderId = null;
        state.viewingArchived = false;
    }

    renderDashboard();
}

async function loadAuthenticatedUser() {
    const userResponse = await request('/api/user');
    state.user = userResponse.data ?? userResponse;

    const workspaceResponse = await request('/api/workspaces');
    state.workspaces = (workspaceResponse.data ?? []).map(normalizeWorkspace);

    const storedWorkspace = Number(localStorage.getItem(STORAGE_WORKSPACE_KEY));
    const fallbackWorkspace = state.workspaces[0]?.id ?? null;
    state.workspaceId = state.workspaces.some((workspace) => Number(workspace.id) === storedWorkspace)
        ? storedWorkspace
        : fallbackWorkspace;

    elements.auth.hidden = true;
    elements.dashboard.hidden = false;
    syncRealtimeSubscriptions();
    await loadWorkspaceData();
    await loadInvitations();
}

async function loadWorkspaces() {
    const response = await request('/api/workspaces');
    state.workspaces = (response.data ?? []).map(normalizeWorkspace);

    if (!state.workspaces.some((workspace) => Number(workspace.id) === Number(state.workspaceId))) {
        state.workspaceId = state.workspaces[0]?.id ?? null;
    }

    syncRealtimeSubscriptions();
    await loadWorkspaceData();
}

function setGuestView() {
    clearRealtimeSubscriptions();
    state.user = null;
    state.workspaces = [];
    state.workspaceId = null;
    state.folders = [];
    state.boards = [];
    state.invitations = [];
    state.activities = [];
    state.currentFolderId = null;
    state.viewingArchived = false;
    elements.dashboard.hidden = true;
    elements.auth.hidden = false;
    elements.loginForm.reset();
    elements.registerForm.reset();
    refreshIcons();
}

function setSubmitButton(button, iconName, label) {
    button.replaceChildren(icon(iconName), document.createElement('span'));
    button.lastElementChild.textContent = label;
}

function openProjectModal(board = null) {
    elements.projectForm.dataset.boardId = board?.id ?? '';
    elements.projectModalTitle.textContent = board ? 'Modifica progetto' : 'Nuovo progetto';
    elements.projectModalNote.hidden = true;
    elements.projectModalNote.textContent = '';
    elements.projectName.value = board?.name ?? '';
    setProjectColor(board?.color ?? DEFAULT_PROJECT_COLOR);
    setSubmitButton(elements.projectSubmit, board ? 'save' : 'plus', board ? 'Salva progetto' : 'Crea progetto');
    elements.projectModal.hidden = false;
    elements.projectModal.classList.add('open');
    setTimeout(() => elements.projectName.focus(), 30);
    refreshIcons();
}

function closeProjectModal() {
    elements.projectModal.classList.remove('open');
    elements.projectModal.hidden = true;
    elements.projectForm.reset();
    elements.projectForm.dataset.boardId = '';
    setProjectColor(DEFAULT_PROJECT_COLOR);
    setSubmitButton(elements.projectSubmit, 'plus', 'Crea progetto');
    refreshIcons();
}

function openFolderModal(folder = null) {
    elements.folderForm.dataset.folderId = folder?.id ?? '';
    elements.folderModalTitle.textContent = folder ? 'Modifica cartella' : 'Nuova cartella';
    elements.folderModalNote.hidden = true;
    elements.folderModalNote.textContent = '';
    elements.folderName.value = folder?.name ?? '';
    setFolderColor(folder?.color ?? DEFAULT_FOLDER_COLOR);
    elements.folderModal.hidden = false;
    elements.folderModal.classList.add('open');
    setTimeout(() => elements.folderName.focus(), 30);
    refreshIcons();
}

function closeFolderModal() {
    elements.folderModal.classList.remove('open');
    elements.folderModal.hidden = true;
    elements.folderForm.reset();
    elements.folderForm.dataset.folderId = '';
    setFolderColor(DEFAULT_FOLDER_COLOR);
}

function closeModals() {
    closeProjectModal();
    closeFolderModal();
    elements.workspaceModal.classList.remove('open');
    elements.workspaceModal.hidden = true;
    elements.activityModal.classList.remove('open');
    elements.activityModal.hidden = true;
    closeWorkspaceHistory();
    [elements.newWorkspaceModal].forEach((modal) => {
        modal.classList.remove('open');
        modal.hidden = true;
    });
    elements.accountModal.classList.remove('open');
    elements.accountModal.hidden = true;
}

function openWorkspaceModal() {
    elements.workspaceModal.hidden = false;
    elements.workspaceModal.classList.add('open');
    state.activityStandalone = false;
    closeWorkspaceHistory();
    refreshIcons();
}

function closeWorkspaceHistory() {
    if (!elements.workspaceHistoryContent || !elements.workspaceHistoryToggle) return;
    elements.workspaceHistoryContent.hidden = true;
    elements.workspaceHistoryToggle.setAttribute('aria-expanded', 'false');
}

async function openWorkspaceHistory() {
    if (!elements.workspaceHistoryContent || !elements.workspaceHistoryToggle) return;
    elements.workspaceHistoryContent.hidden = false;
    elements.workspaceHistoryToggle.setAttribute('aria-expanded', 'true');
    activityPage = 1;
    state.activityOpenDay = activityDayKey(new Date());
    state.activityDayExpandedByUser = false;
    await loadActivity();
    elements.workspaceHistoryContent.scrollIntoView({ block: 'nearest' });
}

function confirmDialog(message, actions = ['delete']) {
    const actionSet = new Set(actions);
    elements.confirmMessage.textContent = message;
    elements.confirmButtons.forEach((button) => {
        const result = button.dataset.confirmResult;
        button.classList.toggle('is-hidden', result !== 'cancel' && !actionSet.has(result));
    });
    elements.confirmModal.hidden = false;
    elements.confirmModal.classList.add('open');
    refreshIcons();

    return new Promise((resolve) => {
        state.confirmResolve = resolve;
    });
}

function closeConfirmModal(result = 'cancel') {
    elements.confirmModal.classList.remove('open');
    elements.confirmModal.hidden = true;

    if (state.confirmResolve) {
        state.confirmResolve(result);
        state.confirmResolve = null;
    }
}

function syncPreset(select, value) {
    const normalized = value.toLowerCase();
    const match = [...select.options].find(
        (option) => option.value && option.value.toLowerCase() === normalized
    );
    select.value = match ? match.value : '';
}

function setProjectColor(value) {
    const color = normalizeColor(value, DEFAULT_PROJECT_COLOR);
    elements.projectColor.value = color;
    elements.projectColorText.value = color;
    syncPreset(elements.projectColorPreset, color);
}

function setFolderColor(value) {
    const color = normalizeColor(value, DEFAULT_FOLDER_COLOR);
    elements.folderColor.value = color;
    elements.folderColorText.value = color;
    syncPreset(elements.folderColorPreset, color);
}

async function saveProject() {
    const name = elements.projectName.value.trim();
    const color = normalizeColor(elements.projectColorText.value, elements.projectColor.value);
    const board = state.boards.find((item) => item.id === Number(elements.projectForm.dataset.boardId));
    if (!name) return;

    if (board) {
        await request(`/api/boards/${board.id}`, {
            method: 'PATCH',
            body: JSON.stringify({ name, color, description: board.description ?? null }),
        });
        return;
    }

    await request(`/api/workspaces/${state.workspaceId}/boards`, {
        method: 'POST',
        body: JSON.stringify({ name, color, folder_id: state.viewingArchived ? null : state.currentFolderId }),
    });
}

async function saveFolder() {
    const name = elements.folderName.value.trim();
    const color = normalizeColor(elements.folderColorText.value, elements.folderColor.value);
    const folder = state.folders.find((item) => item.id === Number(elements.folderForm.dataset.folderId));
    if (!name) return;

    if (folder) {
        await request(`/api/folders/${folder.id}`, {
            method: 'PATCH',
            body: JSON.stringify({ name, color }),
        });
        return;
    }

    await request(`/api/workspaces/${state.workspaceId}/folders`, {
        method: 'POST',
        body: JSON.stringify({ name, color, parent_id: state.currentFolderId }),
    });
}

async function moveBoardTo(boardId, folderId, archived) {
    await request(`/api/boards/${boardId}/move`, {
        method: 'POST',
        body: JSON.stringify({ folder_id: folderId, archived }),
    });
    await loadWorkspaceData();
}

async function archiveFolderTo(folderId, archived) {
    await request(`/api/folders/${folderId}/archive`, {
        method: 'POST',
        body: JSON.stringify({ archived }),
    });
    await loadWorkspaceData();
}

async function moveFolderTo(folderId, parentId) {
    await request(`/api/folders/${folderId}/move`, {
        method: 'POST',
        body: JSON.stringify({ parent_id: parentId }),
    });
    await loadWorkspaceData();
}

function clearDropTargets() {
    document.querySelectorAll('.drop-target').forEach((target) => target.classList.remove('drop-target'));
}

function resetDraggingState() {
    state.draggedBoardId = null;
    state.draggedFolderId = null;
    document.body.classList.remove('dragging-board');
    clearDropTargets();
    elements.boards.querySelectorAll('.dragging').forEach((card) => card.classList.remove('dragging'));
    elements.folders.querySelectorAll('.dragging').forEach((card) => card.classList.remove('dragging'));
}

function attachFolderDragEvents() {
    elements.folders.querySelectorAll('[data-folder-drop]').forEach((card) => {
        card.addEventListener('dragover', (event) => {
            if (!state.draggedBoardId && !state.draggedFolderId) return;
            const targetFolderId = Number(card.dataset.folderDrop);
            if (state.draggedFolderId && folderTreeIds(state.draggedFolderId).includes(targetFolderId)) return;
            event.preventDefault();
            card.classList.add('drop-target');
        });
        card.addEventListener('dragleave', () => card.classList.remove('drop-target'));
        card.addEventListener('drop', async (event) => {
            event.preventDefault();
            const targetFolderId = Number(card.dataset.folderDrop);
            const boardId = state.draggedBoardId;
            const folderId = state.draggedFolderId;
            resetDraggingState();
            if (!boardId && !folderId) return;

            try {
                if (folderId) {
                    await moveFolderTo(folderId, targetFolderId);
                    return;
                }
                await moveBoardTo(boardId, targetFolderId, state.viewingArchived);
            } catch (error) {
                showMessage(elements.dashboardMessage, `Non riesco a spostare l'elemento: ${error.message}`);
            }
        });
    });

    elements.folders.querySelectorAll('[data-folder-drag]').forEach((card) => {
        card.addEventListener('dragstart', (event) => {
            state.draggedFolderId = Number(card.dataset.folderDrag);
            card.classList.add('dragging');
            document.body.classList.add('dragging-board');
            event.dataTransfer?.setData('text/plain', `folder:${state.draggedFolderId}`);
            if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move';
        });
        card.addEventListener('dragend', resetDraggingState);
    });
}

function attachProjectDragEvents() {
    elements.boards.querySelectorAll('[data-board-drag]').forEach((card) => {
        card.addEventListener('dragstart', (event) => {
            state.draggedBoardId = Number(card.dataset.boardDrag);
            card.classList.add('dragging');
            document.body.classList.add('dragging-board');
            event.dataTransfer?.setData('text/plain', String(state.draggedBoardId));
            if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move';
        });
        card.addEventListener('dragend', resetDraggingState);
    });
}

function attachQuickDropEvents() {
    elements.quickDropZones.forEach((zone) => {
        zone.addEventListener('dragover', (event) => {
            if (!state.draggedBoardId && !state.draggedFolderId) return;
            event.preventDefault();
            zone.classList.add('drop-target');
        });
        zone.addEventListener('dragleave', () => zone.classList.remove('drop-target'));
        zone.addEventListener('drop', async (event) => {
            event.preventDefault();
            const action = zone.dataset.quickDrop;
            const boardId = state.draggedBoardId;
            const folderId = state.draggedFolderId;
            resetDraggingState();
            if (!boardId && !folderId) return;

            try {
                if (folderId) {
                    state.currentFolderId = null;
                    state.viewingArchived = action === 'archive';
                    await archiveFolderTo(folderId, action === 'archive');
                    return;
                }

                await moveBoardTo(boardId, null, action === 'archive');
            } catch (error) {
                showMessage(elements.dashboardMessage, `Non riesco a spostare il progetto: ${error.message}`);
            }
        });
    });
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

elements.accountButton.addEventListener('click', () => {
    elements.accountName.value = state.user?.name ?? '';
    elements.accountLastName.value = state.user?.last_name ?? '';
    elements.accountUsername.value = state.user?.username ?? '';
    elements.accountEmail.value = state.user?.email ?? '';
    elements.accountMessage.hidden = true;
    elements.accountModal.hidden = false;
    elements.accountModal.classList.add('open');
});

elements.profileForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    try {
        const response = await request('/api/account/profile', { method: 'PATCH', body: JSON.stringify({ name: elements.accountName.value.trim(), last_name: elements.accountLastName.value.trim(), username: elements.accountUsername.value.trim() }) });
        state.user = response.data;
        elements.accountMessage.textContent = 'Profilo aggiornato.';
        elements.accountMessage.hidden = false;
    } catch (error) { elements.accountMessage.textContent = error.message; elements.accountMessage.hidden = false; }
});

elements.passwordForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    try {
        await request('/api/account/password', { method: 'PATCH', body: JSON.stringify({ current_password: elements.currentPassword.value, password: elements.newPassword.value, password_confirmation: elements.confirmPassword.value }) });
        elements.passwordForm.reset();
        elements.accountMessage.textContent = 'Password aggiornata.';
        elements.accountMessage.hidden = false;
    } catch (error) { elements.accountMessage.textContent = error.message; elements.accountMessage.hidden = false; }
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
elements.newWorkspaceButton.addEventListener('click', async () => {
    elements.newWorkspaceModal.hidden = false;
    elements.newWorkspaceModal.classList.add('open');
    elements.newWorkspaceName.focus();
});

elements.newWorkspaceForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    try { await request('/api/workspaces', { method: 'POST', body: JSON.stringify({ name: elements.newWorkspaceName.value.trim() }) }); closeModals(); await loadWorkspaces(); }
    catch (error) { showMessage(elements.dashboardMessage, error.message); }
});


async function loadWorkspaceManagement() {
    const workspace = activeWorkspace();
    if (!workspace) return;
    elements.workspaceDetailName.textContent = workspace.name;
    elements.editWorkspaceButton.hidden = Number(workspace.owner_id) !== Number(state.user?.id);
    elements.workspaceDetailOwner.textContent = `Owner: ${workspace.owner?.name ?? workspace.owner?.email ?? '—'}`;
    elements.deleteWorkspace.hidden = workspace.type !== 'shared' || Number(workspace.owner_id) !== Number(state.user?.id);
    elements.leaveWorkspace.hidden = Number(workspace.owner_id) === Number(state.user?.id);
    let currentUserRole = workspace.current_user_role;
    const canManageMembers = ['owner', 'admin'].includes(currentUserRole);
    elements.inviteForm.closest('.workspace-panel').hidden = !canManageMembers;
    elements.pendingInvitations.closest('.workspace-panel').hidden = !canManageMembers;
    elements.inviteForm.hidden = !canManageMembers;
    const adminOption = elements.inviteRole?.querySelector('option[value="admin"]');
    if (adminOption) adminOption.hidden = workspace.current_user_role !== 'owner';
    const invitationsRequest = canManageMembers
        ? request(`/api/workspaces/${workspace.id}/invitations`)
        : Promise.resolve({ data: [] });
    const [members, invitations] = await Promise.all([
        request(`/api/workspaces/${workspace.id}/members`),
        invitationsRequest,
    ]);
    const currentMember = (members.data ?? []).find((member) => Number(member.id) === Number(state.user?.id));
    currentUserRole = Number(workspace.owner_id) === Number(state.user?.id)
        ? 'owner'
        : currentMember?.pivot?.role ?? currentUserRole;
    const canManageMembersAfterLoad = ['owner', 'admin'].includes(currentUserRole);
    elements.inviteForm.closest('.workspace-panel').hidden = !canManageMembersAfterLoad;
    elements.pendingInvitations.closest('.workspace-panel').hidden = !canManageMembersAfterLoad;
    elements.inviteForm.hidden = !canManageMembersAfterLoad;
    if (adminOption) adminOption.hidden = currentUserRole !== 'owner';
    elements.workspaceMembers.replaceChildren();
    (members.data ?? []).forEach((member) => {
        const row = document.createElement('div');
        row.className = 'workspace-member-row';
        const role = member.pivot?.role ?? 'member';
        const roleLabels = { owner: 'Proprietario', admin: 'Amministratore', member: 'Membro', viewer: 'Visualizzatore' };
        const name = document.createElement('span');
        name.className = 'workspace-member-name';
        name.textContent = member.name;
        row.append(name);
        const controls = document.createElement('div');
        controls.className = 'workspace-member-controls';
        const canRemoveMember = currentUserRole === 'owner'
            || (currentUserRole === 'admin' && ['member', 'viewer'].includes(role));
        if (Number(member.id) !== Number(workspace.owner_id)
            && Number(member.id) !== Number(state.user?.id)
            && canRemoveMember) {
            const remove = document.createElement('button');
            remove.className = 'btn btn-danger'; remove.textContent = 'Rimuovi';
            remove.onclick = async () => { await request(`/api/workspaces/${workspace.id}/members/${member.id}`, { method: 'DELETE' }); await loadWorkspaceManagement(); };
            controls.append(remove);
            if (currentUserRole === 'owner' || ['member', 'viewer'].includes(role)) {
                const select = document.createElement('select');
                [['admin', 'Amministratore'], ['member', 'Membro'], ['viewer', 'Visualizzatore']].forEach(([value, label]) => { const option = document.createElement('option'); option.value = value; option.textContent = label; option.selected = role === value; option.hidden = value === 'admin' && currentUserRole !== 'owner'; select.append(option); });
                select.onchange = async () => { await request(`/api/workspaces/${workspace.id}/members/${member.id}/role`, { method: 'PATCH', body: JSON.stringify({ role: select.value }) }); await loadWorkspaceManagement(); };
                controls.append(select);
            }
        }
        if (!controls.querySelector('select')) controls.classList.add('workspace-member-controls-remove-only');
        if (controls.childElementCount) row.append(controls);
        elements.workspaceMembers.append(row);
    });
    elements.pendingInvitations.replaceChildren();
    const pending = invitations.data ?? [];
    if (!pending.length) {
        elements.pendingInvitations.textContent = 'Nessun invito pendente.';
    } else {
        pending.forEach((item) => {
            const invitation = document.createElement('div');
            invitation.className = 'workspace-invitation';
            invitation.textContent = `${item.email} — ${formatInvitationExpiry(item.expires_at)}`;
            const remove = document.createElement('button');
            remove.className = 'workspace-invitation-remove';
            remove.type = 'button';
            remove.title = 'Cancella invito';
            remove.setAttribute('aria-label', `Cancella invito per ${item.email}`);
            remove.append(icon('trash-2'));
            remove.onclick = async () => {
                remove.disabled = true;
                try {
                    await request(`/api/invitations/${item.id}`, { method: 'DELETE' });
                    await loadWorkspaceManagement();
                } catch (error) {
                    remove.disabled = false;
                    showMessage(elements.dashboardMessage, error.message);
                }
            };
            invitation.append(remove);
            elements.pendingInvitations.append(invitation);
        });
    }
    refreshIcons();
}

elements.manageWorkspaceButton.addEventListener('click', async () => {
    openWorkspaceModal();
    try { await loadWorkspaceManagement(); } catch (error) { showMessage(elements.dashboardMessage, error.message); }
});

elements.editWorkspaceButton.addEventListener('click', () => {
    const workspace = activeWorkspace();
    if (!workspace || workspace.owner_id !== state.user?.id) return;
    elements.workspaceNameInput.value = workspace.name;
    elements.workspaceNameForm.hidden = false;
    elements.editWorkspaceButton.hidden = true;
    elements.workspaceNameInput.focus();
});

elements.cancelWorkspaceName.addEventListener('click', () => {
    elements.workspaceNameForm.hidden = true;
    elements.editWorkspaceButton.hidden = false;
});

elements.workspaceNameForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    const workspace = activeWorkspace();
    if (!workspace) return;
    try {
        const response = await request(`/api/workspaces/${workspace.id}`, {
            method: 'PATCH',
            body: JSON.stringify({ name: elements.workspaceNameInput.value }),
        });
        const currentRole = workspace.current_user_role;
        Object.assign(workspace, normalizeWorkspace(response.data));
        workspace.current_user_role = currentRole;
        renderWorkspaceOptions();
        elements.workspaceDetailName.textContent = workspace.name;
        elements.workspaceNameForm.hidden = true;
        elements.editWorkspaceButton.hidden = false;
    } catch (error) {
        showMessage(elements.dashboardMessage, error.message);
    }
});

function renderActivityList() {
    const activityList = state.activityStandalone ? elements.standaloneActivityList : elements.activityList;
    const activityMore = state.activityStandalone ? elements.standaloneActivityMore : elements.activityMore;
    activityList.replaceChildren();
    activityMore.hidden = true;

    if (!state.activities.length) {
        activityList.textContent = 'Nessuna attività.';
        return;
    }

    const groups = new Map();
    state.activities.forEach((activity) => {
        const key = activityDayKey(activity.created_at);
        if (!groups.has(key)) groups.set(key, []);
        groups.get(key).push(activity);
    });
    const openDay = state.activityOpenDay === undefined ? activityDayKey(new Date()) : state.activityOpenDay;
    groups.forEach((activities, day) => {
        const section = document.createElement('section');
        section.className = 'activity-day';
        const heading = document.createElement('button');
        heading.className = 'activity-day-heading'; heading.type = 'button';
        heading.append(icon(day === openDay ? 'chevron-down' : 'chevron-right'), document.createTextNode(formatActivityDay(day)));
        const content = document.createElement('div'); content.className = 'activity-day-content'; content.hidden = day !== openDay;
        heading.onclick = () => {
            state.activityOpenDay = openDay === day ? null : day;
            state.activityDayExpandedByUser = state.activityOpenDay !== null;
            renderActivityList();
        };
        section.append(heading, content); activityList.append(section);
        activities.forEach((activity) => {
        const row = document.createElement('div');
        row.className = 'activity-row';
        const actor = document.createElement('strong');
        actor.textContent = activity.actor?.name ?? 'Utente';
        const text = document.createElement('span');
        text.textContent = formatActivity(activity);
        const date = document.createElement('small');
        date.textContent = formatActivityDate(activity.created_at);
        const meta = document.createElement('div');
        meta.className = 'activity-meta';
        meta.append(date);
        row.append(actor, text, meta);

        const details = formatActivityDetails(activity);
        if (details.length) {
            const toggle = document.createElement('button');
            toggle.className = 'activity-details-toggle';
            toggle.type = 'button';
            toggle.append(icon('chevron-down'), document.createTextNode('Dettagli'));
            toggle.setAttribute('aria-expanded', 'false');
            const box = document.createElement('div');
            box.className = 'activity-details';
            box.hidden = true;
            details.forEach((detail) => {
                const line = document.createElement('div');
                line.textContent = `${detail.label}: ${detail.oldValue} → ${detail.newValue}`;
                box.append(line);
            });
            toggle.onclick = () => {
                box.hidden = !box.hidden;
                toggle.replaceChildren(
                    icon(box.hidden ? 'chevron-down' : 'chevron-up'),
                    document.createTextNode(box.hidden ? 'Dettagli' : 'Nascondi'),
                );
                toggle.setAttribute('aria-expanded', String(!box.hidden));
                refreshIcons();
            };
            meta.append(toggle);
            row.append(box);
        }
        content.append(row);
        });
    });
    activityMore.hidden = !state.activityHasMore || state.activityOpenDay === null || !state.activityDayExpandedByUser;
    refreshIcons();
}

let activityPage = 1;
async function loadActivity(append = false) {
    const response = await request(`/api/workspaces/${state.workspaceId}/activity?page=${activityPage}`);
    const rows = response.data ?? [];
    if (!append) state.activities = [];
    rows.forEach((activity) => {
        if (!state.activities.some((item) => Number(item.id) === Number(activity.id))) {
            state.activities.push(activity);
        }
    });
    renderActivityList();
    state.activityHasMore = Boolean(response.next_page_url);
    const activityMore = state.activityStandalone ? elements.standaloneActivityMore : elements.activityMore;
    activityMore.hidden = !state.activityHasMore || state.activityOpenDay === null || !state.activityDayExpandedByUser;
}
elements.openActivityButton.addEventListener('click', async () => {
    state.activityStandalone = true;
    elements.activityModal.hidden = false;
    elements.activityModal.classList.add('open');
    activityPage = 1;
    state.activityOpenDay = activityDayKey(new Date());
    state.activityDayExpandedByUser = false;
    try {
        await loadActivity();
    } catch (error) {
        closeModals();
        showMessage(elements.dashboardMessage, error.message);
    }
});
elements.workspaceHistoryToggle.addEventListener('click', async () => {
    if (elements.workspaceHistoryContent.hidden) {
        try {
            await openWorkspaceHistory();
        } catch (error) {
            showMessage(elements.dashboardMessage, error.message);
        }
        return;
    }
    closeWorkspaceHistory();
});
const loadMoreActivity = async () => { activityPage += 1; await loadActivity(true); };
elements.activityMore.addEventListener('click', loadMoreActivity);
elements.standaloneActivityMore.addEventListener('click', loadMoreActivity);
elements.inviteForm.addEventListener('submit', async (event) => { event.preventDefault(); try { await request(`/api/workspaces/${state.workspaceId}/invitations`, { method: 'POST', body: JSON.stringify({ email: elements.inviteEmail.value, role: elements.inviteRole.value }) }); elements.inviteForm.reset(); await loadWorkspaceManagement(); } catch (error) { showMessage(elements.dashboardMessage, error.message); } });

function renderNotifications() {
    const invitations = state.invitations;
    const notifications = state.internalNotifications;
    const unreadCount = notifications.filter((item) => !item.read_at).length;
    elements.notificationCount.textContent = String(invitations.length + unreadCount);
    elements.notificationCount.hidden = invitations.length + unreadCount === 0;
    elements.notificationsList.replaceChildren();

    if (!invitations.length && !notifications.length) {
        elements.notificationsList.textContent = 'Nessuna notifica.';
        return;
    }

    invitations.forEach((invitation) => {
        const item = document.createElement('div');
        item.className = 'notification-item';
        item.dataset.invitationToken = invitation.token;
        item.dataset.invitationId = String(invitation.id);
        const workspace = document.createElement('strong');
        workspace.textContent = invitation.workspace?.name ?? 'Workspace';
        const message = document.createElement('span');
        message.textContent = `${invitation.workspace?.owner?.name ?? 'Un utente'} ti ha invitato nel workspace`;
        const expiry = document.createElement('small');
        expiry.textContent = `Scade il ${new Intl.DateTimeFormat('it-IT').format(new Date(invitation.expires_at))}`;
        const actions = document.createElement('div');
        actions.className = 'notification-actions';
        const accept = document.createElement('button');
        accept.className = 'btn btn-primary'; accept.type = 'button'; accept.dataset.acceptInvitation = '';
        accept.textContent = 'Accetta';
        const reject = document.createElement('button');
        reject.className = 'btn'; reject.type = 'button'; reject.dataset.rejectInvitation = '';
        reject.textContent = 'Rifiuta';
        actions.append(accept, reject);
        item.append(workspace, message, expiry, actions);
        elements.notificationsList.append(item);
    });

    if (notifications.length) {
        const heading = document.createElement('h3'); heading.textContent = 'Notifiche'; elements.notificationsList.append(heading);
        notifications.forEach((notification) => {
            const item = document.createElement('div'); item.className = `notification-item${notification.read_at ? '' : ' is-unread'}`; item.dataset.internalNotificationId = notification.id;
            const data = notification.data ?? {}; const actor = data.actor ?? {}; const actorName = [actor.name, actor.last_name].filter(Boolean).join(' ') || 'Un utente';
            const title = data.task_title ?? 'una task'; const message = document.createElement('span');
            message.textContent = notification.type === 'task_due_soon'
                ? `“${title}” scade tra meno di 24 ore.`
                : notification.type === 'task_overdue'
                    ? `“${title}” è scaduta.`
                    : notification.type === 'task_assigned'
                ? `${actorName}${actor.username ? ` (@${actor.username})` : ''} ti ha assegnato a “${title}”.`
                : `${actorName}${actor.username ? ` (@${actor.username})` : ''} ha commentato “${title}”: ${data.comment_preview ?? ''}`;
            item.append(message); elements.notificationsList.append(item);
        });
        if (unreadCount) { const all = document.createElement('button'); all.type = 'button'; all.className = 'btn'; all.textContent = 'Segna tutte come lette'; all.dataset.markAllNotifications = ''; elements.notificationsList.append(all); }
    }
}

async function loadInvitations() {
    const response = await request('/api/invitations');
    state.invitations = (response.data ?? []).map(normalizeInvitation);
    const notifications = await request('/api/notifications');
    state.internalNotifications = notifications.notifications ?? [];
    renderNotifications();
}

elements.notificationsButton.addEventListener('click', async () => {
    elements.notificationsPanel.hidden = !elements.notificationsPanel.hidden;
    if (!elements.notificationsPanel.hidden) await loadInvitations();
});
document.querySelector('[data-close-notifications]').addEventListener('click', () => { elements.notificationsPanel.hidden = true; });
elements.notificationsList.addEventListener('click', async (event) => {
    const notificationItem = event.target.closest('[data-internal-notification-id]');
    if (notificationItem) {
        const notification = state.internalNotifications.find((item) => item.id === notificationItem.dataset.internalNotificationId);
        if (notification) {
            notification.read_at = new Date().toISOString(); renderNotifications();
            request(`/api/notifications/${encodeURIComponent(notification.id)}/read`, { method: 'PATCH' }).catch(() => {});
            const data = notification.data ?? {};
            window.location.assign(`/boards/${encodeURIComponent(data.board_id)}?task=${encodeURIComponent(data.task_id)}`);
        }
        return;
    }
    if (event.target.closest('[data-mark-all-notifications]')) {
        state.internalNotifications.forEach((notification) => { if (!notification.read_at) notification.read_at = new Date().toISOString(); }); renderNotifications();
        request('/api/notifications/read-all', { method: 'POST' }).catch(() => {}); return;
    }
    const item = event.target.closest('[data-invitation-token]');
    if (!item) return;
    try {
        if (event.target.closest('[data-accept-invitation]')) {
            await request(`/api/invitations/${item.dataset.invitationToken}/accept`, { method: 'POST' });
            await loadWorkspaces();
        } else if (event.target.closest('[data-reject-invitation]')) {
            await request(`/api/invitations/${item.dataset.invitationId}`, { method: 'DELETE' });
        } else return;
        await loadInvitations();
    } catch (error) { showMessage(elements.dashboardMessage, error.message); }
});

elements.leaveWorkspace.addEventListener('click', async () => {
    const result = await confirmDialog('Vuoi lasciare questo workspace?');
    if (result !== 'delete') return;

    try {
        await request(`/api/workspaces/${state.workspaceId}/leave`, { method: 'DELETE' });
        closeModals();
        await loadWorkspaces();
    } catch (error) {
        showMessage(elements.dashboardMessage, error.message);
    }
});

elements.deleteWorkspace.addEventListener('click', async () => {
    const workspace = activeWorkspace();
    if (!workspace) return;

    const result = await confirmDialog(
        `Eliminare definitivamente "${workspace.name}"? Verranno eliminati anche cartelle, progetti e dati contenuti.`
    );
    if (result !== 'delete') return;

    try {
        await request(`/api/workspaces/${workspace.id}`, { method: 'DELETE' });
        closeModals();
        await loadWorkspaces();
    } catch (error) {
        showMessage(elements.dashboardMessage, error.message);
    }
});

elements.projectForm.addEventListener('submit', async (event) => {
    event.preventDefault();

    try {
        await saveProject();
        closeProjectModal();
        await loadWorkspaceData();
    } catch (error) {
        showMessage(elements.dashboardMessage, `Non riesco a salvare il progetto: ${error.message}`);
    }
});

elements.folderForm.addEventListener('submit', async (event) => {
    event.preventDefault();

    try {
        await saveFolder();
        closeFolderModal();
        await loadWorkspaceData();
    } catch (error) {
        showMessage(elements.dashboardMessage, `Non riesco a salvare la cartella: ${error.message}`);
    }
});

elements.projectModal.addEventListener('click', (event) => {
    if (event.target === elements.projectModal) closeProjectModal();
});

elements.folderModal.addEventListener('click', (event) => {
    if (event.target === elements.folderModal) closeFolderModal();
});

elements.closeConfirm.addEventListener('click', () => closeConfirmModal('cancel'));
elements.confirmButtons.forEach((button) => {
    button.addEventListener('click', () => closeConfirmModal(button.dataset.confirmResult));
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

document.addEventListener('click', async (event) => {
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

    const pathFolder = event.target.closest('[data-folder-path] [data-folder-path]');
    if (pathFolder) {
        const folder = state.folders.find((item) => item.id === Number(pathFolder.dataset.folderPath));
        if (!folder) return;
        state.currentFolderId = folder.id;
        state.viewingArchived = folder.archived;
        renderDashboard();
        return;
    }

    const pathRoot = event.target.closest('[data-folder-path-root]');
    if (pathRoot) {
        state.currentFolderId = null;
        state.viewingArchived = pathRoot.dataset.folderPathRoot === 'archive';
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

    const archiveProject = event.target.closest('[data-archive-project]');
    if (archiveProject) {
        const board = state.boards.find((item) => item.id === Number(archiveProject.dataset.archiveProject));
        if (!board) return;

        try {
            await moveBoardTo(board.id, board.archived ? null : board.folder_id, !board.archived);
        } catch (error) {
            showMessage(elements.dashboardMessage, `Non riesco ad aggiornare l'archivio: ${error.message}`);
        }
        return;
    }

    const deleteFolder = event.target.closest('[data-delete-folder]');
    if (deleteFolder) {
        const folder = state.folders.find((item) => item.id === Number(deleteFolder.dataset.deleteFolder));
        if (!folder) return;

        const hasInternalKan = boardCountForFolder(folder.id) > 0;
        const fallbackArea = state.viewingArchived ? "nell'archivio" : 'in Principale';
        const result = await confirmDialog(
            hasInternalKan
                ? `Eliminare la cartella "${folder.name}"? Puoi lasciare i Kan ${fallbackArea}, eliminarli, oppure archiviarli.`
                : `Eliminare la cartella "${folder.name}"?`,
            hasInternalKan ? ['delete', 'delete-kan', 'archive-kan'] : ['delete']
        );

        if (result === 'cancel') return;

        try {
            const folderIds = folderTreeIds(folder.id);
            const folderBoards = state.boards.filter((board) => folderIds.includes(board.folder_id));

            if (result === 'delete-kan') {
                for (const board of folderBoards) {
                    await request(`/api/boards/${board.id}`, { method: 'DELETE' });
                }
            }

            if (result === 'archive-kan') {
                for (const board of folderBoards) {
                    await moveBoardTo(board.id, null, true);
                }
            }

            await request(`/api/folders/${folder.id}`, { method: 'DELETE' });
            if (state.currentFolderId === folder.id) state.currentFolderId = null;
            await loadWorkspaceData();
        } catch (error) {
            showMessage(elements.dashboardMessage, error.message);
            await loadWorkspaceData();
        }
        return;
    }

    const deleteBoard = event.target.closest('[data-delete-board]');
    if (deleteBoard) {
        const board = state.boards.find((item) => item.id === Number(deleteBoard.dataset.deleteBoard));
        if (!board) return;

        const result = await confirmDialog(`Eliminare il progetto "${board.name}" e tutti i suoi eventi?`);
        if (result !== 'delete') return;

        try {
            await request(`/api/boards/${board.id}`, { method: 'DELETE' });
            state.boards = state.boards.filter((item) => item.id !== board.id);
            renderDashboard();
        } catch (error) {
            showMessage(elements.dashboardMessage, error.message);
            await loadWorkspaceData();
        }
    }
});
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        closeModals();
        closeConfirmModal('cancel');
        return;
    }

    if ((event.key === 'Enter' || event.key === ' ') && event.target.matches('[data-folder]')) {
        event.preventDefault();
        event.target.click();
    }
});

attachQuickDropEvents();
loadAuthenticatedUser()
    .catch(() => setGuestView())
    .finally(refreshIcons);
