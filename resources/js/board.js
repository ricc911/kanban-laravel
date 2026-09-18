import '../css/board.css';
import { activityDayKey, formatActivity, formatActivityDate, formatActivityDay, formatActivityDetails } from './activity-log';
import { subscribeToBoard, subscribeToBoardPresence, subscribeToUserRealtime } from './realtime';
import './pwa';

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
    workspaceMembers: [],
    assignedToMeOnly: false,
    dueFilter: 'all',
    dueTimer: null,
    pendingTaskAssigneeIds: new Set(),
    activities: [],
    activityOpenDay: null,
    editingSessionId: null,
    editingSessionTaskId: null,
    editingCleanupInterval: null,
    editingHeartbeat: null,
    remoteTaskEditors: new Map(),
    taskComments: [],
    taskCommentsTaskId: null,
    taskCommentsRequestToken: 0,
    editingCommentId: null,
    commentSubmitting: false,
    commentConfirmResolver: null,
    taskConfirmResolver: null,
    ai: {
        status: null,
        statusLoadedAt: 0,
        reasoning: 'medium',
        descriptionReasoning: 'low',
        loading: {},
        breakdown: null,
    },
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
    taskEditingIndicator: document.querySelector('#taskEditingIndicator'),
    deleteTask: document.querySelector('#deleteTask'),
    taskRealtimeStatus: null,
    taskAssigneeList: document.querySelector('#taskAssigneeList'),
    taskAssigneeControls: document.querySelector('#taskAssigneeControls'),
    taskAssigneeSelect: document.querySelector('#taskAssigneeSelect'),
    taskComments: document.querySelector('#taskComments'),
    taskCommentsStatus: document.querySelector('#taskCommentsStatus'),
    taskCommentForm: document.querySelector('#taskCommentForm'),
    taskCommentBody: document.querySelector('#taskCommentBody'),
    taskCommentSubmit: document.querySelector('#taskCommentSubmit'),
    commentConfirmModal: document.querySelector('#commentConfirmModal'),
    commentConfirmMessage: document.querySelector('#commentConfirmMessage'),
    commentConfirmOk: document.querySelector('#commentConfirmOk'),
    commentConfirmCancel: document.querySelector('#commentConfirmCancel'),
    commentConfirmCancelButton: document.querySelector('#commentConfirmCancelButton'),
    taskConfirmModal: document.querySelector('#taskConfirmModal'),
    taskConfirmOk: document.querySelector('#taskConfirmOk'),
    taskConfirmCancel: document.querySelector('#taskConfirmCancel'),
    taskConfirmCancelButton: document.querySelector('#taskConfirmCancelButton'),
    assignTaskMember: document.querySelector('#assignTaskMember'),
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
    taskAssignmentFilter: document.querySelector('#taskAssignmentFilter'),
    taskDueFilter: document.querySelector('#taskDueFilter'),
    boardActivityList: document.querySelector('#boardActivityList'),
    boardPresence: document.querySelector('#boardPresence'),
    boardPresenceStatus: document.querySelector('#boardPresenceStatus'),
    boardPresenceUsers: document.querySelector('#boardPresenceUsers'),
    boardPresenceCount: document.querySelector('#boardPresenceCount'),
    workspaceDeletedModal: document.querySelector('#workspaceDeletedModal'),
    boardPresencePopover: document.querySelector('#boardPresencePopover'),
    boardPresenceClose: document.querySelector('#boardPresenceClose'),
    boardPresenceList: document.querySelector('#boardPresenceList'),
    openAiModal: document.querySelector('#openAiModal'),
    aiModal: document.querySelector('#aiModal'),
    aiCredits: document.querySelector('#aiCredits'),
    aiStatusMessage: document.querySelector('#aiStatusMessage'),
    aiReasoning: document.querySelector('#aiReasoning'),
    aiBreakdownButton: document.querySelector('#aiBreakdownButton'),
    aiSummaryButton: document.querySelector('#aiSummaryButton'),
    aiAnalysisButton: document.querySelector('#aiAnalysisButton'),
    aiBreakdownFeature: document.querySelector('#aiBreakdownFeature'),
    aiObjective: document.querySelector('#aiObjective'),
    aiDesiredCount: document.querySelector('#aiDesiredCount'),
    aiGenerateBreakdown: document.querySelector('#aiGenerateBreakdown'),
    aiBreakdownResult: document.querySelector('#aiBreakdownResult'),
    aiSummaryResult: document.querySelector('#aiSummaryResult'),
    aiAnalysisResult: document.querySelector('#aiAnalysisResult'),
    aiMessage: document.querySelector('#aiMessage'),
    generateDescriptionAi: document.querySelector('#generateDescriptionAi'),
    aiDescriptionPreview: document.querySelector('#aiDescriptionPreview'),
};

function boardId() {
    return document.body.dataset.boardId;
}

function currentUserId() {
    return document.body.dataset.userId;
}

function normalizeAssignee(user) {
    return {
        id: String(user.id),
        name: user.name ?? 'Utente',
        last_name: user.last_name ?? '',
        username: user.username ?? '',
    };
}

function assigneeName(user, includeUsername = true) {
    const name = [user.name, user.last_name].filter(Boolean).join(' ') || 'Utente';

    return includeUsername && user.username ? `${name} (@${user.username})` : name;
}

function userInitials(user) {
    return [user.name, user.last_name]
        .filter(Boolean)
        .map((part) => part.trim()[0])
        .filter(Boolean)
        .slice(0, 2)
        .join('')
        .toUpperCase() || '?';
}

