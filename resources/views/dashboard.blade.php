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
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js" defer></script>
    @vite(['resources/css/app.css', 'resources/js/dashboard.js'])
</head>
<body>
    <section class="auth-shell" data-auth hidden>
        <div class="auth-panel">
            <div class="auth-panel-head">
                <div class="brand">
                    <div class="brand-badge">KB</div>
                    <div>
                        <div class="brand-title">Kanban</div>
                        <div class="brand-subtitle">Working boards</div>
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
                            <i class="icon" data-lucide="log-in"></i>
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
                            <i class="icon" data-lucide="user-plus"></i>
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
                    <div class="brand-title">Kanban</div>
                    <div class="brand-subtitle">Working boards</div>
                </div>
            </div>
            <div class="topbar-actions">
                <select class="workspace-select" data-workspace-select aria-label="Workspace"></select>
                <button class="btn btn-primary with-icon" type="button" data-new-workspace>
                    <i class="icon" data-lucide="plus"></i>
                    Nuovo workspace
                </button>
                <button class="btn btn-primary with-icon" type="button" data-new-project>
                    <i class="icon" data-lucide="plus"></i>
                    Nuovo progetto
                </button>
                <button class="btn with-icon" type="button" data-account>
                    <i class="icon" data-lucide="user"></i>
                    Account
                </button>
                <button class="btn notification-button" type="button" data-notifications aria-label="Notifiche"><i class="icon" data-lucide="bell"></i><span class="notification-badge" data-notification-count hidden>0</span></button>
                <button class="btn with-icon" type="button" data-logout>
                    <i class="icon" data-lucide="log-out"></i>
                    Esci
                </button>
            </div>
        </header>

        <aside class="notifications-panel" data-notifications-panel hidden>
            <div class="notifications-head"><div><span class="notifications-eyebrow">Aggiornamenti</span><h2>Notifiche</h2></div><button class="close" type="button" data-close-notifications>&times;</button></div>
            <div class="notifications-body"><div class="notifications-panel-heading"><h3>Inviti workspace</h3><span>Richiedono la tua risposta</span></div><div class="notifications-list" data-notifications-list></div></div>
        </aside>

        <div class="modal-backdrop" data-account-modal hidden>
            <div class="modal">
                <div class="modal-head"><h2>Account</h2><button class="close" type="button" data-close-modal>&times;</button></div>
                <div class="modal-body">
                    <form data-profile-form>
                        <div class="field"><label for="accountName">Nome</label><input id="accountName" required maxlength="255" data-account-name></div>
                        <div class="field"><label>Email</label><input type="email" disabled data-account-email></div>
                        <div class="modal-actions"><button class="btn btn-primary" type="submit">Salva nome</button></div>
                    </form>
                    <form data-password-form>
                        <div class="field"><label for="currentPassword">Password attuale</label><input id="currentPassword" type="password" required autocomplete="current-password" data-current-password></div>
                        <div class="field"><label for="newPassword">Nuova password</label><input id="newPassword" type="password" required minlength="8" autocomplete="new-password" data-new-password></div>
                        <div class="field"><label for="confirmPassword">Conferma password</label><input id="confirmPassword" type="password" required minlength="8" autocomplete="new-password" data-confirm-password></div>
                        <div class="modal-actions"><button class="btn btn-primary" type="submit">Cambia password</button></div>
                    </form>
                    <p class="message" data-account-message hidden></p>
                </div>
            </div>
        </div>

        <main class="page">
            <div class="page-head">
                <h1>I tuoi progetti</h1>
                <p class="page-note">Apri un progetto per entrare nel suo Kanban.</p>
                <div class="page-note toast-message" data-dashboard-message hidden><span data-dashboard-message-text></span><button class="toast-close" type="button" data-close-dashboard-message aria-label="Chiudi messaggio">&times;</button></div>
            </div>

            <div class="workspace">
                <div class="workspace-head">
                    <div class="workspace-title">
                        <div class="folder-path" data-folder-path></div>
                        <div class="workspace-nav">
                            <button class="btn active with-icon" type="button" data-root>
                                <i class="icon" data-lucide="home"></i>
                                Principale
                            </button>
                            <button class="btn with-icon" type="button" data-archived>
                                <i class="icon" data-lucide="archive"></i>
                                Progetti archiviati
                            </button>
                        </div>
                    </div>
                    <div class="workspace-actions">
                        <button class="btn with-icon" type="button" data-manage-workspace hidden>Gestisci workspace</button>
                        <button class="btn with-icon" type="button" data-open-activity hidden>Cronologia</button>
                        <button class="btn with-icon" type="button" data-new-folder>
                            <i class="icon" data-lucide="folder-plus"></i>
                            Nuova cartella
                        </button>
                    </div>
                </div>

                <div class="folder-section is-hidden" data-folder-section>
                    <div class="section-label">Cartelle</div>
                    <div class="folders-grid" data-folders></div>
                </div>

                <div class="project-section-head">
                    <div class="section-label">Progetti</div>
                </div>
                <section class="projects" data-boards></section>
            </div>

            <div class="status" data-status>Caricamento progetti...</div>
        </main>

        <div class="modal-backdrop" data-workspace-modal hidden>
            <div class="modal">
                <div class="modal-head">
                    <h2>Gestisci workspace</h2>
                    <button class="close" type="button" data-close-modal>&times;</button>
                </div>
                <div class="modal-body">
                    <section class="workspace-info-card">
                        <span class="workspace-panel-eyebrow">Workspace condiviso</span>
                        <strong data-workspace-detail-name></strong>
                        <span data-workspace-detail-owner></span>
                    </section>
                    <section class="workspace-panel workspace-invite-panel">
                        <div class="workspace-panel-heading"><h3>Invita membro</h3><span>Collabora con il tuo team</span></div>
                        <form class="workspace-invite-form" data-invite-form>
                        <div class="field"><label for="inviteEmail">Invita membro</label><input id="inviteEmail" type="email" required placeholder="email@esempio.it" data-invite-email></div>
                        <button class="btn btn-primary" type="submit">Invia invito</button>
                        </form>
                    </section>
                    <section class="workspace-panel workspace-section"><div class="workspace-panel-heading"><h3>Inviti pendenti</h3><span>In attesa di risposta</span></div><div data-pending-invitations></div></section>
                    <section class="workspace-panel workspace-section"><div class="workspace-panel-heading"><h3>Membri</h3><span>Persone con accesso</span></div><div data-workspace-members></div></section>
                    <div class="workspace-danger-zone"><div><strong>Azioni workspace</strong><span>Le modifiche possono influire su tutti i membri</span></div><div class="modal-actions"><button class="btn warning-solid" type="button" data-leave-workspace>Lascia workspace</button><button class="btn danger-solid" type="button" data-delete-workspace hidden>Elimina workspace</button></div></div>
                </div>
            </div>
        </div>

        <div class="modal-backdrop" data-activity-modal hidden>
            <div class="modal activity-modal">
                <div class="modal-head"><div><span class="activity-modal-eyebrow">Workspace</span><h2>Cronologia</h2></div><button class="close" type="button" data-close-modal>&times;</button></div>
                <div class="modal-body activity-modal-body"><div class="activity-panel"><div class="activity-panel-heading"><h3>Attività recenti</h3><span>Modifiche del workspace</span></div><div data-activity-list></div><button class="btn" type="button" data-activity-more hidden>Carica altre</button></div></div>
            </div>
        </div>

        <div class="modal-backdrop" data-new-workspace-modal hidden>
            <div class="modal">
                <div class="modal-head"><h2>Nuovo workspace</h2><button class="close" type="button" data-close-modal>&times;</button></div>
                <form class="modal-body" data-new-workspace-form>
                    <div class="field"><label for="newWorkspaceName">Nome workspace</label><input id="newWorkspaceName" required maxlength="120" placeholder="Es. Marketing" data-new-workspace-name></div>
                    <div class="modal-actions"><button class="btn" type="button" data-close-modal>Annulla</button><button class="btn btn-primary" type="submit">Crea workspace</button></div>
                </form>
            </div>
        </div>

        <div class="quick-drop" data-quick-drop-shell>
            <div class="quick-drop-zone root" data-quick-drop="root">
                <i class="icon" data-lucide="home"></i>
                Sposta in Principale
            </div>
            <div class="quick-drop-zone archive" data-quick-drop="archive">
                <i class="icon" data-lucide="archive"></i>
                Sposta in Archivio
            </div>
        </div>

        <div class="modal-backdrop" data-project-modal hidden>
            <div class="modal">
                <div class="modal-head">
                    <h2 data-project-modal-title>Nuovo progetto</h2>
                    <button class="close" type="button" data-close-modal>&times;</button>
                </div>
                <form class="modal-body" data-project-form>
                    <p class="modal-readonly-note" data-project-modal-note hidden></p>
                    <div class="field">
                        <label for="projectName">Nome progetto</label>
                        <input id="projectName" name="name" maxlength="120" required placeholder="Es. Sito cliente Rossi" data-project-name>
                    </div>
                    <div class="field">
                        <label for="projectColor">Colore progetto</label>
                        <div class="color-row">
                            <select id="projectColorPreset" aria-label="Colore progetto preimpostato" data-project-color-preset>
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
                                <option value="#22c55e">Verde</option>
                                <option value="">Personalizzato</option>
                            </select>
                            <input id="projectColorText" maxlength="7" placeholder="#2563eb" data-project-color-text>
                            <input id="projectColor" type="color" value="#2563eb" aria-label="Colore progetto" data-project-color>
                        </div>
                    </div>
                    <div class="modal-actions">
                        <button class="btn" type="button" data-close-modal>Annulla</button>
                        <button class="btn btn-primary with-icon" type="submit" data-project-submit>
                            <i class="icon" data-lucide="plus"></i>
                            <span>Crea progetto</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal-backdrop" data-folder-modal hidden>
            <div class="modal">
                <div class="modal-head">
                    <h2 data-folder-modal-title>Nuova cartella</h2>
                    <button class="close" type="button" data-close-modal>&times;</button>
                </div>
                <form class="modal-body" data-folder-form>
                    <p class="modal-readonly-note" data-folder-modal-note hidden></p>
                    <div class="field">
                        <label for="folderName">Nome cartella</label>
                        <input id="folderName" name="name" maxlength="120" required placeholder="Es. Clienti" data-folder-name>
                    </div>
                    <div class="field">
                        <label for="folderColor">Colore cartella</label>
                        <div class="color-row">
                            <select id="folderColorPreset" aria-label="Colore preimpostato" data-folder-color-preset>
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
                                <option value="#22c55e">Verde</option>
                                <option value="">Personalizzato</option>
                            </select>
                            <input id="folderColorText" maxlength="7" placeholder="#4f6f9f" data-folder-color-text>
                            <input id="folderColor" type="color" value="#4f6f9f" aria-label="Colore cartella" data-folder-color>
                        </div>
                    </div>
                    <div class="modal-actions">
                        <button class="btn" type="button" data-close-modal>Annulla</button>
                        <button class="btn btn-primary with-icon" type="submit" data-folder-submit>
                            <i class="icon" data-lucide="save"></i>
                            Salva cartella
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal-backdrop" data-confirm-modal hidden>
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="confirmTitle">
                <div class="modal-head">
                    <h2 id="confirmTitle">Conferma eliminazione</h2>
                    <button class="close" type="button" data-close-confirm>&times;</button>
                </div>
                <div class="modal-body">
                    <p class="confirm-message" data-confirm-message></p>
                    <div class="modal-actions confirm-actions">
                        <button class="btn modal-button neutral" type="button" data-confirm-result="cancel">Annulla</button>
                        <button class="btn modal-button danger-outline with-icon" type="button" data-confirm-result="delete">
                            <i class="icon" data-lucide="trash-2"></i>
                            Elimina
                        </button>
                        <button class="btn modal-button danger-solid with-icon" type="button" data-confirm-result="delete-kan">
                            <i class="icon" data-lucide="trash"></i>
                            Elimina anche Kan
                        </button>
                        <button class="btn modal-button warning-solid with-icon" type="button" data-confirm-result="archive-kan">
                            <i class="icon" data-lucide="archive"></i>
                            Elimina e archivia Kan
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>
</body>
</html>
