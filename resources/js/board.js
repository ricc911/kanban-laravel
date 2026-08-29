import '../css/board.css';
import { activityDayKey, formatActivity, formatActivityDate, formatActivityDay, formatActivityDetails } from './activity-log';
import { subscribeToBoard, subscribeToBoardPresence, subscribeToUserRealtime } from './realtime';

const DEFAULT_TASK_COLOR = '#2563eb';
const DEFAULT_CATEGORY_COLOR = '#4f6f9f';
const UNCATEGORIZED = '__uncategorized__';

const state = {
    board: null,
    columns: [],
    categories: [],
    drag: null,
    realtimeCleanup: null,
    presenceCleanup: null,
    userRealtimeCleanup: null,
    realtimeRenderPending: false,
    onlineBoardUsers: new Map(),
    sharedWorkspace: false,
    editingTaskId: null,
    taskModalBaseline: {},
    latestRemoteTask: null,
    dirtyTaskFields: new Set(),
    taskModalConflicts: new Map(),
    taskModalRemoteChanged: false,
    taskModalDeleted: false,
    boardDeleted: false,
    workspaceRole: 'member',
    activities: [],
    activityOpenDay: null,
};

const elements = {
    title: document.querySelector('#currentBoardTitle'),
    description: document.querySelector('#boardDescription'),
    status: document.querySelector('#saveStatus'),
    boardColumns: document.querySelector('#boardColumns'),
    categorySelect: document.querySelector('#category'),
    categoryList: document.querySelector('#categoryList'),
    taskModal: document.querySelector('#taskModal'),
    categoryModal: document.querySelector('#categoryModal'),
    columnModal: document.querySelector('#columnModal'),
    deleteColumnModal: document.querySelector('#deleteColumnModal'),
    openTaskModal: document.querySelector('#openTaskModal'),
    openCategoryModal: document.querySelector('#openCategoryModal'),
    openColumnModal: document.querySelector('#openColumnModal'),
    taskForm: document.querySelector('#taskForm'),
    categoryForm: document.querySelector('#categoryForm'),
    columnForm: document.querySelector('#columnForm'),
    taskId: document.querySelector('#taskId'),
    taskTitle: document.querySelector('#title'),
    taskDescription: document.querySelector('#description'),
    taskPriority: document.querySelector('#priority'),
    taskDueAt: document.querySelector('#dueAt'),
    taskColor: document.querySelector('#color'),
    taskColorText: document.querySelector('#colorText'),
    taskColorPreset: document.querySelector('#colorPreset'),
    taskModalTitle: document.querySelector('#taskModalTitle'),
    taskSubmit: document.querySelector('#taskForm button[type="submit"]'),
    deleteTask: document.querySelector('#deleteTask'),
    taskRealtimeStatus: null,
    categoryId: document.querySelector('#categoryId'),
    categoryName: document.querySelector('#categoryName'),
    categoryColor: document.querySelector('#categoryColor'),
    categoryColorText: document.querySelector('#categoryColorText'),
    categoryColorPreset: document.querySelector('#categoryColorPreset'),
    categoryFormLabel: document.querySelector('#categoryFormLabel'),
    categorySubmit: document.querySelector('#categorySubmit'),
    columnId: document.querySelector('#columnId'),
    columnName: document.querySelector('#columnName'),
    columnModalTitle: document.querySelector('#columnModalTitle'),
    columnSubmit: document.querySelector('#columnSubmit'),
    deleteColumn: document.querySelector('#deleteColumn'),
    deleteColumnMessage: document.querySelector('#deleteColumnMessage'),
    deleteColumnWithTasks: document.querySelector('#deleteColumnWithTasks'),
    moveColumnTasks: document.querySelector('#moveColumnTasks'),
    backToProjects: document.querySelector('[data-back-to-projects]'),
    activityModal: document.querySelector('#activityModal'),
    openActivityModal: document.querySelector('#openActivityModal'),
    boardActivityList: document.querySelector('#boardActivityList'),
    boardPresence: document.querySelector('#boardPresence'),
    boardPresenceStatus: document.querySelector('#boardPresenceStatus'),
    boardPresenceUsers: document.querySelector('#boardPresenceUsers'),
    boardPresenceCount: document.querySelector('#boardPresenceCount'),
    workspaceDeletedModal: document.querySelector('#workspaceDeletedModal'),
    boardPresencePopover: document.querySelector('#boardPresencePopover'),
    boardPresenceClose: document.querySelector('#boardPresenceClose'),
    boardPresenceList: document.querySelector('#boardPresenceList'),
};

function boardId() {
    return document.body.dataset.boardId;
}

function currentUserId() {
    return document.body.dataset.userId;
}

function normalizePresenceUser(user) {
    if (!user || user.id === undefined || user.id === null) return null;

    return {
        id: String(user.id),
        name: user.name || 'Utente',
    };
}

function presenceInitials(name) {
    return name
        .trim()
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase() || '?';
}

function renderOnlineBoardUsers() {
    if (!elements.boardPresenceUsers) return;

    const users = [...state.onlineBoardUsers.values()]
        .sort((left, right) => left.name.localeCompare(right.name, 'it'));
    const visibleUsers = users.slice(0, 5);
    elements.boardPresenceUsers.replaceChildren();

    visibleUsers.forEach((user) => {
        const avatar = document.createElement('span');
        avatar.className = 'board-presence-avatar';
        avatar.textContent = presenceInitials(user.name);
        avatar.title = user.name;
        avatar.setAttribute('aria-label', user.name);
        elements.boardPresenceUsers.append(avatar);
    });

    if (users.length > 5) {
        const remaining = users.slice(5);
        const more = document.createElement('span');
        more.className = 'board-presence-avatar board-presence-more';
        more.textContent = `+${remaining.length}`;
        more.title = remaining.map((user) => user.name).join(', ');
        more.setAttribute('aria-label', `Altri utenti online: ${more.title}`);
        elements.boardPresenceUsers.append(more);
    }

    elements.boardPresenceCount.textContent = String(users.length);
    elements.boardPresence.hidden = !state.sharedWorkspace;
    if (elements.boardPresenceList) {
        elements.boardPresenceList.replaceChildren();
        users.forEach((user) => {
            const item = document.createElement('div');
            item.className = 'board-presence-list-item';
            item.textContent = user.name;
            elements.boardPresenceList.append(item);
        });
    }
}

function setPresenceOnline() {
    elements.boardPresenceStatus.textContent = 'Online';
    elements.boardPresence.classList.remove('is-offline');
    renderOnlineBoardUsers();
}

function setPresenceOffline() {
    state.onlineBoardUsers.clear();
    elements.boardPresenceStatus.textContent = 'Offline';
    elements.boardPresence.classList.add('is-offline');
    renderOnlineBoardUsers();
}

function applyPresenceHere(users) {
    state.onlineBoardUsers.clear();
    (users ?? []).map(normalizePresenceUser).filter(Boolean).forEach((user) => {
        state.onlineBoardUsers.set(user.id, user);
    });
    setPresenceOnline();
}

function applyPresenceJoining(user) {
    const normalized = normalizePresenceUser(user);
    if (!normalized) return;

    state.onlineBoardUsers.set(normalized.id, normalized);
    setPresenceOnline();
}

function applyPresenceLeaving(user) {
    const normalized = normalizePresenceUser(user);
    if (!normalized) return;

    state.onlineBoardUsers.delete(normalized.id);
    renderOnlineBoardUsers();
}