function newEditingSessionId() {
    if (window.crypto?.randomUUID) return window.crypto.randomUUID();

    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (character) => {
        const random = Math.floor(Math.random() * 16);
        const value = character === 'x' ? random : (random & 0x3) | 0x8;

        return value.toString(16);
    });
}

function taskEditorsFor(taskId) {
    const editors = state.remoteTaskEditors.get(String(taskId));

    return editors ? [...editors.values()] : [];
}

function renderTaskEditingIndicator() {
    const indicator = elements.taskEditingIndicator;
    if (!indicator) return;

    const editors = state.editingTaskId
        ? taskEditorsFor(state.editingTaskId).filter((editor) => editor.user.id !== String(currentUserId()))
        : [];
    indicator.replaceChildren();
    indicator.hidden = editors.length === 0;
    if (!editors.length) return;

    const names = editors.map((editor) => {
        const user = editor.user;
        const fullName = [user.name, user.last_name].filter(Boolean).join(' ') || 'Utente';
        return user.username ? `${fullName} (@${user.username})` : fullName;
    });
    const text = names.length === 1
        ? `${names[0]} sta modificando questa task`
        : `${names.slice(0, 2).join(' e ')}${names.length > 2 ? ` e altri ${names.length - 2}` : ''} stanno modificando questa task`;
    indicator.textContent = text;
}

function applyRemoteTaskEditingState(payload) {
    if (Number(payload?.board_id) !== Number(boardId()) || !payload?.task_id || !payload?.session_id || !payload?.user?.id) return;

    const taskId = String(payload.task_id);
    if (!state.remoteTaskEditors.has(taskId)) state.remoteTaskEditors.set(taskId, new Map());
    const editors = state.remoteTaskEditors.get(taskId);
    const sessionId = String(payload.session_id);
    if (payload.active) {
        editors.set(sessionId, { user: { ...payload.user, id: String(payload.user.id) }, lastSeen: Date.now() });
    } else {
        editors.delete(sessionId);
    }
    if (!editors.size) state.remoteTaskEditors.delete(taskId);
    renderTaskEditingIndicator();
}

function cleanupStaleTaskEditors() {
    const staleBefore = Date.now() - 50000;
    state.remoteTaskEditors.forEach((editors, taskId) => {
        editors.forEach((editor, sessionId) => {
            if (editor.lastSeen < staleBefore) editors.delete(sessionId);
        });
        if (!editors.size) state.remoteTaskEditors.delete(taskId);
    });
    renderTaskEditingIndicator();
}

function sendTaskEditingState(active, keepalive = false) {
    if (!state.editingSessionTaskId || !state.editingSessionId) return;

    request(`/api/tasks/${encodeURIComponent(state.editingSessionTaskId)}/editing-state`, {
        method: 'POST',
        body: JSON.stringify({ active, session_id: state.editingSessionId }),
        keepalive,
    }).catch((error) => {
        if (error.status === 403 && active) stopTaskEditing();
    });
}

function startTaskEditing(taskId) {
    if (state.workspaceRole === 'viewer') return;

    stopTaskEditing();
    state.editingSessionTaskId = String(taskId);
    state.editingSessionId = newEditingSessionId();
    sendTaskEditingState(true);
    state.editingHeartbeat = window.setInterval(() => {
        if (state.editingSessionTaskId && state.workspaceRole !== 'viewer') sendTaskEditingState(true);
        else stopTaskEditing();
    }, 20000);
}

function stopTaskEditing() {
    if (!state.editingSessionTaskId || !state.editingSessionId) return;

    window.clearInterval(state.editingHeartbeat);
    state.editingHeartbeat = null;
    sendTaskEditingState(false, true);
    state.editingSessionTaskId = null;
    state.editingSessionId = null;
}

state.editingCleanupInterval = window.setInterval(cleanupStaleTaskEditors, 5000);
window.addEventListener('pagehide', () => {
    stopTaskEditing();
    window.clearInterval(state.editingCleanupInterval);
});

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
        error.code = payload?.code ?? null;
        throw error;
    }

    return payload;
}

function aiErrorMessage(error) {
    const messages = {
        ai_disabled: 'AI non disponibile nel tuo piano.',
        ai_credits_exhausted: 'Hai esaurito i crediti AI disponibili per questo periodo.',
        ai_provider_not_configured: 'Il servizio AI non è configurato.',
        ai_provider_unavailable: 'Il servizio AI non è momentaneamente disponibile.',
        ai_provider_invalid_response: 'Non è stato possibile generare un risultato valido.',
        ai_provider_refused: 'La richiesta non può essere completata.',
        ai_request_already_processed: 'Questa richiesta è già stata elaborata.',
    };
    if (error.code && messages[error.code]) return messages[error.code];
    if (error.status === 429) return 'Troppe richieste AI. Riprova tra poco.';
    if (!error.status) return 'Errore di connessione. Riprova.';
    return 'Si è verificato un errore. Riprova.';
}

function setAiMessage(message = '', isError = false) {
    if (!elements.aiMessage) return;
    elements.aiMessage.textContent = message;
    elements.aiMessage.classList.toggle('is-error', isError);
}

function updateAiUsage(usage) {
    if (!usage || !state.ai.status) return;
    state.ai.status.remaining_credits = Number(usage.remaining_credits ?? state.ai.status.remaining_credits);
    state.ai.status.used_credits = Math.max(0, Number(state.ai.status.monthly_credits) - state.ai.status.remaining_credits);
    renderAiStatus();
}

