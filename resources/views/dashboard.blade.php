<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Kanban - Progetti</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest" defer></script>
    @vite(['resources/css/app.css', 'resources/js/dashboard.js'])
</head>
<body>
    <section class="auth-shell" data-auth hidden>
        <div class="auth-panel">
            <div class="auth-panel-head">
                <div class="brand">
                    <div class="brand-badge">KB</div>
                    <div>
                        <div class="brand-title">Task Board</div>
                        <div class="brand-subtitle">Accedi al tuo workspace</div>
                    </div>
                </div>
                <p class="message" data-auth-message hidden></p>
            </div>

            <div class="auth-grid">
                <form class="auth-card" data-login-form>
                    <h2>Accedi</h2>
                    <div class="field-stack">
                        <label>
                            Email
                            <input type="email" name="email" autocomplete="email" required>
                        </label>
                        <label>
                            Password
                            <input type="password" name="password" autocomplete="current-password" required>
                        </label>
                    </div>
                    <div class="form-actions">
                        <button class="btn btn-primary with-icon" type="submit">
                            <i data-lucide="log-in" class="icon"></i>
                            Accedi
                        </button>
                    </div>
                </form>

                <form class="auth-card" data-register-form>
                    <h2>Registrati</h2>
                    <div class="field-stack">
                        <label>
                            Nome
                            <input type="text" name="name" autocomplete="name" required>
                        </label>
                        <label>
                            Email
                            <input type="email" name="email" autocomplete="email" required>
                        </label>
                        <label>
                            Password
                            <input type="password" name="password" autocomplete="new-password" required>
                        </label>
                        <label>
                            Conferma password
                            <input type="password" name="password_confirmation" autocomplete="new-password" required>
                        </label>
                    </div>
                    <div class="form-actions">
                        <button class="btn btn-primary with-icon" type="submit">
                            <i data-lucide="user-plus" class="icon"></i>
                            Crea account
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <section data-dashboard hidden>
        <header class="topbar">
            <div class="brand">
                <div class="brand-badge">KB</div>
                <div>
                    <div class="brand-title">Task Board</div>
                    <div class="brand-subtitle" data-user-email></div>
                </div>
            </div>
            <div class="topbar-actions">
                <select class="workspace-select" data-workspace-select aria-label="Workspace"></select>
                <button class="btn btn-primary with-icon" type="button" data-new-project>
                    <i data-lucide="plus" class="icon"></i>
                    Nuovo progetto
                </button>
                <button class="btn with-icon" type="button" data-logout>
                    <i data-lucide="log-out" class="icon"></i>
                    Esci
                </button>
            </div>
        </header>

        <main class="page">
            <div class="page-head">
                <h1>Progetti</h1>
                <p class="page-note" data-dashboard-message hidden></p>
            </div>

            <section class="workspace-head">
                <div class="workspace-title">
                    <div class="folder-path" data-folder-path></div>
                    <nav class="workspace-nav" aria-label="Viste progetto">
                        <button class="btn with-icon" type="button" data-root>
                            <i data-lucide="home" class="icon"></i>
                            Principale
                        </button>
                        <button class="btn with-icon" type="button" data-archived>
                            <i data-lucide="archive" class="icon"></i>
                            Progetti archiviati
                        </button>
                    </nav>
                </div>
                <div class="workspace-actions">
                    <button class="btn btn-primary with-icon" type="button" data-new-folder>
                        <i data-lucide="folder-plus" class="icon"></i>
                        Nuova cartella
                    </button>
                </div>
            </section>

            <section class="folder-section" data-folder-section>
                <p class="section-label">Cartelle</p>
                <div class="folders-grid" data-folders></div>
            </section>

            <section>
                <p class="section-label">Progetti</p>
                <div class="projects" data-boards></div>
            </section>
        </main>

        <div class="modal-backdrop" data-project-modal hidden>
            <form class="modal" data-project-form>
                <div class="modal-head">
                    <h2 data-project-modal-title>Nuovo progetto</h2>
                    <button class="btn with-icon" type="button" data-close-modal>
                        <i data-lucide="x" class="icon"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="modal-readonly-note" data-project-modal-note hidden></p>
                    <label class="field">
                        Nome progetto
                        <input type="text" name="name" data-project-name placeholder="Es. Board attivita">
                    </label>
                    <label class="field">
                        Descrizione
                        <textarea name="description" data-project-description placeholder="Scrivi una breve descrizione..."></textarea>
                    </label>
                    <div class="field">
                        Colore progetto
                        <div class="color-row">
                            <select data-project-color-preset>
                                <option value="#2563eb">Blu</option>
                                <option value="#4f6f9f">Azzurro</option>
                                <option value="#d6a257">Giallo</option>
                                <option value="#d96060">Rosso</option>
                            </select>
                            <input type="text" data-project-color-text value="#2563eb">
                            <input type="color" data-project-color value="#2563eb">
                        </div>
                    </div>
                    <div class="modal-actions">
                        <button class="modal-button neutral" type="button" data-close-modal>Annulla</button>
                        <button class="modal-button warning-solid" type="submit" data-project-submit>Salva progetto</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="modal-backdrop" data-folder-modal hidden>
            <form class="modal" data-folder-form>
                <div class="modal-head">
                    <h2 data-folder-modal-title>Nuova cartella</h2>
                    <button class="btn with-icon" type="button" data-close-modal>
                        <i data-lucide="x" class="icon"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="modal-readonly-note" data-folder-modal-note hidden></p>
                    <label class="field">
                        Nome cartella
                        <input type="text" name="name" data-folder-name placeholder="Es. Clienti">
                    </label>
                    <div class="field">
                        Colore cartella
                        <div class="color-row">
                            <select data-folder-color-preset>
                                <option value="#4f6f9f">Blu</option>
                                <option value="#2563eb">Azzurro</option>
                                <option value="#d6a257">Giallo</option>
                                <option value="#d96060">Rosso</option>
                            </select>
                            <input type="text" data-folder-color-text value="#4f6f9f">
                            <input type="color" data-folder-color value="#4f6f9f">
                        </div>
                    </div>
                    <div class="modal-actions">
                        <button class="modal-button neutral" type="button" data-close-modal>Annulla</button>
                        <button class="modal-button warning-solid" type="submit" data-folder-submit>Salva cartella</button>
                    </div>
                </div>
            </form>
        </div>
    </section>
</body>
</html>