function renderBoardActivityList() {
    elements.boardActivityList.replaceChildren();
    const groups = new Map();
    state.activities.forEach((activity) => {
        const key = activityDayKey(activity.created_at);
        if (!groups.has(key)) groups.set(key, []);
        groups.get(key).push(activity);
    });
    const openDay = state.activityOpenDay === undefined ? activityDayKey(new Date()) : state.activityOpenDay;
    groups.forEach((activities, day) => {
        const section = document.createElement('section'); section.className = 'activity-day';
        const heading = document.createElement('button'); heading.className = 'activity-day-heading'; heading.type = 'button';
        heading.append(icon(day === openDay ? 'chevron-down' : 'chevron-right'), document.createTextNode(formatActivityDay(day)));
        const content = document.createElement('div'); content.className = 'activity-day-content'; content.hidden = day !== openDay;
        heading.onclick = () => { state.activityOpenDay = openDay === day ? null : day; renderBoardActivityList(); };
        section.append(heading, content); elements.boardActivityList.append(section);
        activities.forEach((activity) => {
        const row = document.createElement('div');
        row.className = 'activity-row';
        const actor = document.createElement('strong'); actor.textContent = activity.actor?.name ?? 'Utente';
        const message = document.createElement('span'); message.textContent = formatActivity(activity);
        const date = document.createElement('small'); date.textContent = formatActivityDate(activity.created_at);
        const meta = document.createElement('div'); meta.className = 'activity-meta'; meta.append(date);
        row.append(actor, message, meta);
        const details = formatActivityDetails(activity);
        if (details.length) {
            const toggle = document.createElement('button'); toggle.className = 'activity-details-toggle'; toggle.type = 'button'; toggle.append(icon('chevron-down'), document.createTextNode('Dettagli')); toggle.setAttribute('aria-expanded', 'false');
            const box = document.createElement('div'); box.className = 'activity-details'; box.hidden = true;
            details.forEach((detail) => { const line = document.createElement('div'); line.textContent = `${detail.label}: ${detail.oldValue} → ${detail.newValue}`; box.append(line); });
            toggle.onclick = () => { box.hidden = !box.hidden; toggle.replaceChildren(icon(box.hidden ? 'chevron-down' : 'chevron-up'), document.createTextNode(box.hidden ? 'Dettagli' : 'Nascondi')); toggle.setAttribute('aria-expanded', String(!box.hidden)); refreshIcons(); };
            meta.append(toggle); row.append(box);
        }
        content.append(row);
        });
    });
    refreshIcons();
}

async function loadBoardActivity() {
    const board = state.board;
    if (!board) return;
    const response = await request(`/api/workspaces/${board.workspace_id}/activity?board_id=${board.id}`);
    state.activities = response.data ?? [];
    state.activityOpenDay = activityDayKey(new Date());
    renderBoardActivityList();
}

elements.openActivityModal.addEventListener('click', async () => {
    elements.activityModal.classList.add('open');
    await loadBoardActivity();
});
document.querySelectorAll('[data-close="activityModal"]').forEach((button) => button.addEventListener('click', () => elements.activityModal.classList.remove('open')));

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
        const error = new Error(validationMessage ?? payload?.message ?? `Errore HTTP ${response.status}.`);
        error.status = response.status;
        throw error;
    }

    return payload;
}

function refreshIcons() {
    if (window.lucide?.createIcons) {
        window.lucide.createIcons();
    }
}

function icon(name) {
    const node = document.createElement('i');
    node.className = 'icon';
    node.dataset.lucide = name;
    return node;
}

function normalizeColor(value, fallback) {
    return /^#[0-9a-f]{6}$/i.test(value ?? '') ? value : fallback;
}

function normalizeCategory(category) {
    return {
        ...category,
        id: String(category.id),
        name: category.name ?? 'Categoria',
        color: normalizeColor(category.color, DEFAULT_CATEGORY_COLOR),
        position: Number(category.position ?? 0),
    };
}

function upsertCategoryInState(category) {
    const normalized = normalizeCategory(category);
    state.categories = [
        ...state.categories.filter((item) => item.id !== normalized.id),
        normalized,
    ].sort((left, right) => left.position - right.position);
}

function normalizeTask(task) {
    return {
        ...task,
        id: String(task.id),
        board_column_id: Number(task.board_column_id),
        category_id: task.category_id === null || task.category_id === undefined ? '' : String(task.category_id),
        color: normalizeColor(task.color, ''),
        title: task.title ?? 'Evento',
        description: task.description ?? '',
        priority: task.priority ?? '',
        due_at: task.due_at ?? null,
        position: Number(task.position ?? 0),
    };
}

function normalizeColumn(column) {
    return {
        ...column,
        id: Number(column.id),
        name: column.name ?? 'Colonna',
        position: Number(column.position ?? 0),
        color: normalizeColor(column.color, DEFAULT_CATEGORY_COLOR),
        tasks: (column.tasks ?? []).map(normalizeTask).sort((left, right) => left.position - right.position),
    };
}

function allTasks() {
    return state.columns.flatMap((column) => column.tasks);
}

function findColumn(columnId) {
    return state.columns.find((column) => column.id === Number(columnId)) ?? null;
}

function findTask(taskId) {
    return allTasks().find((task) => task.id === String(taskId)) ?? null;
}

function findTaskColumn(taskId) {
    return state.columns.find((column) => column.tasks.some((task) => task.id === String(taskId))) ?? null;
}

function removeTaskFromState(taskId) {
    const id = String(taskId);
    state.columns.forEach((column) => {
        column.tasks = column.tasks.filter((task) => task.id !== id);
    });
}

function upsertTaskInState(task) {
    const normalized = normalizeTask(task);
    removeTaskFromState(normalized.id);

    if (normalized.archived) {
        return;
    }

    const column = findColumn(normalized.board_column_id);
    if (!column) {
        return;
    }

    column.tasks.push(normalized);
    column.tasks.sort((left, right) => left.position - right.position);
}

const editableTaskFields = {
    title: elements.taskTitle,
    description: elements.taskDescription,
    priority: elements.taskPriority,
    category_id: elements.categorySelect,
    due_at: elements.taskDueAt,
};

function ensureTaskRealtimeStatus() {
    if (elements.taskRealtimeStatus) return elements.taskRealtimeStatus;

    const status = document.createElement('p');
    status.className = 'task-realtime-status';
    status.hidden = true;
    status.setAttribute('aria-live', 'polite');
    elements.taskForm.prepend(status);
    elements.taskRealtimeStatus = status;

    return status;
}

function taskFieldValue(field) {
    const element = editableTaskFields[field];
    if (!element) return null;

    if (field === 'title') return element.value.trim();
    return element.value || null;
}

function taskFieldValueFromTask(field, task) {
    if (field === 'title') return task.title ?? '';
    if (field === 'description') return task.description || null;
    if (field === 'priority') return task.priority || null;
    if (field === 'category_id') return task.category_id || null;
    if (field === 'due_at') return toDateInput(task.due_at) || null;

    return null;
}

function setTaskFieldValue(field, value) {
    const element = editableTaskFields[field];
    if (!element) return;

    element.value = value ?? '';
}

function readableRemoteTaskValue(field, task) {
    const value = taskFieldValueFromTask(field, task);

    if (field === 'category_id') {
        return value ? findCategory(value)?.name ?? `Categoria #${value}` : 'Nessuna categoria';
    }
    if (field === 'priority') return priorityLabel(value) || 'Nessuna';
    if (field === 'due_at') return formatDate(task.due_at) || 'Nessuna scadenza';
    if (field === 'description') return value || 'Nessuna descrizione';

    return value || 'Nessuno';
}