function renderAiStatus() {
    const status = state.ai.status;
    if (!status || !elements.aiCredits) return;
    const remaining = Math.max(0, Number(status.remaining_credits ?? 0));
    const monthly = Math.max(0, Number(status.monthly_credits ?? 0));
    elements.aiCredits.textContent = remaining.toLocaleString('it-IT') + ' / ' + monthly.toLocaleString('it-IT');
    const unavailable = !status.enabled || !status.can_use || state.workspaceRole === 'viewer';
    [elements.aiReasoning, elements.aiBreakdownButton, elements.aiSummaryButton, elements.aiAnalysisButton, elements.aiGenerateBreakdown, document.querySelector('#aiApplyBreakdown')].forEach((control) => {
        if (control) control.disabled = unavailable;
    });
    if (elements.generateDescriptionAi) elements.generateDescriptionAi.hidden = state.workspaceRole === 'viewer' || !state.editingTaskId;
    if (!status.enabled) elements.aiStatusMessage.textContent = 'AI non disponibile nel tuo piano.';
    else if (!status.can_use || state.workspaceRole === 'viewer') elements.aiStatusMessage.textContent = 'Funzione disponibile solo per chi può modificare.';
    else elements.aiStatusMessage.textContent = '';
}

async function loadAiStatus(force = false) {
    if (!force && state.ai.status && Date.now() - state.ai.statusLoadedAt < 30000) {
        renderAiStatus();
        return;
    }
    try {
        state.ai.status = await request('/api/boards/' + encodeURIComponent(boardId()) + '/ai/status');
        state.ai.statusLoadedAt = Date.now();
        renderAiStatus();
    } catch (error) {
        setAiMessage(aiErrorMessage(error), true);
    }
}

function setAiLoading(feature, loading) {
    state.ai.loading[feature] = loading;
    const controls = {
        breakdown: elements.aiGenerateBreakdown,
        summary: elements.aiSummaryButton,
        analysis: elements.aiAnalysisButton,
        description: elements.generateDescriptionAi,
        applyBreakdown: document.querySelector('#aiApplyBreakdown'),
    };
    const button = controls[feature];
    if (button) {
        button.disabled = loading;
        if (loading) button.dataset.originalText = button.textContent;
        button.textContent = loading ? 'Generazione...' : (button.dataset.originalText || button.textContent);
    }
}

async function requestAi(feature, url, payload) {
    setAiMessage('');
    setAiLoading(feature, true);
    try {
        const response = await request(url, { method: 'POST', body: JSON.stringify({ request_id: newEditingSessionId(), ...payload }) });
        updateAiUsage(response.usage);
        return response;
    } catch (error) {
        setAiMessage(aiErrorMessage(error), true);
        throw error;
    } finally {
        setAiLoading(feature, false);
        renderAiStatus();
    }
}

function resetAiResults() {
    state.ai.breakdown = null;
    [elements.aiBreakdownResult, elements.aiSummaryResult, elements.aiAnalysisResult].forEach((element) => {
        if (element) { element.hidden = true; element.replaceChildren(); }
    });
}

function renderDescriptionPreview(text) {
    const preview = elements.aiDescriptionPreview;
    if (!preview) return;
    preview.replaceChildren();
    preview.hidden = !text;
    if (!text) return;
    const title = document.createElement('strong');
    title.textContent = 'Descrizione proposta';
    const body = document.createElement('p');
    body.textContent = text;
    const discard = document.createElement('button');
    discard.className = 'btn';
    discard.type = 'button';
    discard.textContent = 'Scarta';
    discard.onclick = () => renderDescriptionPreview('');
    const apply = document.createElement('button');
    apply.className = 'btn btn-primary';
    apply.type = 'button';
    apply.textContent = 'Usa descrizione';
    apply.onclick = () => {
        elements.taskDescription.value = text;
        elements.taskDescription.dispatchEvent(new Event('input', { bubbles: true }));
        renderDescriptionPreview('');
    };
    const actions = document.createElement('div');
    actions.className = 'ai-result-actions';
    actions.append(discard, apply);
    preview.append(title, body, actions);
}

function renderAiBreakdown(tasks) {
    const result = elements.aiBreakdownResult;
    result.replaceChildren();
    result.hidden = false;
    state.ai.breakdown = tasks.map((task, index) => ({ ...task, selected: true, key: String(index) }));
    const title = document.createElement('h3');
    title.textContent = 'Task proposte';
    const list = document.createElement('div');
    list.className = 'ai-breakdown-list';
    state.ai.breakdown.forEach((task) => {
        const item = document.createElement('label');
        item.className = 'ai-breakdown-item';
        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.checked = true;
        checkbox.dataset.breakdownKey = task.key;
        checkbox.onchange = () => { task.selected = checkbox.checked; };
        const content = document.createElement('span');
        const taskTitle = document.createElement('strong');
        taskTitle.textContent = task.title ?? 'Task senza titolo';
        const description = document.createElement('small');
        description.textContent = task.description ?? '';
        const priority = document.createElement('small');
        priority.textContent = task.priority ? 'Priorità: ' + ({ low: 'Bassa', medium: 'Media', high: 'Alta' }[task.priority] ?? task.priority) : 'Nessuna priorità';
        content.append(taskTitle, description, priority);
        item.append(checkbox, content);
        list.append(item);
    });
    const columnLabel = document.createElement('label');
    columnLabel.textContent = 'Colonna destinazione';
    const column = document.createElement('select');
    column.id = 'aiBreakdownColumn';
    state.columns.forEach((boardColumn) => {
        const option = document.createElement('option');
        option.value = boardColumn.id;
        option.textContent = boardColumn.name;
        column.append(option);
    });
    const apply = document.createElement('button');
    apply.className = 'btn btn-primary';
    apply.type = 'button';
    apply.id = 'aiApplyBreakdown';
    apply.textContent = 'Crea task selezionate';
    apply.onclick = () => applyAiBreakdown(column.value, apply);
    result.append(title, list, columnLabel, column, apply);
}

