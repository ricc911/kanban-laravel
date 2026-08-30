<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Task Board</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js" defer></script>
    @vite(['resources/css/app.css', 'resources/js/board.js'])
</head>
<body data-board-id="{{ $board }}" data-user-id="{{ auth()->id() }}">
    <header class="topbar">
        <div class="brand">
            <div class="brand-badge">KB</div>
            <div>
                <div class="brand-title">Task Board</div>
                <div class="brand-subtitle">To do &middot; Doing &middot; Done</div>
            </div>
        </div>

        <div class="toolbar">
            <div class="board-presence" id="boardPresence" aria-label="Utenti online" hidden>
                <span class="board-presence-status"><span class="board-presence-dot" aria-hidden="true"></span><span id="boardPresenceStatus">Online</span></span>
                <div class="board-presence-users" id="boardPresenceUsers"></div>
                <span class="board-presence-count" id="boardPresenceCount">0</span>
                <div class="board-presence-popover" id="boardPresencePopover" hidden>
                    <div class="board-presence-popover-head">
                        <strong>Persone attive</strong>
                        <button class="board-presence-close" id="boardPresenceClose" type="button" aria-label="Chiudi elenco persone attive">&times;</button>
                    </div>
                    <div id="boardPresenceList"></div>
                </div>
            </div>
            <a class="btn home-link" data-back-to-projects href="/">&larr; Progetti</a>
            <button class="btn" type="button" id="openColumnModal">+ Colonna</button>
            <button class="btn btn-category" type="button" id="openCategoryModal">+ Categoria</button>
            <button class="btn btn-primary" type="button" id="openTaskModal">+ Evento</button>
            <button class="btn" type="button" id="openActivityModal">Cronologia</button>
            <select class="board-filter" id="taskAssignmentFilter" aria-label="Filtra le task per assegnatario">
                <option value="all">Tutte</option>
                <option value="mine">Assegnate a me</option>
            </select>
            <select class="board-filter" id="taskDueFilter" aria-label="Filtra le task per scadenza">
                <option value="all">Tutte le scadenze</option>
                <option value="due_soon">In scadenza</option>
                <option value="overdue">Scadute</option>
            </select>
        </div>
    </header>

    <div class="modal-backdrop" id="activityModal">
        <div class="modal activity-modal" role="dialog" aria-modal="true" aria-labelledby="activityModalTitle">
            <div class="modal-head"><h2 id="activityModalTitle">Cronologia</h2><button class="close" type="button" data-close="activityModal">&times;</button></div>
            <div class="modal-body"><div id="boardActivityList"></div></div>
        </div>
    </div>

    <div class="modal-backdrop" id="workspaceDeletedModal" hidden>
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="workspaceDeletedTitle">
            <div class="modal-head"><h2 id="workspaceDeletedTitle">Workspace eliminato</h2></div>
            <div class="modal-body">
                <p>Il workspace è stato eliminato dal proprietario. Questa board non è più disponibile.</p>
                <div class="modal-actions"><a class="btn btn-primary" href="/">Torna alla dashboard</a></div>
            </div>
        </div>
    </div>

    <main class="page">
        <div class="page-head">
            <div>
                <h1 class="page-title" id="currentBoardTitle">Board attivit&agrave;</h1>
                <p class="page-note" id="boardDescription" hidden></p>
                <p class="page-note">Gli eventi sono raggruppati per categoria. Trascina una card o l&rsquo;intero gruppo tra le colonne. <span id="saveStatus" class="save-status">Connessione al database...</span></p>
            </div>
        </div>

        <section class="board" id="boardColumns"></section>
    </main>

    <div class="modal-backdrop" id="taskModal">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="taskModalTitle">
            <div class="modal-head">
                <h2 id="taskModalTitle">Nuovo evento</h2>
                <button class="close" type="button" data-close="taskModal">&times;</button>
            </div>

            <form class="modal-body" id="taskForm">
                <input type="hidden" id="taskId">
                <p class="task-editing-indicator" id="taskEditingIndicator" hidden aria-live="polite"></p>

                <div class="field">
                    <label for="title">Titolo</label>
                    <input id="title" maxlength="80" required placeholder="Es. Preparare preventivo">
                </div>

                <div class="field">
                    <label for="description">Descrizione</label>
                    <textarea id="description" maxlength="500" placeholder="Scrivi una breve descrizione..."></textarea>
                </div>

                <div class="field">
                    <label for="category">Categoria</label>
                    <select id="category"></select>
                    <p class="hint">Puoi creare nuove categorie dal pulsante &ldquo;+ Categoria&rdquo;.</p>
                </div>

                <div class="field task-assignees-field">
                    <label>Assegnatari</label>
                    <div id="taskAssigneeList" class="task-assignee-list"></div>
                    <div id="taskAssigneeControls" class="task-assignee-controls" hidden>
                        <select id="taskAssigneeSelect" aria-label="Seleziona assegnatario"></select>
                        <button type="button" class="btn" id="assignTaskMember">Assegna</button>
                    </div>
                </div>

                <section class="field task-comments-field" aria-labelledby="taskCommentsTitle">
                    <div class="task-comments-heading">
                        <label id="taskCommentsTitle">Commenti</label>
                        <span class="hint" id="taskCommentsStatus" aria-live="polite"></span>
                    </div>
                    <div id="taskComments" class="task-comments-list" aria-live="polite"></div>
                    <div id="taskCommentForm" class="task-comment-form" hidden>
                        <label class="sr-only" for="taskCommentBody">Nuovo commento</label>
                        <textarea id="taskCommentBody" maxlength="5000" rows="3" placeholder="Scrivi un commento..."></textarea>
                        <button type="button" class="btn btn-primary" id="taskCommentSubmit">Commenta</button>
                    </div>
                </section>

                <div class="field">
                    <label for="priority">Priorit&agrave;</label>
                    <select id="priority">
                        <option value="">Nessuna</option>
                        <option value="low">Bassa</option>
                        <option value="medium">Media</option>
                        <option value="high">Alta</option>
                    </select>
                </div>

                <div class="field">
                    <label for="dueAt">Scadenza</label>
                    <input id="dueAt" type="datetime-local">
                </div>

                <div class="field">
                    <label for="color">Colore evento</label>
                    <div class="color-row">
                        <select id="colorPreset" aria-label="Colore preimpostato">
                            <option value="#ffffff">Bianco</option>
                            <option value="#808080">Grigio</option>
                            <option value="#000000">Nero</option>
                            <option value="#facc15">Giallo</option>
                            <option value="#f97316">Arancione</option>
                            <option value="#ef4444">Rosso</option>
                            <option value="#ec4899">Rosa</option>
                            <option value="#8b5cf6">Viola</option>
                            <option value="#2563eb">Blu</option>
                            <option value="#38bdf8">Azzurro</option>
                            <option value="#86efac">Verde chiaro</option>
                            <option value="#166534">Verde scuro</option>
                            <option value="">Personalizzato</option>
                        </select>
                        <input id="colorText" value="#2563eb" maxlength="7" pattern="^#[0-9A-Fa-f]{6}$" aria-label="Colore HEX">
                        <input type="color" id="color" value="#2563eb" aria-label="Selettore colore">
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-danger" id="deleteTask" style="display:none;margin-right:auto;">Elimina</button>
                    <button type="button" class="btn" data-close="taskModal">Annulla</button>
                    <button type="submit" class="btn btn-primary">Salva evento</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-backdrop" id="commentConfirmModal" hidden>
        <div class="modal comment-confirm-modal" role="dialog" aria-modal="true" aria-labelledby="commentConfirmTitle">
            <div class="modal-head">
                <h2 id="commentConfirmTitle">Elimina commento</h2>
                <button class="close" type="button" id="commentConfirmCancel" aria-label="Chiudi">&times;</button>
            </div>
            <div class="modal-body">
                <p id="commentConfirmMessage">Eliminare questo commento?</p>
                <div class="modal-actions">
                    <button type="button" class="btn" id="commentConfirmCancelButton">Annulla</button>
                    <button type="button" class="btn btn-danger" id="commentConfirmOk">Elimina</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-backdrop" id="taskConfirmModal" hidden>
        <div class="modal comment-confirm-modal" role="dialog" aria-modal="true" aria-labelledby="taskConfirmTitle">
            <div class="modal-head"><h2 id="taskConfirmTitle">Elimina task</h2><button class="close" type="button" id="taskConfirmCancel" aria-label="Chiudi">&times;</button></div>
            <div class="modal-body"><p>Sei sicuro di voler eliminare questa task?</p><div class="modal-actions"><button type="button" class="btn" id="taskConfirmCancelButton">Annulla</button><button type="button" class="btn btn-danger" id="taskConfirmOk">Elimina</button></div></div>
        </div>
    </div>

    <div class="modal-backdrop" id="categoryModal">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="categoryModalTitle">
            <div class="modal-head">
                <h2 id="categoryModalTitle">Categorie</h2>
                <button class="close" type="button" data-close="categoryModal">&times;</button>
            </div>

            <form class="modal-body" id="categoryForm">
                <input type="hidden" id="categoryId">
                <div class="field">
                    <label for="categoryName" id="categoryFormLabel">Nuova categoria</label>
                    <div class="category-input-row">
                        <select id="categoryColorPreset" aria-label="Colore categoria preimpostato">
                            <option value="#d9d9d9">Bianco</option>
                            <option value="#73777f">Grigio</option>
                            <option value="#24262b">Nero</option>
                            <option value="#b8a64c">Giallo</option>
                            <option value="#b86f3d">Arancione</option>
                            <option value="#a85151">Rosso</option>
                            <option value="#a85f7f">Rosa</option>
                            <option value="#745f9e">Viola</option>
                            <option value="#4f6f9f">Blu</option>
                            <option value="#5f8fa6">Azzurro</option>
                            <option value="#6f9d79">Verde chiaro</option>
                            <option value="#3e674b">Verde scuro</option>
                            <option value="">Personalizzato</option>
                        </select>
                        <input id="categoryName" maxlength="40" required placeholder="Es. Amministrazione">
                        <input id="categoryColorText" value="#4f6f9f" maxlength="7" pattern="^#[0-9A-Fa-f]{6}$" aria-label="Colore categoria HEX">
                        <input type="color" id="categoryColor" value="#4f6f9f" aria-label="Selettore colore categoria">
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn" data-close="categoryModal">Annulla</button>
                    <button type="submit" class="btn btn-category" id="categorySubmit">Crea categoria</button>
                </div>

                <div class="category-list" id="categoryList"></div>
            </form>
        </div>
    </div>

    <div class="modal-backdrop" id="columnModal">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="columnModalTitle">
            <div class="modal-head">
                <h2 id="columnModalTitle">Nuova colonna</h2>
                <button class="close" type="button" data-close="columnModal">&times;</button>
            </div>

            <form class="modal-body" id="columnForm">
                <input type="hidden" id="columnId">
                <div class="field">
                    <label for="columnName">Nome colonna</label>
                    <input id="columnName" maxlength="80" required placeholder="Es. In revisione">
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-danger" id="deleteColumn" style="display:none;margin-right:auto;">Elimina</button>
                    <button type="button" class="btn" data-close="columnModal">Annulla</button>
                    <button type="submit" class="btn btn-primary" id="columnSubmit">Salva colonna</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-backdrop" id="deleteColumnModal">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="deleteColumnModalTitle">
            <div class="modal-head">
                <h2 id="deleteColumnModalTitle">Elimina colonna</h2>
                <button class="close" type="button" data-close="deleteColumnModal">&times;</button>
            </div>

            <div class="modal-body">
                <p id="deleteColumnMessage">La colonna contiene delle task. Scegli come procedere.</p>
                <div class="modal-actions">
                    <button type="button" class="btn btn-danger" id="deleteColumnWithTasks">Elimina colonna e task</button>
                    <button type="button" class="btn" id="moveColumnTasks">Sposta task nella prima colonna</button>
                    <button type="button" class="btn" data-close="deleteColumnModal">Annulla</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