function fieldConflictElement(field) {
    const input = editableTaskFields[field];
    const wrapper = input?.closest('.field');
    if (!wrapper) return null;

    let message = wrapper.querySelector('.task-remote-field-message');
    if (!message) {
        message = document.createElement('p');
        message.className = 'task-remote-field-message';
        message.hidden = true;
        message.setAttribute('aria-live', 'polite');
        wrapper.append(message);
    }

    return message;
}

function renderTaskModalRealtimeState() {
    const status = ensureTaskRealtimeStatus();
    const hasConflicts = state.taskModalConflicts.size > 0;

    status.hidden = !state.taskModalRemoteChanged && !state.taskModalDeleted;
    status.classList.toggle('is-conflict', hasConflicts || state.taskModalDeleted);
    status.textContent = state.taskModalDeleted
        ? 'Questo task è stato eliminato da un altro utente.'
        : hasConflicts
            ? 'Questo task è stato modificato anche da un altro utente.'
            : 'Aggiornato in tempo reale';

    Object.keys(editableTaskFields).forEach((field) => {
        const message = fieldConflictElement(field);
        const remoteTask = state.latestRemoteTask;
        const conflict = state.taskModalConflicts.has(field);
        message.hidden = !conflict || !remoteTask;
        message.textContent = conflict && remoteTask
            ? `Valore remoto: ${readableRemoteTaskValue(field, remoteTask)}`
            : '';
        editableTaskFields[field]?.classList.toggle('has-remote-conflict', conflict);
    });
}

function clearTaskModalRealtimeState() {
    state.editingTaskId = null;
    state.taskModalBaseline = {};
    state.latestRemoteTask = null;
    state.dirtyTaskFields.clear();
    state.taskModalConflicts.clear();
    state.taskModalRemoteChanged = false;
    state.taskModalDeleted = false;

    if (elements.taskSubmit) elements.taskSubmit.disabled = false;
    elements.deleteTask.disabled = false;
    if (elements.taskRealtimeStatus) elements.taskRealtimeStatus.hidden = true;
    Object.keys(editableTaskFields).forEach((field) => {
        const message = fieldConflictElement(field);
        message.hidden = true;
        message.textContent = '';
        editableTaskFields[field]?.classList.remove('has-remote-conflict');
    });
}

function beginTaskModalEditing(task) {
    clearTaskModalRealtimeState();
    state.editingTaskId = String(task.id);
    state.latestRemoteTask = normalizeTask(task);
    Object.keys(editableTaskFields).forEach((field) => {
        state.taskModalBaseline[field] = taskFieldValueFromTask(field, state.latestRemoteTask);
    });
}

function syncOpenTaskModal(task) {
    if (state.editingTaskId !== String(task.id) || state.taskModalDeleted) return;

    state.latestRemoteTask = task;
    state.taskModalRemoteChanged = true;

    Object.keys(editableTaskFields).forEach((field) => {
        const remoteValue = taskFieldValueFromTask(field, task);
        const localValue = taskFieldValue(field);

        if (!state.dirtyTaskFields.has(field)) {
            setTaskFieldValue(field, remoteValue);
            state.taskModalBaseline[field] = remoteValue;
            state.taskModalConflicts.delete(field);
        } else if (localValue !== remoteValue) {
            state.taskModalConflicts.set(field, remoteValue);
        }
    });

    elements.taskForm.dataset.columnId = String(task.board_column_id);
    renderTaskModalRealtimeState();
}

function trackTaskFieldChange(field) {
    if (!state.editingTaskId || state.taskModalDeleted) return;

    const value = taskFieldValue(field);
    if (value === state.taskModalBaseline[field]) {
        state.dirtyTaskFields.delete(field);
        if (state.taskModalConflicts.has(field) && state.latestRemoteTask) {
            const remoteValue = taskFieldValueFromTask(field, state.latestRemoteTask);
            setTaskFieldValue(field, remoteValue);
            state.taskModalBaseline[field] = remoteValue;
            state.taskModalConflicts.delete(field);
        }
    } else {
        state.dirtyTaskFields.add(field);
        if (state.latestRemoteTask && value === taskFieldValueFromTask(field, state.latestRemoteTask)) {
            state.taskModalConflicts.delete(field);
        }
    }

    renderTaskModalRealtimeState();
}

function markTaskDeletedRemotely() {
    if (!state.editingTaskId) return;

    state.taskModalDeleted = true;
    elements.taskSubmit.disabled = true;
    elements.deleteTask.disabled = true;
    renderTaskModalRealtimeState();
}

function renderAfterRealtimeUpdate() {
    if (state.drag) {
        state.realtimeRenderPending = true;
        return;
    }

    renderBoard();
}

function flushRealtimeRender() {
    if (!state.drag && state.realtimeRenderPending) {
        state.realtimeRenderPending = false;
        renderBoard();
    }
}

function applyRemoteTaskCreated(payload) {
    if (Number(payload?.task?.board_id) !== Number(boardId())) return;

    upsertTaskInState(payload.task);
    renderAfterRealtimeUpdate();
}

function applyRemoteTaskUpdated(payload) {
    if (Number(payload?.task?.board_id) !== Number(boardId())) return;

    const remoteTask = normalizeTask(payload.task);
    upsertTaskInState(remoteTask);
    syncOpenTaskModal(remoteTask);
    renderAfterRealtimeUpdate();
}

function applyRemoteTaskMoved(payload) {
    if (Number(payload?.task?.board_id) !== Number(boardId())) return;

    const remoteTask = normalizeTask(payload.task);
    upsertTaskInState(remoteTask);
    if (state.editingTaskId === String(remoteTask.id) && !state.taskModalDeleted) {
        state.latestRemoteTask = remoteTask;
        elements.taskForm.dataset.columnId = String(remoteTask.board_column_id);
    }
    renderAfterRealtimeUpdate();
}

function applyRemoteTaskDeleted(payload) {
    if (Number(payload?.board_id) !== Number(boardId())) return;

    removeTaskFromState(payload.task_id);
    if (state.editingTaskId === String(payload.task_id)) {
        markTaskDeletedRemotely();
    }
    renderAfterRealtimeUpdate();
}

function applyRemoteTasksReordered(payload) {
    if (
        Number(payload?.board_id) !== Number(boardId()) ||
        !Array.isArray(payload?.tasks)
    ) return;

    const column = findColumn(payload.column_id);
    if (!column) return;

    const positions = new Map(payload.tasks.map((task) => [String(task.id), Number(task.position)]));
    column.tasks.forEach((task) => {
        if (positions.has(task.id)) {
            task.position = positions.get(task.id);
        }
    });
    column.tasks.sort((left, right) => left.position - right.position);
    renderAfterRealtimeUpdate();
}

function isCurrentBoard(payload) {
    return Number(payload?.board_id ?? payload?.board?.id ?? payload?.activity?.board?.id) === Number(boardId());
}

function applyRemoteBoardChanged(payload) {
    if (!isCurrentBoard(payload) || !payload.board || !state.board) return;

    state.board = { ...state.board, ...payload.board };
    renderBoard();
}

function applyRemoteBoardDeleted(payload) {
    if (!isCurrentBoard(payload)) return;

    state.board = null;
    state.columns = [];
    state.categories = [];
    state.boardDeleted = true;
    state.realtimeCleanup?.();
    state.realtimeCleanup = null;
    state.presenceCleanup?.();
    state.presenceCleanup = null;
    state.userRealtimeCleanup?.();
    state.userRealtimeCleanup = null;
    document.querySelectorAll('.toolbar button').forEach((button) => { button.disabled = true; });
    document.querySelectorAll('.modal.open').forEach((modal) => modal.classList.remove('open'));
    elements.boardColumns.replaceChildren();
    setStatus('Questo progetto è stato eliminato da un altro utente.', true);
}