function renderAiSummary(data) {
    const result = elements.aiSummaryResult;
    result.replaceChildren();
    result.hidden = false;
    const title = document.createElement('h3');
    title.textContent = 'Riepilogo progetto';
    const overview = document.createElement('p');
    overview.textContent = data.overview ?? '';
    result.append(title, overview);
    appendAiList(result, 'Punti principali', data.highlights);
    appendAiList(result, 'Da tenere sotto controllo', data.attention_items);
}

function renderAiAnalysis(data) {
    const result = elements.aiAnalysisResult;
    result.replaceChildren();
    result.hidden = false;
    const title = document.createElement('h3');
    title.textContent = 'Analisi progetto';
    const summary = document.createElement('p');
    summary.textContent = data.executive_summary ?? '';
    result.append(title, summary);
    appendAiAnalysisList(result, 'Rischi', data.risks, true);
    appendAiAnalysisList(result, 'Priorità', data.priorities, false);
    appendAiList(result, 'Raccomandazioni', data.recommendations);
}

function appendAiList(parent, heading, values) {
    if (!Array.isArray(values) || !values.length) return;
    const title = document.createElement('h4');
    title.textContent = heading;
    const list = document.createElement('ul');
    values.forEach((value) => {
        const item = document.createElement('li');
        item.textContent = value;
        list.append(item);
    });
    parent.append(title, list);
}

function appendAiAnalysisList(parent, heading, values, withSeverity) {
    if (!Array.isArray(values) || !values.length) return;
    const title = document.createElement('h4');
    title.textContent = heading;
    const list = document.createElement('div');
    list.className = 'ai-analysis-list';
    values.forEach((value) => {
        const item = document.createElement('article');
        item.className = 'ai-analysis-item';
        const itemTitle = document.createElement('strong');
        itemTitle.textContent = (withSeverity && value.severity ? ({ low: 'Bassa', medium: 'Media', high: 'Alta' }[value.severity] + ': ') : '') + (value.title ?? '');
        const detail = document.createElement('p');
        detail.textContent = value.detail ?? value.reason ?? '';
        const references = document.createElement('small');
        const names = (value.task_ids ?? []).map((id) => findTask(id)?.title ?? '#' + id);
        references.textContent = names.length ? 'Task collegate: ' + names.join(', ') : '';
        item.append(itemTitle, detail, references);
        list.append(item);
    });
    parent.append(title, list);
}

async function generateTaskDescriptionWithAi() {
    if (!state.editingTaskId || state.workspaceRole === 'viewer') return;
    try {
        const response = await requestAi('description', '/api/tasks/' + encodeURIComponent(state.editingTaskId) + '/ai/generate-description', { reasoning_level: state.ai.descriptionReasoning });
        renderDescriptionPreview(response.data?.description ?? '');
    } catch (error) {
        // The inline AI message already contains the safe user-facing error.
    }
}

async function generateAiFeature(feature) {
    if (state.workspaceRole === 'viewer') return;
    try {
        if (feature === 'breakdown') {
            const objective = elements.aiObjective.value.trim();
            const desiredCount = Number(elements.aiDesiredCount.value);
            if (!objective || desiredCount < 3 || desiredCount > 10) {
                setAiMessage('Inserisci un obiettivo e un numero di task tra 3 e 10.', true);
                return;
            }
            const response = await requestAi('breakdown', '/api/boards/' + encodeURIComponent(boardId()) + '/ai/breakdown', { reasoning_level: state.ai.reasoning, objective, desired_count: desiredCount });
            renderAiBreakdown(response.data?.tasks ?? []);
        } else if (feature === 'summary') {
            const response = await requestAi('summary', '/api/boards/' + encodeURIComponent(boardId()) + '/ai/summary', { reasoning_level: state.ai.reasoning });
            renderAiSummary(response.data ?? {});
        } else {
            const response = await requestAi('analysis', '/api/boards/' + encodeURIComponent(boardId()) + '/ai/analysis', { reasoning_level: state.ai.reasoning });
            renderAiAnalysis(response.data ?? {});
        }
    } catch (error) {
        // Keep the board and task modal usable when AI fails.
    }
}

async function applyAiBreakdown(columnId, button) {
    const selected = (state.ai.breakdown ?? []).filter((task) => task.selected).map(({ title, description, priority }) => ({ title, description, priority }));
    if (!selected.length || !columnId || state.workspaceRole === 'viewer') return;
    setAiLoading('applyBreakdown', true);
    try {
        await request('/api/boards/' + encodeURIComponent(boardId()) + '/ai/breakdown/apply', { method: 'POST', body: JSON.stringify({ column_id: Number(columnId), tasks: selected }) });
        resetAiResults();
        setAiMessage('Task create correttamente.', false);
    } catch (error) {
        setAiMessage(error.message || 'Impossibile creare le task selezionate.', true);
    } finally {
        setAiLoading('applyBreakdown', false);
    }
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
        comments_count: task.comments_count === undefined ? undefined : Number(task.comments_count),
        assignees: Array.isArray(task.assignees) ? task.assignees.map(normalizeAssignee) : undefined,
    };
}

function applyTaskCommentsCount(taskId, count) {
    const task = findTask(taskId);
    if (task) task.comments_count = Math.max(0, Number(count ?? task.comments_count ?? 0));
}

function getTaskDueState(task) {
    if (!task?.due_at) return 'normal';
    const due = new Date(task.due_at); if (Number.isNaN(due.getTime())) return 'normal';
    const now = Date.now(); return due.getTime() < now ? 'overdue' : due.getTime() <= now + 86400000 ? 'due_soon' : 'normal';
}

