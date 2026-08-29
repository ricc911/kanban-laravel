import './echo';

export function subscribeToBoard(boardId, handlers) {
    const echo = window.Echo;
    if (!echo) {
        return () => {};
    }

    try {
        const channel = echo.private(`board.${boardId}`);

        channel.listen('.task.created', handlers.created);
        channel.listen('.task.updated', handlers.updated);
        channel.listen('.task.moved', handlers.moved);
        channel.listen('.task.deleted', handlers.deleted);
        channel.listen('.tasks.reordered', handlers.reordered);
        channel.error((error) => {
            console.warn('Canale realtime board non disponibile.', error);
        });

        return () => echo.leave(`board.${boardId}`);
    } catch (error) {
        console.warn('Impossibile sottoscrivere il canale realtime della board.', error);

        return () => {};
    }
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