function applyRemoteCategory(payload, removed = false) {
    if (!isCurrentBoard(payload)) return;

    const categoryId = String(payload.category_id ?? payload.category?.id);
    if (removed) {
        state.categories = state.categories.filter((category) => category.id !== categoryId);
        state.columns.forEach((column) => {
            column.tasks = column.tasks.map((task) => (
                task.category_id === categoryId ? { ...task, category_id: '' } : task
            ));
        });
    } else if (payload.category) {
        upsertCategoryInState(payload.category);
    }
    renderAfterRealtimeUpdate();
}

function applyRemoteColumn(payload, removed = false) {
    if (!isCurrentBoard(payload)) return;

    const columnId = Number(payload.column_id ?? payload.column?.id);
    if (removed) {
        state.columns = state.columns.filter((column) => column.id !== columnId);
    } else if (payload.column) {
        const remoteColumn = normalizeColumn({
            ...payload.column,
            tasks: findColumn(columnId)?.tasks ?? [],
        });
        state.columns = [
            ...state.columns.filter((column) => column.id !== remoteColumn.id),
            remoteColumn,
        ].sort((left, right) => left.position - right.position);
    }
    renderAfterRealtimeUpdate();
}

function applyRemoteColumnsReordered(payload) {
    if (!isCurrentBoard(payload) || !Array.isArray(payload.columns)) return;

    const positions = new Map(payload.columns.map((column) => [Number(column.id), Number(column.position)]));
    state.columns.forEach((column) => {
        if (positions.has(column.id)) column.position = positions.get(column.id);
    });
    state.columns.sort((left, right) => left.position - right.position);
    renderAfterRealtimeUpdate();
}

function applyRemoteActivity(payload) {
    if (!isCurrentBoard(payload) || !payload.activity) return;

    const activity = payload.activity;
    state.activities = [
        activity,
        ...state.activities.filter((item) => Number(item.id) !== Number(activity.id)),
    ];
    if (elements.activityModal.classList.contains('open')) renderBoardActivityList();
}

function handleRemoteBoardAccessRemoved(payload) {
    if (!state.board || Number(state.board.workspace_id) !== Number(payload?.workspace?.id ?? payload?.workspace_id)) return;

    state.board = null;
    state.columns = [];
    state.categories = [];
    state.boardDeleted = true;
    state.realtimeCleanup?.();
    state.realtimeCleanup = null;
    state.presenceCleanup?.();
    state.presenceCleanup = null;
    state.userRealtimeCleanup?.();
    state.userRealtimeCleanup = null;
    document.querySelectorAll('.toolbar button').forEach((button) => { button.disabled = true; });
    document.querySelectorAll('.modal.open').forEach((modal) => modal.classList.remove('open'));
    elements.boardColumns.replaceChildren();
    setStatus('Non hai più accesso a questo workspace.', true);
}

function handleRemoteBoardRoleUpdated(payload) {
    if (!state.board || Number(state.board.workspace_id) !== Number(payload?.workspace?.id)) return;

    state.workspaceRole = payload.role ?? state.workspaceRole;
    renderBoard();
}

function handleRemoteBoardDeleted(payload) {
    if (!state.board || Number(state.board.workspace_id) !== Number(payload?.workspace?.id ?? payload?.workspace_id)) return;

    handleRemoteBoardAccessRemoved(payload);
    elements.workspaceDeletedModal.hidden = false;
    elements.workspaceDeletedModal.classList.add('open');
}

function subscribeToCurrentBoard() {
    state.realtimeCleanup?.();
    state.presenceCleanup?.();
    state.userRealtimeCleanup?.();
    state.realtimeCleanup = subscribeToBoard(boardId(), {
        created: applyRemoteTaskCreated,
        updated: applyRemoteTaskUpdated,
        moved: applyRemoteTaskMoved,
        deleted: applyRemoteTaskDeleted,
        reordered: applyRemoteTasksReordered,
        boardUpdated: applyRemoteBoardChanged,
        boardArchived: applyRemoteBoardChanged,
        boardRestored: applyRemoteBoardChanged,
        boardDeleted: applyRemoteBoardDeleted,
        categoryCreated: (payload) => applyRemoteCategory(payload),
        categoryUpdated: (payload) => applyRemoteCategory(payload),
        categoryDeleted: (payload) => applyRemoteCategory(payload, true),
        columnCreated: (payload) => applyRemoteColumn(payload),
        columnUpdated: (payload) => applyRemoteColumn(payload),
        columnDeleted: (payload) => applyRemoteColumn(payload, true),
        columnsReordered: applyRemoteColumnsReordered,
        activityLogged: applyRemoteActivity,
        error: (error) => console.warn('Realtime board non disponibile.', error),
    });
    state.presenceCleanup = subscribeToBoardPresence(boardId(), {
        here: applyPresenceHere,
        joining: applyPresenceJoining,
        leaving: applyPresenceLeaving,
        error: setPresenceOffline,
    });
    state.userRealtimeCleanup = subscribeToUserRealtime(currentUserId(), {
        workspaceAccessRemoved: handleRemoteBoardAccessRemoved,
        workspaceRoleUpdated: handleRemoteBoardRoleUpdated,
        workspaceDeleted: handleRemoteBoardDeleted,
        error: (error) => console.warn('Realtime utente non disponibile.', error),
    });
}

function clearBoardRealtimeSubscriptions() {
    state.realtimeCleanup?.();
    state.realtimeCleanup = null;
    state.presenceCleanup?.();
    state.presenceCleanup = null;
    state.userRealtimeCleanup?.();
    state.userRealtimeCleanup = null;
}

window.addEventListener('pagehide', clearBoardRealtimeSubscriptions);

function findCategory(categoryId) {
    return state.categories.find((category) => category.id === String(categoryId)) ?? null;
}

function taskGroupKey(task) {
    return task.category_id && findCategory(task.category_id) ? task.category_id : UNCATEGORIZED;
}

function groupInfo(groupKey) {
    const category = findCategory(groupKey);

    return category
        ? { name: category.name, color: category.color }
        : { name: 'Senza categoria', color: '#5f6975' };
}

function toDateInput(value) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';

    return date.toISOString().slice(0, 10);
}

function formatDate(value) {
    const input = toDateInput(value);
    if (!input) return '';

    return new Intl.DateTimeFormat('it-IT', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(`${input}T00:00:00`));
}

function priorityLabel(value) {
    return {
        low: 'Bassa',
        medium: 'Media',
        high: 'Alta',
    }[value] ?? value;
}

function setStatus(message, isError = false) {
    elements.status.textContent = message;
    elements.status.style.color = isError ? '#fca5a5' : '';
}

function sortedColumnIds() {
    return state.columns.map((column) => column.id);
}

function columnTaskIds(column) {
    return column.tasks.map((task) => Number(task.id));
}

function updateTaskPositions(column) {
    column.tasks.forEach((task, index) => {
        task.board_column_id = column.id;
        task.position = (index + 1) * 1000;
    });
}

function renderCategorySelect() {
    const selectedCategory = elements.categorySelect.value;
    elements.categorySelect.replaceChildren();

    const empty = document.createElement('option');
    empty.value = '';
    empty.textContent = 'Senza categoria';
    elements.categorySelect.append(empty);

    state.categories.forEach((category) => {
        const option = document.createElement('option');
        option.value = category.id;
        option.textContent = category.name;
        elements.categorySelect.append(option);
    });
    elements.categorySelect.value = state.categories.some((category) => category.id === selectedCategory)
        ? selectedCategory
        : '';
}