function taskMatchesFilters(task) {
    return state.dueFilter === 'all' || getTaskDueState(task) === state.dueFilter;
}

function commentAuthorName(author) {
    if (!author) return 'Utente eliminato';
    const name = [author.name, author.last_name].filter(Boolean).join(' ') || 'Utente';
    return author.username ? `${name} (@${author.username})` : name;
}

function commentInitials(author) {
    return author ? presenceInitials([author.name, author.last_name].filter(Boolean).join(' ') || 'Utente') : '?';
}

function renderTaskComments() {
    if (!elements.taskComments) return;
    elements.taskComments.replaceChildren();
    const hasTask = Boolean(state.editingTaskId);
    const canComment = hasTask && state.workspaceRole !== 'viewer' && !state.taskModalDeleted;
    elements.taskCommentForm.hidden = !canComment;
    elements.taskCommentSubmit.disabled = state.commentSubmitting;
    if (!hasTask || state.taskCommentsTaskId !== state.editingTaskId) {
        const message = document.createElement('p'); message.className = 'task-comments-empty';
        message.textContent = hasTask ? 'Caricamento commenti...' : 'I commenti saranno disponibili dopo il salvataggio.';
        elements.taskComments.append(message); return;
    }
    if (!state.taskComments.length) {
        const message = document.createElement('p'); message.className = 'task-comments-empty';
        message.textContent = elements.taskCommentsStatus.textContent || 'Nessun commento.';
        elements.taskComments.append(message); return;
    }
    state.taskComments.forEach((comment) => {
        const item = document.createElement('article'); item.className = 'task-comment';
        const head = document.createElement('div'); head.className = 'task-comment-head';
        const avatar = document.createElement('span'); avatar.className = 'task-comment-avatar'; avatar.textContent = commentInitials(comment.author);
        const author = document.createElement('strong'); author.textContent = commentAuthorName(comment.author);
        const date = document.createElement('time'); date.textContent = comment.created_at ? new Date(comment.created_at).toLocaleString('it-IT', { dateStyle: 'short', timeStyle: 'short' }) : '';
        head.append(avatar, author, date);
        if (comment.edited) { const edited = document.createElement('span'); edited.className = 'task-comment-edited'; edited.textContent = 'Modificato'; head.append(edited); }
        item.append(head);
        if (state.editingCommentId === String(comment.id)) {
            const input = document.createElement('textarea'); input.className = 'task-comment-edit-input'; input.value = comment.body; input.maxLength = 5000;
            const actions = document.createElement('div'); actions.className = 'task-comment-actions';
            const save = document.createElement('button'); save.className = 'btn btn-primary'; save.type = 'button'; save.textContent = 'Salva'; save.onclick = () => updateTaskComment(comment.id, input.value);
            const cancel = document.createElement('button'); cancel.className = 'btn'; cancel.type = 'button'; cancel.textContent = 'Annulla'; cancel.onclick = () => { state.editingCommentId = null; renderTaskComments(); };
            actions.append(save, cancel); item.append(input, actions);
        } else {
            const body = document.createElement('p'); body.className = 'task-comment-body'; body.textContent = comment.body; item.append(body);
            const own = comment.author?.id && String(comment.author.id) === String(currentUserId());
            const canDelete = own || ['owner', 'admin'].includes(state.workspaceRole);
            const actions = document.createElement('div'); actions.className = 'task-comment-actions';
            if (own && state.workspaceRole !== 'viewer') { const edit = document.createElement('button'); edit.className = 'btn'; edit.type = 'button'; edit.textContent = 'Modifica'; edit.onclick = () => { state.editingCommentId = String(comment.id); renderTaskComments(); }; actions.append(edit); }
            if (canDelete) { const remove = document.createElement('button'); remove.className = 'btn btn-danger'; remove.type = 'button'; remove.textContent = 'Elimina'; remove.onclick = () => deleteTaskComment(comment.id); actions.append(remove); }
            if (actions.childElementCount) item.append(actions);
        }
        elements.taskComments.append(item);
    });
}

function upsertTaskComment(comment) {
    const normalized = { ...comment, id: String(comment.id) };
    state.taskComments = [...state.taskComments.filter((item) => String(item.id) !== normalized.id), normalized]
        .sort((left, right) => new Date(left.created_at) - new Date(right.created_at));
}

async function loadTaskComments(taskId) {
    const token = ++state.taskCommentsRequestToken; state.taskCommentsTaskId = String(taskId); state.taskComments = []; state.editingCommentId = null; elements.taskCommentsStatus.textContent = ''; renderTaskComments();
    try {
        const response = await request(`/api/tasks/${encodeURIComponent(taskId)}/comments`);
        if (token !== state.taskCommentsRequestToken || state.editingTaskId !== String(taskId)) return;
        state.taskComments = (response.comments ?? []).map((comment) => ({ ...comment, id: String(comment.id) })); renderTaskComments();
    } catch (error) {
        if (token !== state.taskCommentsRequestToken || state.editingTaskId !== String(taskId)) return;
        elements.taskCommentsStatus.textContent = 'Impossibile caricare i commenti.'; renderTaskComments();
    }
}

async function createTaskComment() {
    const body = elements.taskCommentBody.value.trim(); if (!body || !state.editingTaskId || state.commentSubmitting) return;
    state.commentSubmitting = true; renderTaskComments();
    try { const response = await request(`/api/tasks/${encodeURIComponent(state.editingTaskId)}/comments`, { method: 'POST', body: JSON.stringify({ body }) }); upsertTaskComment(response.data); applyTaskCommentsCount(state.editingTaskId, response.comments_count); elements.taskCommentBody.value = ''; renderTaskComments(); renderBoard(); }
    catch (error) { elements.taskCommentsStatus.textContent = error.message; renderTaskComments(); }
    finally { state.commentSubmitting = false; renderTaskComments(); }
}

