function quoted(value) {
    return value ? `"${value}"` : '';
}

function location(metadata, prefix) {
    const from = metadata[`${prefix}_from_name`] ?? metadata[`from_${prefix}_name`];
    const to = metadata[`${prefix}_to_name`] ?? metadata[`to_${prefix}_name`];
    if (!from && !to) return '';
    return ` da ${quoted(from ?? 'Root')} → ${quoted(to ?? 'Root')}`;
}

export function formatActivity(activity) {
    const metadata = activity.metadata ?? {};
    const name = metadata.task_title ?? metadata.category_name ?? metadata.column_name ?? metadata.board_name ?? metadata.folder_name ?? '';
    const messages = {
        'task.created': `ha creato ${quoted(name)}`,
        'task.updated': `ha modificato ${quoted(name)}`,
        'task.moved': `ha spostato ${quoted(name)} da ${quoted(metadata.from_column_name ?? 'Root')} → ${quoted(metadata.to_column_name ?? 'Root')}`,
        'task.deleted': `ha eliminato ${quoted(name)}`,
        'category.created': `ha creato la categoria ${quoted(name)}`,
        'category.updated': `ha modificato la categoria ${quoted(name)}`,
        'category.deleted': `ha eliminato la categoria ${quoted(name)}`,
        'column.created': `ha creato la colonna ${quoted(name)}`,
        'column.updated': `ha modificato la colonna ${quoted(name)}`,
        'column.deleted': `ha eliminato la colonna ${quoted(name)}`,
        'board.created': `ha creato il progetto ${quoted(name)}`,
        'board.updated': `ha modificato il progetto ${quoted(name)}`,
        'board.moved': `ha spostato il progetto ${quoted(name)}${location(metadata, 'folder')}`,
        'board.archived': `ha archiviato il progetto ${quoted(name)}`,
        'board.restored': `ha ripristinato il progetto ${quoted(name)}`,
        'board.deleted': `ha eliminato il progetto ${quoted(name)}`,
        'folder.created': `ha creato la cartella ${quoted(name)}`,
        'folder.updated': `ha modificato la cartella ${quoted(name)}`,
        'folder.moved': `ha spostato la cartella ${quoted(name)}${location(metadata, 'parent')}`,
        'folder.archived': `ha archiviato la cartella ${quoted(name)}`,
        'folder.restored': `ha ripristinato la cartella ${quoted(name)}`,
        'folder.deleted': `ha eliminato la cartella ${quoted(name)}`,
        'workspace.created': `ha creato il workspace ${quoted(metadata.workspace_name ?? '')}`,
        'workspace.updated': 'ha modificato il workspace',
        'workspace.member_invited': `ha invitato ${metadata.email ?? 'un membro'} nel workspace`,
        'workspace.member_joined': 'è entrato nel workspace',
        'workspace.member_removed': `ha rimosso ${metadata.member_name ?? 'un membro'} dal workspace`,
        'workspace.member_left': 'ha lasciato il workspace',
        'workspace.invitation_rejected': 'ha rifiutato l’invito al workspace',
    };

    return messages[activity.action] ?? 'ha effettuato una modifica';
}

export function formatActivityDate(value) {
    return new Intl.DateTimeFormat('it-IT', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value));
}

export function activityDayKey(value) {
    const date = new Date(value);
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

export function formatActivityDay(value) {
    const date = new Date(`${value}T12:00:00`);
    const today = activityDayKey(new Date());
    const yesterday = activityDayKey(new Date(Date.now() - 86400000));
    if (value === today) return 'Oggi';
    if (value === yesterday) return 'Ieri';
    return new Intl.DateTimeFormat('it-IT', { dateStyle: 'full' }).format(date);
}

const detailLabels = { title: 'Titolo', description: 'Descrizione', priority: 'Priorità', due_at: 'Scadenza', category: 'Categoria', name: 'Nome', color: 'Colore' };
const priorities = { low: 'Bassa', medium: 'Media', high: 'Alta' };

function detailValue(field, value) {
    if (field === 'category') return value?.name ?? 'Nessuna categoria';
    if (field === 'priority') return priorities[value] ?? value ?? 'Nessuna';
    if (field === 'due_at') return value ? new Intl.DateTimeFormat('it-IT').format(new Date(value)) : 'Nessuna scadenza';
    if (value === null || value === undefined || value === '') return 'Nessuno';
    const text = String(value);
    return field === 'description' && text.length > 140 ? `${text.slice(0, 140)}…` : text;
}

export function formatActivityDetails(activity) {
    return Object.entries(activity.metadata?.changes ?? {}).map(([field, change]) => ({ label: detailLabels[field] ?? field, oldValue: detailValue(field, change.old), newValue: detailValue(field, change.new) }));
}