function renderCategoryList() {
    elements.categoryList.replaceChildren();

    if (state.categories.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'empty';
        empty.textContent = 'Nessuna categoria creata.';
        elements.categoryList.append(empty);
        return;
    }

    state.categories.forEach((category) => {
        const item = document.createElement('div');
        item.className = 'category-item';

        const name = document.createElement('div');
        name.className = 'category-name';

        const dot = document.createElement('span');
        dot.className = 'dot';
        dot.style.setProperty('--dot', category.color);

        name.append(dot, document.createTextNode(category.name));

        const actions = document.createElement('div');
        actions.className = 'category-actions';

        const edit = document.createElement('button');
        edit.className = 'edit-category';
        edit.type = 'button';
        edit.title = 'Modifica categoria';
        edit.ariaLabel = 'Modifica categoria';
        edit.dataset.editCategory = category.id;
        edit.append(icon('pencil'));

        const remove = document.createElement('button');
        remove.className = 'delete-category';
        remove.type = 'button';
        remove.dataset.deleteCategory = category.id;
        remove.textContent = 'Rimuovi';

        actions.append(edit, remove);
        item.append(name, actions);
        elements.categoryList.append(item);
    });
}

function renderTask(task) {
    const category = findCategory(task.category_id);
    const article = document.createElement('div');
    article.className = 'task';
    article.draggable = true;
    article.dataset.id = task.id;
    article.style.setProperty('--task-color', task.color || category?.color || DEFAULT_TASK_COLOR);

    const menu = document.createElement('button');
    menu.className = 'task-menu';
    menu.type = 'button';
    menu.title = 'Modifica';
    menu.dataset.edit = task.id;
    menu.textContent = '...';

    const title = document.createElement('h3');
    title.className = 'task-title';
    title.textContent = task.title;

    article.append(menu, title);

    if (task.description) {
        const description = document.createElement('p');
        description.className = 'task-description';
        description.textContent = task.description;
        article.append(description);
    }

    if (task.priority || task.due_at) {
        const meta = document.createElement('div');
        meta.className = 'task-meta';

        if (task.priority) {
            const priority = document.createElement('span');
            priority.textContent = priorityLabel(task.priority);
            meta.append(priority);
        }

        if (task.due_at) {
            const dueAt = document.createElement('span');
            dueAt.textContent = formatDate(task.due_at);
            meta.append(dueAt);
        }

        article.append(meta);
    }

    return article;
}

function renderGroup(groupKey, tasks, columnId) {
    const info = groupInfo(groupKey);
    const group = document.createElement('section');
    group.className = 'category-group';
    group.dataset.categoryKey = groupKey;
    group.style.setProperty('--category-color', info.color);

    const header = document.createElement('div');
    header.className = 'category-group-header';
    header.draggable = true;
    header.dataset.dragCategory = groupKey;
    header.dataset.sourceColumn = String(columnId);
    header.title = 'Trascina intera categoria';

    const title = document.createElement('div');
    title.className = 'category-group-title';

    const dot = document.createElement('span');
    dot.className = 'dot';

    const name = document.createElement('span');
    name.className = 'category-group-name';
    name.textContent = info.name;

    title.append(dot, name);

    const side = document.createElement('div');
    side.className = 'category-group-side';

    const count = document.createElement('span');
    count.className = 'category-group-count';
    count.textContent = String(tasks.length);

    const handle = document.createElement('span');
    handle.className = 'category-drag-handle';
    handle.ariaHidden = 'true';
    handle.textContent = '::';

    side.append(count, handle);
    header.append(title, side);

    const taskList = document.createElement('div');
    taskList.className = 'category-tasks';
    tasks.forEach((task) => taskList.append(renderTask(task)));

    group.append(header, taskList);
    return group;
}

function renderColumn(column) {
    const article = document.createElement('article');
    article.className = 'column';
    article.dataset.columnId = String(column.id);

    const header = document.createElement('div');
    header.className = 'column-header';
    header.draggable = true;

    const title = document.createElement('h2');
    title.className = 'column-title';
    title.textContent = column.name;

    const side = document.createElement('div');
    side.className = 'column-side';

    const count = document.createElement('span');
    count.className = 'count';
    count.textContent = String(column.tasks.length);

    const actions = document.createElement('div');
    actions.className = 'column-actions';

    const edit = document.createElement('button');
    edit.type = 'button';
    edit.title = 'Rinomina colonna';
    edit.dataset.editColumn = String(column.id);
    edit.append(icon('pencil'));

    const dragHandle = document.createElement('span');
    dragHandle.className = 'category-drag-handle';
    dragHandle.ariaHidden = 'true';
    dragHandle.textContent = '::';

    actions.append(edit, dragHandle);
    side.append(count, actions);
    header.append(title, side);

    const dropzone = document.createElement('div');
    dropzone.className = 'dropzone';
    dropzone.dataset.columnId = String(column.id);

    if (column.tasks.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'empty';
        empty.textContent = 'Trascina qui un evento o una categoria';
        dropzone.append(empty);
    } else {
        const groupKeys = [...new Set(column.tasks.map(taskGroupKey))];
        const orderedKeys = [
            ...state.categories.map((category) => category.id).filter((id) => groupKeys.includes(id)),
            ...(groupKeys.includes(UNCATEGORIZED) ? [UNCATEGORIZED] : []),
        ];

        orderedKeys.forEach((key) => {
            const tasks = column.tasks.filter((task) => taskGroupKey(task) === key);
            if (tasks.length > 0) dropzone.append(renderGroup(key, tasks, column.id));
        });
    }

    article.append(header, dropzone);
    return article;
}

function renderBoard() {
    if (!state.board) return;

    const viewer = state.workspaceRole === 'viewer';
    document.body.classList.toggle('viewer-mode', viewer);
    [elements.openColumnModal, elements.openCategoryModal, elements.openTaskModal].forEach((button) => { if (button) button.hidden = viewer; });

    elements.title.textContent = state.board.name ?? 'Kanban';

    if (state.board.description) {
        elements.description.textContent = state.board.description;
        elements.description.hidden = false;
    } else {
        elements.description.textContent = '';
        elements.description.hidden = true;
    }

    elements.boardColumns.replaceChildren();
    elements.boardColumns.style.setProperty('--board-column-count', String(Math.min(6, Math.max(1, state.columns.length))));
    state.columns.forEach((column) => elements.boardColumns.append(renderColumn(column)));
    renderCategorySelect();
    renderCategoryList();
    setStatus('Salvato');
    bindDragEvents();
    refreshIcons();
}

function setError(error) {
    const messageByStatus = {
        401: 'Sessione scaduta. Effettua di nuovo l accesso.',
        403: 'Non hai accesso a questa board.',
        404: 'Board non trovata.',
        422: error.message,
    };

    state.board = null;
    state.columns = [];
    state.categories = [];
    state.realtimeCleanup?.();
    state.realtimeCleanup = null;
    state.presenceCleanup?.();
    state.presenceCleanup = null;
    state.userRealtimeCleanup?.();
    state.userRealtimeCleanup = null;
    state.onlineBoardUsers.clear();
    setPresenceOffline();
    elements.title.textContent = 'Errore database';
    elements.description.textContent = '';
    elements.description.hidden = true;
    elements.boardColumns.replaceChildren();
    setStatus(messageByStatus[error.status] ?? error.message, true);
}