async function updateTaskComment(commentId, body) {
    try { const response = await request(`/api/task-comments/${encodeURIComponent(commentId)}`, { method: 'PATCH', body: JSON.stringify({ body }) }); upsertTaskComment(response.data); state.editingCommentId = null; renderTaskComments(); }
    catch (error) { elements.taskCommentsStatus.textContent = error.message; renderTaskComments(); }
}

async function deleteTaskComment(commentId) {
    if (!await confirmCommentDeletion()) return;
    try { await request(`/api/task-comments/${encodeURIComponent(commentId)}`, { method: 'DELETE' }); state.taskComments = state.taskComments.filter((comment) => String(comment.id) !== String(commentId)); const task = findTask(state.editingTaskId); applyTaskCommentsCount(state.editingTaskId, Math.max(0, (task?.comments_count ?? 1) - 1)); renderTaskComments(); renderBoard(); }
    catch (error) { elements.taskCommentsStatus.textContent = error.message; renderTaskComments(); }
}

function closeTaskConfirmation(result) {
    elements.taskConfirmModal.hidden = true; elements.taskConfirmModal.classList.remove('open');
    const resolve = state.taskConfirmResolver; state.taskConfirmResolver = null; resolve?.(result);
}

function confirmTaskDeletion() {
    elements.taskConfirmModal.hidden = false; elements.taskConfirmModal.classList.add('open');
    return new Promise((resolve) => { state.taskConfirmResolver = resolve; });
}

function closeCommentConfirmation(result) {
    elements.commentConfirmModal.hidden = true;
    elements.commentConfirmModal.classList.remove('open');
    const resolve = state.commentConfirmResolver;
    state.commentConfirmResolver = null;
    resolve?.(result);
}

