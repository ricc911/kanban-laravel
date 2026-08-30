import './echo';

export function subscribeToBoard(boardId, handlers) {
    const echo = window.Echo;
    if (!echo) {
        return () => {};
    }

    try {
        const channel = echo.private(`board.${boardId}`);

        const events = {
            'task.created': handlers.created,
            'task.updated': handlers.updated,
            'task.moved': handlers.moved,
            'task.deleted': handlers.deleted,
            'task.editing_state_changed': handlers.editingStateChanged,
            'task.assignees_changed': handlers.assigneesChanged,
            'tasks.reordered': handlers.reordered,
            'board.updated': handlers.boardUpdated,
            'board.archived': handlers.boardArchived,
            'board.restored': handlers.boardRestored,
            'board.deleted': handlers.boardDeleted,
            'category.created': handlers.categoryCreated,
            'category.updated': handlers.categoryUpdated,
            'category.deleted': handlers.categoryDeleted,
            'column.created': handlers.columnCreated,
            'column.updated': handlers.columnUpdated,
            'column.deleted': handlers.columnDeleted,
            'columns.reordered': handlers.columnsReordered,
            'activity.logged': handlers.activityLogged,
        };
        Object.entries(events).forEach(([eventName, handler]) => {
            if (typeof handler === 'function') {
                channel.listen(`.${eventName}`, handler);
            }
        });
        channel.error((error) => {
            console.warn('Canale realtime board non disponibile.', error);
            handlers.error?.(error);
        });

        return () => echo.leave(`board.${boardId}`);
    } catch (error) {
        console.warn('Impossibile sottoscrivere il canale realtime della board.', error);

        return () => {};
    }
}

function subscribeToPrivateChannel(channelName, handlers, errorMessage) {
    const echo = window.Echo;
    if (!echo) {
        handlers.error?.(new Error('Echo non disponibile.'));

        return () => {};
    }

    try {
        const channel = echo.private(channelName);

        Object.entries(handlers.events ?? {}).forEach(([eventName, handler]) => {
            if (typeof handler === 'function') {
                channel.listen(`.${eventName}`, handler);
            }
        });
        channel.error((error) => {
            console.warn(errorMessage, error);
            handlers.error?.(error);
        });

        return () => echo.leave(channelName);
    } catch (error) {
        console.warn(errorMessage, error);
        handlers.error?.(error);

        return () => {};
    }
}

export function subscribeToWorkspaceRealtime(workspaceId, handlers) {
    return subscribeToPrivateChannel(
        `workspace.${workspaceId}`,
        {
            ...handlers,
            events: {
                'folder.created': handlers.folderCreated,
                'folder.updated': handlers.folderUpdated,
                'folder.moved': handlers.folderMoved,
                'folder.archived': handlers.folderArchived,
                'folder.restored': handlers.folderRestored,
                'folder.deleted': handlers.folderDeleted,
                'board.created': handlers.boardCreated,
                'board.updated': handlers.boardUpdated,
                'board.moved': handlers.boardMoved,
                'board.archived': handlers.boardArchived,
                'board.restored': handlers.boardRestored,
                'board.deleted': handlers.boardDeleted,
                'workspace.member_joined': handlers.memberJoined,
                'workspace.member_removed': handlers.memberRemoved,
                'workspace.member_left': handlers.memberLeft,
                'workspace.member_role_updated': handlers.memberRoleUpdated,
                'workspace.updated': handlers.workspaceUpdated,
                'activity.logged': handlers.activityLogged,
            },
        },
        'Canale realtime workspace non disponibile.',
    );
}

export function subscribeToUserRealtime(userId, handlers) {
    return subscribeToPrivateChannel(
        `user.${userId}`,
        {
            ...handlers,
            events: {
                'invitation.created': handlers.invitationCreated,
                'invitation.accepted': handlers.invitationAccepted,
                'invitation.rejected': handlers.invitationRejected,
                'invitation.pending.created': handlers.pendingInvitationCreated,
                'invitation.pending.removed': handlers.pendingInvitationRemoved,
                'workspace.created': handlers.workspaceCreated,
                'workspace.available': handlers.workspaceAvailable,
                'workspace.access_removed': handlers.workspaceAccessRemoved,
                'workspace.deleted': handlers.workspaceDeleted,
                'workspace.role_updated': handlers.workspaceRoleUpdated,
            },
        },
        'Canale realtime utente non disponibile.',
    );
}

export function subscribeToBoardPresence(boardId, handlers) {
    const echo = window.Echo;
    if (!echo) {
        handlers.error?.(new Error('Echo non disponibile.'));

        return () => {};
    }

    try {
        const channel = echo.join(`board-presence.${boardId}`);

        channel.here(handlers.here);
        channel.joining(handlers.joining);
        channel.leaving(handlers.leaving);
        channel.error((error) => {
            console.warn('Presence board non disponibile.', error);
            handlers.error?.(error);
        });

        return () => echo.leave(`board-presence.${boardId}`);
    } catch (error) {
        console.warn('Impossibile sottoscrivere la presence della board.', error);
        handlers.error?.(error);

        return () => {};
    }
}