async function loadBoard() {
    setStatus('Connessione al database...');

    try {
        const [response, workspacesResponse] = await Promise.all([
            request(`/api/boards/${encodeURIComponent(boardId())}`),
            request('/api/workspaces'),
        ]);
        const board = response.data ?? response;

        state.board = board;
        const currentWorkspace = (workspacesResponse.data ?? []).find((workspace) => Number(workspace.id) === Number(board.workspace_id));
        state.workspaceRole = currentWorkspace?.current_user_role ?? currentWorkspace?.pivot?.role ?? 'member';
        state.sharedWorkspace = (workspacesResponse.data ?? []).some((workspace) => (
            Number(workspace.id) === Number(board.workspace_id) && workspace.type === 'shared'
        ));
        state.boardDeleted = false;
        state.categories = (board.categories ?? [])
            .map(normalizeCategory)
            .sort((left, right) => left.position - right.position);
        state.columns = (board.columns ?? [])
            .map(normalizeColumn)
            .sort((left, right) => left.position - right.position);

        renderBoard();
        subscribeToCurrentBoard();
    } catch (error) {
        setError(error);
    }
}

function openModal(id) {
    document.getElementById(id)?.classList.add('open');
}

function closeModal(id) {
    document.getElementById(id)?.classList.remove('open');
    if (id === 'taskModal') clearTaskModalRealtimeState();
}

function setTaskColor(value) {
    const color = normalizeColor(value, DEFAULT_TASK_COLOR);
    const preset = [...elements.taskColorPreset.options].find((option) => (
        option.value && option.value.toLowerCase() === color.toLowerCase()
    ));

    elements.taskColor.value = color;
    elements.taskColorText.value = color;
    elements.taskColorPreset.value = preset?.value ?? '';
}

function resetTaskForm() {
    clearTaskModalRealtimeState();
    elements.taskForm.reset();
    elements.taskId.value = '';
    elements.taskModalTitle.textContent = 'Nuovo evento';
    elements.deleteTask.style.display = 'none';
    setTaskColor(DEFAULT_TASK_COLOR);
    elements.categorySelect.value = '';
    elements.taskForm.dataset.columnId = state.columns[0]?.id ?? '';
}

function editTask(task) {
    beginTaskModalEditing(task);
    elements.taskId.value = task.id;
    elements.taskTitle.value = task.title;
    elements.taskDescription.value = task.description ?? '';
    elements.categorySelect.value = task.category_id ?? '';
    elements.taskPriority.value = task.priority ?? '';
    elements.taskDueAt.value = toDateInput(task.due_at);
    const color = task.color || findCategory(task.category_id)?.color || DEFAULT_TASK_COLOR;
    setTaskColor(color);
    elements.taskForm.dataset.columnId = String(task.board_column_id);
    elements.taskModalTitle.textContent = 'Modifica evento';
    elements.deleteTask.style.display = 'inline-flex';
    renderTaskModalRealtimeState();
}

function resetCategoryForm() {
    elements.categoryForm.reset();
    elements.categoryId.value = '';
    elements.categoryFormLabel.textContent = 'Nuova categoria';
    elements.categorySubmit.textContent = 'Crea categoria';
    elements.categoryColor.value = DEFAULT_CATEGORY_COLOR;
    elements.categoryColorText.value = DEFAULT_CATEGORY_COLOR;
    elements.categoryColorPreset.value = DEFAULT_CATEGORY_COLOR;
}

function editCategory(category) {
    elements.categoryId.value = category.id;
    elements.categoryName.value = category.name;
    elements.categoryColor.value = category.color;
    elements.categoryColorText.value = category.color;
    elements.categoryColorPreset.value = category.color;
    elements.categoryFormLabel.textContent = 'Modifica categoria';
    elements.categorySubmit.textContent = 'Salva categoria';
}

function resetColumnForm() {
    elements.columnForm.reset();
    elements.columnId.value = '';
    elements.columnModalTitle.textContent = 'Nuova colonna';
    elements.columnSubmit.textContent = 'Salva colonna';
    elements.deleteColumn.style.display = 'none';
}

function editColumn(column) {
    elements.columnId.value = column.id;
    elements.columnName.value = column.name;
    elements.columnModalTitle.textContent = 'Rinomina colonna';
    elements.columnSubmit.textContent = 'Salva colonna';
    elements.deleteColumn.style.display = 'inline-flex';
}

async function saveTask() {
    if (state.taskModalDeleted) {
        throw new Error('Questo task è stato eliminato da un altro utente.');
    }

    const id = elements.taskId.value;
    const categoryId = elements.categorySelect.value || null;
    const payload = {
        title: elements.taskTitle.value.trim(),
        description: elements.taskDescription.value || null,
        category_id: categoryId,
        priority: elements.taskPriority.value || null,
        due_at: elements.taskDueAt.value || null,
        color: normalizeColor(elements.taskColorText.value, DEFAULT_TASK_COLOR),
    };

    if (!payload.title) return;

    if (id) {
        const response = await request(`/api/tasks/${encodeURIComponent(id)}`, {
            method: 'PATCH',
            body: JSON.stringify(payload),
        });
        const updated = normalizeTask(response.data);
        upsertTaskInState(updated);
        state.latestRemoteTask = updated;
        Object.keys(editableTaskFields).forEach((field) => {
            state.taskModalBaseline[field] = taskFieldValueFromTask(field, updated);
        });
        state.dirtyTaskFields.clear();
        state.taskModalConflicts.clear();
        state.taskModalRemoteChanged = false;
        return;
    }

    const columnId = Number(elements.taskForm.dataset.columnId || state.columns[0]?.id);
    if (!columnId) throw new Error('Crea una colonna prima di aggiungere eventi.');

    const response = await request(`/api/boards/${encodeURIComponent(boardId())}/columns/${columnId}/tasks`, {
        method: 'POST',
        body: JSON.stringify(payload),
    });
    const created = normalizeTask(response.data);
    upsertTaskInState(created);
}

async function deleteCurrentTask() {
    const id = elements.taskId.value;
    if (!id) return;

    await request(`/api/tasks/${encodeURIComponent(id)}`, { method: 'DELETE' });
    removeTaskFromState(id);
}

async function saveCategory() {
    const id = elements.categoryId.value;
    const payload = {
        name: elements.categoryName.value.trim(),
        color: normalizeColor(elements.categoryColorText.value, elements.categoryColor.value),
    };

    if (!payload.name) return;

    if (id) {
        const response = await request(`/api/categories/${encodeURIComponent(id)}`, {
            method: 'PATCH',
            body: JSON.stringify(payload),
        });
        const updated = normalizeCategory(response.data);
        state.categories = state.categories.map((category) => (category.id === updated.id ? updated : category));
        return;
    }

    const response = await request(`/api/boards/${encodeURIComponent(boardId())}/categories`, {
        method: 'POST',
        body: JSON.stringify(payload),
    });
    upsertCategoryInState(response.data);
}

async function deleteCategory(id) {
    await request(`/api/categories/${encodeURIComponent(id)}`, { method: 'DELETE' });
    state.categories = state.categories.filter((category) => category.id !== String(id));
    state.columns.forEach((column) => {
        column.tasks = column.tasks.map((task) => (
            task.category_id === String(id) ? { ...task, category_id: '' } : task
        ));
    });
}

async function saveColumn() {
    const id = elements.columnId.value;
    const payload = { name: elements.columnName.value.trim() };
    if (!payload.name) return;

    if (id) {
        const response = await request(`/api/columns/${encodeURIComponent(id)}`, {
            method: 'PATCH',
            body: JSON.stringify(payload),
        });
        const updated = normalizeColumn({ ...response.data, tasks: findColumn(id)?.tasks ?? [] });
        state.columns = state.columns.map((column) => (column.id === updated.id ? updated : column));
        return;
    }

    const response = await request(`/api/boards/${encodeURIComponent(boardId())}/columns`, {
        method: 'POST',
        body: JSON.stringify(payload),
    });
    const createdColumn = normalizeColumn({ ...response.data, tasks: [] });
    state.columns = [
        ...state.columns.filter((column) => column.id !== createdColumn.id),
        createdColumn,
    ].sort((left, right) => left.position - right.position);
}