function confirmCommentDeletion() {
    elements.commentConfirmMessage.textContent = 'Eliminare questo commento?';
    elements.commentConfirmModal.hidden = false;
    elements.commentConfirmModal.classList.add('open');

    return new Promise((resolve) => {
        state.commentConfirmResolver = resolve;
    });
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
    const current = findTask(task.id);
    const normalized = normalizeTask(task);
    if (normalized.assignees === undefined) normalized.assignees = current?.assignees ?? [];
    if (normalized.comments_count === undefined) normalized.comments_count = current?.comments_count ?? 0;
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
    stopTaskEditing();
    state.taskCommentsRequestToken += 1;
    state.taskComments = [];
    state.taskCommentsTaskId = null;
    state.editingCommentId = null;
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
    renderTaskEditingIndicator();
    renderTaskComments();
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

    stopTaskEditing();
    state.taskModalDeleted = true;
    elements.taskSubmit.disabled = true;
    elements.deleteTask.disabled = true;
    renderDescriptionPreview('');
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

function applyRemoteTaskAssigneesChanged(payload) {
    if (Number(payload?.board_id) !== Number(boardId()) || !Array.isArray(payload?.assignees)) return;

    applyTaskAssignees(payload.task_id, payload.assignees);
}

function applyRemoteTaskCommentCreated(payload) {
    if (!isCurrentBoard(payload) || !payload.comment) return;
    applyTaskCommentsCount(payload.task_id, payload.comments_count);
    if (state.editingTaskId === String(payload.task_id)) { upsertTaskComment(payload.comment); renderTaskComments(); }
    renderAfterRealtimeUpdate();
}

function applyRemoteTaskCommentUpdated(payload) {
    if (!isCurrentBoard(payload) || !payload.comment) return;
    applyTaskCommentsCount(payload.task_id, payload.comments_count);
    if (state.editingTaskId === String(payload.task_id)) { upsertTaskComment(payload.comment); renderTaskComments(); }
}

function applyRemoteTaskCommentDeleted(payload) {
    if (!isCurrentBoard(payload)) return;
    applyTaskCommentsCount(payload.task_id, payload.comments_count);
    if (state.editingTaskId === String(payload.task_id)) { state.taskComments = state.taskComments.filter((comment) => String(comment.id) !== String(payload.comment_id)); renderTaskComments(); }
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
    state.remoteTaskEditors.delete(String(payload.task_id));
    if (state.editingTaskId === String(payload.task_id)) {
        state.taskCommentsRequestToken += 1;
        state.taskComments = [];
        state.taskCommentsTaskId = null;
    }
    if (state.editingTaskId === String(payload.task_id)) {
        markTaskDeletedRemotely();
    }
    renderTaskEditingIndicator();
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

function redirectToPersonalWorkspace() {
    request('/api/workspaces')
        .then((response) => {
            const personalWorkspace = (response.data ?? []).find((workspace) => workspace.type === 'personal');
            const destination = personalWorkspace?.id
                ? `/dashboard?workspace_id=${encodeURIComponent(personalWorkspace.id)}`
                : '/dashboard';
            window.location.assign(destination);
        })
        .catch(() => window.location.assign('/dashboard'));
}

function handleRemoteBoardAccessRemoved(payload, redirect = false) {
    if (!state.board || Number(state.board.workspace_id) !== Number(payload?.workspace?.id ?? payload?.workspace_id)) return;

    if (redirect) redirectToPersonalWorkspace();
    stopTaskEditing();
    state.remoteTaskEditors.clear();
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
    if (state.workspaceRole === 'viewer') stopTaskEditing();
    renderBoard();
    renderTaskComments();
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
        editingStateChanged: applyRemoteTaskEditingState,
        assigneesChanged: applyRemoteTaskAssigneesChanged,
        commentCreated: applyRemoteTaskCommentCreated,
        commentUpdated: applyRemoteTaskCommentUpdated,
        commentDeleted: applyRemoteTaskCommentDeleted,
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
        workspaceAccessRemoved: (payload) => handleRemoteBoardAccessRemoved(payload, true),
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

    const pad = (part) => String(part).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
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
            priority.className = `task-priority task-priority-${task.priority}`;
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

    if (task.due_at) {
        const due = document.createElement('span');
        due.className = `task-due-badge task-due-${getTaskDueState(task)}`;
        due.textContent = getTaskDueState(task) === 'overdue' ? 'Scaduta' : getTaskDueState(task) === 'due_soon' ? 'In scadenza' : formatDate(task.due_at);
        article.append(due);
    }

    const assignees = task.assignees ?? [];
    if (assignees.length) {
        const assigneeBadges = document.createElement('div');
        assigneeBadges.className = 'task-assignee-badges';
        assignees.slice(0, 3).forEach((assignee) => {
            const badge = document.createElement('span');
            badge.className = 'task-assignee-avatar';
            badge.textContent = userInitials(assignee);
            badge.title = assigneeName(assignee);
            badge.setAttribute('aria-label', assigneeName(assignee));
            assigneeBadges.append(badge);
        });
        if (assignees.length > 3) {
            const more = document.createElement('span');
            more.className = 'task-assignee-avatar task-assignee-more';
            more.textContent = `+${assignees.length - 3}`;
            more.title = `${assignees.length} assegnatari`;
            assigneeBadges.append(more);
        }
        article.append(assigneeBadges);
    }

    if (task.comments_count > 0) {
        const comments = document.createElement('span');
        comments.className = 'task-comment-count';
        comments.append(icon('message-circle'));
        comments.append(document.createTextNode(String(task.comments_count)));
        comments.title = `${task.comments_count} commenti`;
        article.append(comments);
    }

    return article;
}

function renderTaskAssignees(task) {
    const list = elements.taskAssigneeList;
    const controls = elements.taskAssigneeControls;
    const select = elements.taskAssigneeSelect;
    if (!list || !controls || !select) return;

    const assigned = task?.assignees ?? [...state.pendingTaskAssigneeIds]
        .map((id) => state.workspaceMembers.find((member) => String(member.id) === String(id)))
        .filter(Boolean);

    list.replaceChildren();
    assigned.forEach((assignee) => {
        const row = document.createElement('div');
        row.className = 'task-assignee-row';
        const identity = document.createElement('span');
        identity.textContent = assigneeName(assignee);
        row.append(identity);
        if (state.workspaceRole !== 'viewer') {
            const remove = document.createElement('button');
            remove.className = 'task-assignee-remove';
            remove.type = 'button';
            remove.textContent = 'Rimuovi';
            remove.setAttribute('aria-label', `Rimuovi ${assigneeName(assignee, false)} dagli assegnatari`);
            remove.onclick = async () => {
                if (!task?.id) {
                    state.pendingTaskAssigneeIds.delete(String(assignee.id));
                    renderTaskAssignees(null);
                    return;
                }
                try {
                    await updateTaskAssignee(task.id, assignee.id, false);
                } catch (error) {
                    setStatus(error.message, true);
                }
            };
            row.append(remove);
        }
        list.append(row);
    });

    select.replaceChildren();
    const available = state.workspaceMembers.filter((member) => !assigned.some((assignee) => String(assignee.id) === String(member.id)));
    available.forEach((member) => {
        const option = document.createElement('option');
        option.value = member.id;
        option.textContent = assigneeName(member);
        select.append(option);
    });
    controls.hidden = state.workspaceRole === 'viewer' || available.length === 0;
}

async function updateTaskAssignee(taskId, userId, active) {
    const url = `/api/tasks/${encodeURIComponent(taskId)}/assignees${active ? '' : `/${encodeURIComponent(userId)}`}`;
    const response = await request(url, {
        method: active ? 'POST' : 'DELETE',
        ...(active ? { body: JSON.stringify({ user_id: Number(userId) }) } : {}),
    });
    applyTaskAssignees(taskId, response.data?.assignees ?? []);
}

function applyTaskAssignees(taskId, assignees) {
    const task = findTask(taskId);
    if (!task) return;
    task.assignees = assignees.map(normalizeAssignee);
    if (state.editingTaskId === String(taskId)) renderTaskAssignees(task);
    renderBoard();
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
    const visibleTasks = (state.assignedToMeOnly
        ? column.tasks.filter((task) => (task.assignees ?? []).some((assignee) => String(assignee.id) === String(currentUserId())))
        : column.tasks).filter(taskMatchesFilters);
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
    count.textContent = String(visibleTasks.length);

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

    if (visibleTasks.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'empty';
        empty.textContent = 'Trascina qui un evento o una categoria';
        dropzone.append(empty);
    } else {
        const groupKeys = [...new Set(visibleTasks.map(taskGroupKey))];
        const orderedKeys = [
            ...state.categories.map((category) => category.id).filter((id) => groupKeys.includes(id)),
            ...(groupKeys.includes(UNCATEGORIZED) ? [UNCATEGORIZED] : []),
        ];

        orderedKeys.forEach((key) => {
            const tasks = visibleTasks.filter((task) => taskGroupKey(task) === key);
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
    [elements.openColumnModal, elements.openCategoryModal, elements.openTaskModal, elements.openAiModal].forEach((button) => { if (button) button.hidden = viewer; });
    renderAiStatus();

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
    renderTaskAssignees(state.editingTaskId ? findTask(state.editingTaskId) : null);
    renderCategorySelect();
    renderCategoryList();
    setStatus('Salvato');
    bindDragEvents();
    refreshIcons();
}

function setError(error) {
    window.clearInterval(state.dueTimer);
    state.dueTimer = null;
    const messageByStatus = {
        401: 'Sessione scaduta. Effettua di nuovo l accesso.',
        403: 'Non hai accesso a questa board.',
        404: 'Board non trovata.',
        422: error.message,
    };

    stopTaskEditing();
    state.remoteTaskEditors.clear();
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
        state.workspaceMembers = (board.workspace_members ?? []).map((member) => ({
            ...normalizeAssignee(member),
            role: member.role ?? 'member',
        }));
        state.categories = (board.categories ?? [])
            .map(normalizeCategory)
            .sort((left, right) => left.position - right.position);
        state.columns = (board.columns ?? [])
            .map(normalizeColumn)
            .sort((left, right) => left.position - right.position);

        renderBoard();
        subscribeToCurrentBoard();
        const deepLinkedTaskId = new URLSearchParams(window.location.search).get('task');
        const deepLinkedTask = deepLinkedTaskId ? findTask(deepLinkedTaskId) : null;
        if (deepLinkedTask) {
            editTask(deepLinkedTask);
            openModal('taskModal');
        }
    } catch (error) {
        setError(error);
    }
}

function openModal(id) {
    document.getElementById(id)?.classList.add('open');
}

function closeModal(id) {
    const modal = document.getElementById(id);
    modal?.classList.remove('open');
    if (id === 'aiModal' && modal) modal.hidden = true;
    if (id === 'commentConfirmModal' && state.commentConfirmResolver) closeCommentConfirmation(false);
    if (id === 'taskConfirmModal' && state.taskConfirmResolver) closeTaskConfirmation(false);
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
    state.pendingTaskAssigneeIds.clear();
    elements.taskForm.reset();
    elements.taskId.value = '';
    elements.taskModalTitle.textContent = 'Nuovo evento';
    elements.deleteTask.style.display = 'none';
    setTaskColor(DEFAULT_TASK_COLOR);
    elements.categorySelect.value = '';
    elements.taskForm.dataset.columnId = state.columns[0]?.id ?? '';
    renderTaskAssignees(null);
    renderDescriptionPreview('');
    if (elements.generateDescriptionAi) elements.generateDescriptionAi.hidden = true;
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
    renderDescriptionPreview('');
    if (elements.generateDescriptionAi) elements.generateDescriptionAi.hidden = state.workspaceRole === 'viewer';
    startTaskEditing(task.id);
    renderTaskAssignees(task);
    loadTaskComments(task.id);
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
    for (const userId of state.pendingTaskAssigneeIds) {
        await updateTaskAssignee(created.id, userId, true);
    }
    state.pendingTaskAssigneeIds.clear();
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
    if (state.workspaceRole === 'viewer' || state.assignedToMeOnly || state.dueFilter !== 'all') return;
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

elements.openAiModal.addEventListener('click', async () => {
    if (state.workspaceRole === 'viewer') return;
    resetAiResults();
    elements.aiModal.hidden = false;
    elements.aiModal.classList.add('open');
    await loadAiStatus();
});

elements.aiReasoning.addEventListener('change', (event) => {
    state.ai.reasoning = event.target.value;
});
elements.aiBreakdownButton.addEventListener('click', () => {
    elements.aiBreakdownFeature.hidden = !elements.aiBreakdownFeature.hidden;
});
elements.aiSummaryButton.addEventListener('click', () => generateAiFeature('summary'));
elements.aiAnalysisButton.addEventListener('click', () => generateAiFeature('analysis'));
elements.aiGenerateBreakdown.addEventListener('click', () => generateAiFeature('breakdown'));
elements.generateDescriptionAi.addEventListener('click', generateTaskDescriptionWithAi);

elements.assignTaskMember.addEventListener('click', async () => {
    const task = findTask(state.editingTaskId);
    const userId = elements.taskAssigneeSelect.value;
    if (!userId) return;
    if (!task) {
        state.pendingTaskAssigneeIds.add(String(userId));
        renderTaskAssignees(null);
        return;
    }
    try {
        await updateTaskAssignee(task.id, userId, true);
    } catch (error) {
        setStatus(error.message, true);
    }
});

elements.taskCommentSubmit.addEventListener('click', createTaskComment);
elements.commentConfirmOk.addEventListener('click', () => closeCommentConfirmation(true));
elements.commentConfirmCancel.addEventListener('click', () => closeCommentConfirmation(false));
elements.commentConfirmCancelButton.addEventListener('click', () => closeCommentConfirmation(false));
elements.taskConfirmOk.addEventListener('click', () => closeTaskConfirmation(true));
elements.taskConfirmCancel.addEventListener('click', () => closeTaskConfirmation(false));
elements.taskConfirmCancelButton.addEventListener('click', () => closeTaskConfirmation(false));

elements.openCategoryModal.addEventListener('click', () => {
    resetCategoryForm();
    openModal('categoryModal');
});

elements.openColumnModal.addEventListener('click', () => {
    resetColumnForm();
    openModal('columnModal');
});

elements.taskAssignmentFilter.addEventListener('change', (event) => {
    state.assignedToMeOnly = event.target.value === 'mine';
    renderBoard();
});

elements.taskDueFilter.addEventListener('change', (event) => {
    state.dueFilter = event.target.value;
    renderBoard();
});

state.dueTimer = window.setInterval(() => {
    if (state.board && state.dueFilter !== 'all') renderBoard();
}, 60000);

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
    if (!await confirmTaskDeletion()) return;
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
    elements.backToProjects.href = `/dashboard?${backParams.toString()}`;
}

bindColumnDropEvents();
loadBoard().finally(refreshIcons);