async function deleteCurrentColumn() {
    const id = Number(elements.columnId.value);
    if (!id) return;

    const column = findColumn(id);
    if (!column) return;

    if (column.tasks.length > 0) {
        const targetColumn = state.columns.find((item) => item.id !== id);
        if (!targetColumn) {
            throw new Error('La board deve avere almeno una colonna.');
        }

        const action = await chooseColumnTaskAction(column.tasks.length);

        if (action === 'move') {
            for (const task of [...column.tasks]) {
                await moveTask(task.id, targetColumn.id, targetColumn.tasks.length);
            }
        } else if (action === 'delete') {
            for (const task of [...column.tasks]) {
                await request(`/api/tasks/${encodeURIComponent(task.id)}`, { method: 'DELETE' });
            }
            targetColumn.tasks = targetColumn.tasks.filter((task) => !column.tasks.includes(task));
            column.tasks = [];
        } else {
            throw new Error('Eliminazione annullata.');
        }
    }

    await request(`/api/columns/${id}`, { method: 'DELETE' });
    state.columns = state.columns.filter((column) => column.id !== id);
}

function chooseColumnTaskAction(taskCount) {
    elements.deleteColumnMessage.textContent = `La colonna contiene ${taskCount} task. Scegli se spostarle o eliminarle.`;
    openModal('deleteColumnModal');

    return new Promise((resolve) => {
        const finish = (action) => {
            closeModal('deleteColumnModal');
            resolve(action);
        };

        elements.moveColumnTasks.onclick = () => finish('move');
        elements.deleteColumnWithTasks.onclick = () => finish('delete');
        elements.deleteColumnModal.querySelectorAll('[data-close="deleteColumnModal"]').forEach((button) => {
            button.onclick = () => finish(null);
        });
    });
}

async function moveTask(taskId, targetColumnId, targetIndex) {
    const sourceColumn = findTaskColumn(taskId);
    const targetColumn = findColumn(targetColumnId);
    const task = findTask(taskId);
    if (!sourceColumn || !targetColumn || !task) return;

    sourceColumn.tasks = sourceColumn.tasks.filter((item) => item.id !== task.id);
    const boundedIndex = Math.max(0, Math.min(targetIndex, targetColumn.tasks.length));
    targetColumn.tasks.splice(boundedIndex, 0, task);
    updateTaskPositions(sourceColumn);
    updateTaskPositions(targetColumn);
    renderBoard();

    await request(`/api/tasks/${encodeURIComponent(task.id)}/move`, {
        method: 'POST',
        body: JSON.stringify({
            target_column_id: targetColumn.id,
            position: task.position,
        }),
    });
    await request(`/api/columns/${targetColumn.id}/tasks/reorder`, {
        method: 'POST',
        body: JSON.stringify({ task_ids: columnTaskIds(targetColumn) }),
    });

    if (sourceColumn.id !== targetColumn.id) {
        await request(`/api/columns/${sourceColumn.id}/tasks/reorder`, {
            method: 'POST',
            body: JSON.stringify({ task_ids: columnTaskIds(sourceColumn) }),
        });
    }
}

async function moveCategoryGroup(groupKey, sourceColumnId, targetColumnId, targetIndex) {
    const sourceColumn = findColumn(sourceColumnId);
    const targetColumn = findColumn(targetColumnId);
    if (!sourceColumn || !targetColumn) return;

    const movingTasks = sourceColumn.tasks.filter((task) => taskGroupKey(task) === groupKey);
    if (movingTasks.length === 0) return;

    sourceColumn.tasks = sourceColumn.tasks.filter((task) => !movingTasks.includes(task));
    const boundedIndex = Math.max(0, Math.min(targetIndex, targetColumn.tasks.length));
    targetColumn.tasks.splice(boundedIndex, 0, ...movingTasks);
    updateTaskPositions(sourceColumn);
    updateTaskPositions(targetColumn);
    renderBoard();

    for (const task of movingTasks) {
        await request(`/api/tasks/${encodeURIComponent(task.id)}/move`, {
            method: 'POST',
            body: JSON.stringify({
                target_column_id: targetColumn.id,
                position: task.position,
            }),
        });
    }

    await request(`/api/columns/${targetColumn.id}/tasks/reorder`, {
        method: 'POST',
        body: JSON.stringify({ task_ids: columnTaskIds(targetColumn) }),
    });

    if (sourceColumn.id !== targetColumn.id) {
        await request(`/api/columns/${sourceColumn.id}/tasks/reorder`, {
            method: 'POST',
            body: JSON.stringify({ task_ids: columnTaskIds(sourceColumn) }),
        });
    }
}

async function reorderColumns(columnId, targetIndex) {
    const currentIndex = state.columns.findIndex((column) => column.id === Number(columnId));
    if (currentIndex < 0) return;

    const [column] = state.columns.splice(currentIndex, 1);
    const boundedIndex = Math.max(0, Math.min(targetIndex, state.columns.length));
    state.columns.splice(boundedIndex, 0, column);
    state.columns.forEach((item, index) => {
        item.position = (index + 1) * 1000;
    });
    renderBoard();

    await request(`/api/boards/${encodeURIComponent(boardId())}/columns/reorder`, {
        method: 'POST',
        body: JSON.stringify({ column_ids: sortedColumnIds() }),
    });
}

function taskInsertionIndex(zone, clientY) {
    const tasks = [...zone.querySelectorAll('.task:not(.dragging)')];
    const target = tasks.find((task) => {
        const box = task.getBoundingClientRect();
        return clientY < box.top + box.height / 2;
    });

    return target ? tasks.indexOf(target) : tasks.length;
}

function columnInsertionIndex(clientX) {
    const columns = [...elements.boardColumns.querySelectorAll('.column:not(.column-dragging)')];
    const target = columns.find((column) => {
        const box = column.getBoundingClientRect();
        return clientX < box.left + box.width / 2;
    });

    return target ? columns.indexOf(target) : columns.length;
}

function bindDragEvents() {
    if (state.workspaceRole === 'viewer') return;
    document.querySelectorAll('.task').forEach((task) => {
        task.addEventListener('dragstart', (event) => {
            state.drag = {
                type: 'task',
                id: task.dataset.id,
            };
            task.classList.add('dragging');
            event.dataTransfer?.setData('text/plain', task.dataset.id);
            if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move';
        });
        task.addEventListener('dragend', () => {
            state.drag = null;
            task.classList.remove('dragging');
            document.querySelectorAll('.dropzone').forEach((zone) => zone.classList.remove('drag-over'));
            flushRealtimeRender();
        });
    });

    document.querySelectorAll('[data-drag-category]').forEach((header) => {
        header.addEventListener('dragstart', (event) => {
            state.drag = {
                type: 'category',
                key: header.dataset.dragCategory,
                sourceColumnId: Number(header.dataset.sourceColumn),
            };
            header.closest('.category-group')?.classList.add('group-dragging');
            event.dataTransfer?.setData('text/plain', header.dataset.dragCategory);
            if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move';
        });
        header.addEventListener('dragend', () => {
            state.drag = null;
            header.closest('.category-group')?.classList.remove('group-dragging');
            document.querySelectorAll('.dropzone').forEach((zone) => zone.classList.remove('drag-over'));
            flushRealtimeRender();
        });
    });

    document.querySelectorAll('.dropzone').forEach((zone) => {
        zone.addEventListener('dragover', (event) => {
            if (!state.drag || state.drag.type === 'column') return;
            event.preventDefault();
            zone.classList.add('drag-over');
        });
        zone.addEventListener('dragleave', (event) => {
            if (!zone.contains(event.relatedTarget)) zone.classList.remove('drag-over');
        });
        zone.addEventListener('drop', async (event) => {
            if (!state.drag || state.drag.type === 'column') return;
            event.preventDefault();
            event.stopPropagation();
            zone.classList.remove('drag-over');

            const drag = state.drag;
            state.drag = null;
            const targetColumnId = Number(zone.dataset.columnId);
            const targetIndex = taskInsertionIndex(zone, event.clientY);

            try {
                if (drag.type === 'task') {
                    await moveTask(drag.id, targetColumnId, targetIndex);
                } else {
                    await moveCategoryGroup(drag.key, drag.sourceColumnId, targetColumnId, targetIndex);
                }
                setStatus('Salvato');
            } catch (error) {
                setStatus(error.message, true);
                await loadBoard();
            }
        });
    });

    document.querySelectorAll('.column-header').forEach((header) => {
        const column = header.closest('.column');

        header.addEventListener('dragstart', (event) => {
            if (event.target.closest('button')) {
                event.preventDefault();
                return;
            }

            state.drag = {
                type: 'column',
                id: Number(column.dataset.columnId),
            };
            column.classList.add('column-dragging');
            event.dataTransfer?.setData('text/plain', `column:${column.dataset.columnId}`);
            if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move';
        });
        header.addEventListener('dragend', () => {
            state.drag = null;
            column.classList.remove('column-dragging');
            flushRealtimeRender();
        });
    });

}

function bindColumnDropEvents() {
    elements.boardColumns.addEventListener('dragover', (event) => {
        if (!state.drag || state.drag.type !== 'column') return;
        event.preventDefault();
    });

    elements.boardColumns.addEventListener('drop', async (event) => {
        if (!state.drag || state.drag.type !== 'column') return;
        event.preventDefault();
        const drag = state.drag;
        state.drag = null;

        try {
            await reorderColumns(drag.id, columnInsertionIndex(event.clientX));
            setStatus('Salvato');
        } catch (error) {
            setStatus(error.message, true);
            await loadBoard();
        }
    });
}

elements.openTaskModal.addEventListener('click', () => {
    resetTaskForm();
    openModal('taskModal');
});

elements.openCategoryModal.addEventListener('click', () => {
    resetCategoryForm();
    openModal('categoryModal');
});

elements.openColumnModal.addEventListener('click', () => {
    resetColumnForm();
    openModal('columnModal');
});

document.querySelectorAll('[data-close]').forEach((button) => {
    button.addEventListener('click', () => closeModal(button.dataset.close));
});

document.querySelectorAll('.modal-backdrop').forEach((backdrop) => {
    backdrop.addEventListener('click', (event) => {
        if (event.target === backdrop) closeModal(backdrop.id);
    });
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        document.querySelectorAll('.modal-backdrop.open').forEach((modal) => closeModal(modal.id));
    }
});

Object.entries(editableTaskFields).forEach(([field, element]) => {
    element.addEventListener(field === 'title' || field === 'description' ? 'input' : 'change', () => {
        trackTaskFieldChange(field);
    });
});

elements.taskColorPreset.addEventListener('change', (event) => {
    if (!event.target.value) return;
    setTaskColor(event.target.value);
});
elements.taskColor.addEventListener('input', (event) => {
    setTaskColor(event.target.value);
});
elements.taskColorText.addEventListener('input', (event) => {
    if (/^#[0-9a-f]{6}$/i.test(event.target.value)) {
        setTaskColor(event.target.value);
    } else {
        elements.taskColorPreset.value = '';
    }
});

elements.boardPresence.addEventListener('click', (event) => {
    if (!state.sharedWorkspace) return;

    if (event.target.closest('#boardPresencePopover')) return;

    elements.boardPresencePopover.hidden = !elements.boardPresencePopover.hidden;
});

elements.boardPresenceClose.addEventListener('click', (event) => {
    event.stopPropagation();
    elements.boardPresencePopover.hidden = true;
});

document.addEventListener('click', (event) => {
    if (!elements.boardPresence.contains(event.target)) {
        elements.boardPresencePopover.hidden = true;
    }
});

elements.categoryColorPreset.addEventListener('change', (event) => {
    if (!event.target.value) return;
    elements.categoryColor.value = event.target.value;
    elements.categoryColorText.value = event.target.value;
});
elements.categoryColor.addEventListener('input', (event) => {
    elements.categoryColorText.value = event.target.value;
    elements.categoryColorPreset.value = event.target.value;
});
elements.categoryColorText.addEventListener('input', (event) => {
    if (/^#[0-9a-f]{6}$/i.test(event.target.value)) {
        elements.categoryColor.value = event.target.value;
        elements.categoryColorPreset.value = event.target.value;
    } else {
        elements.categoryColorPreset.value = '';
    }
});

elements.taskForm.addEventListener('submit', async (event) => {
    event.preventDefault();

    try {
        await saveTask();
        closeModal('taskModal');
        renderBoard();
    } catch (error) {
        setStatus(error.message, true);
        await loadBoard();
    }
});

elements.deleteTask.addEventListener('click', async () => {
    try {
        await deleteCurrentTask();
        closeModal('taskModal');
        renderBoard();
    } catch (error) {
        setStatus(error.message, true);
        await loadBoard();
    }
});

elements.categoryForm.addEventListener('submit', async (event) => {
    event.preventDefault();

    try {
        await saveCategory();
        resetCategoryForm();
        renderBoard();
    } catch (error) {
        setStatus(error.message, true);
        await loadBoard();
    }
});

elements.columnForm.addEventListener('submit', async (event) => {
    event.preventDefault();

    try {
        await saveColumn();
        closeModal('columnModal');
        renderBoard();
    } catch (error) {
        setStatus(error.message, true);
        await loadBoard();
    }
});

elements.deleteColumn.addEventListener('click', async () => {
    try {
        await deleteCurrentColumn();
        closeModal('columnModal');
        renderBoard();
    } catch (error) {
        setStatus(error.message, true);
        await loadBoard();
    }
});

document.addEventListener('click', async (event) => {
    const editButton = event.target.closest('[data-edit]');
    if (editButton) {
        const task = findTask(editButton.dataset.edit);
        if (!task) return;
        editTask(task);
        openModal('taskModal');
        return;
    }

    const editCategoryButton = event.target.closest('[data-edit-category]');
    if (editCategoryButton) {
        const category = findCategory(editCategoryButton.dataset.editCategory);
        if (!category) return;
        editCategory(category);
        openModal('categoryModal');
        return;
    }

    const deleteCategoryButton = event.target.closest('[data-delete-category]');
    if (deleteCategoryButton) {
        try {
            await deleteCategory(deleteCategoryButton.dataset.deleteCategory);
            resetCategoryForm();
            renderBoard();
        } catch (error) {
            setStatus(error.message, true);
            await loadBoard();
        }
        return;
    }

    const editColumnButton = event.target.closest('[data-edit-column]');
    if (editColumnButton) {
        const column = findColumn(editColumnButton.dataset.editColumn);
        if (!column) return;
        editColumn(column);
        openModal('columnModal');
        return;
    }

});

const currentParams = new URLSearchParams(window.location.search);
const backParams = new URLSearchParams();
['workspace_id', 'return_folder', 'return_archived'].forEach((key) => {
    if (currentParams.has(key)) backParams.set(key, currentParams.get(key));
});
if (backParams.toString()) {
    elements.backToProjects.href = `/?${backParams.toString()}`;
}

bindColumnDropEvents();
loadBoard().finally(refreshIcons);
