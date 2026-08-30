<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Kanban</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-100 text-slate-900">
        <main class="mx-auto min-h-screen max-w-5xl px-6 py-10">
            <header class="mb-10 flex items-center justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-indigo-600">Kanban</p>
                    <h1 class="mt-2 text-3xl font-bold tracking-tight">Il tuo spazio di lavoro</h1>
                </div>
                <span class="rounded-full bg-white px-4 py-2 text-sm text-slate-500 shadow-sm">Laravel + Sanctum</span>
            </header>

            <section data-auth class="grid gap-6 md:grid-cols-2">
                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                    <h2 class="text-xl font-semibold">Accedi</h2>
                    <p class="mt-1 text-sm text-slate-500">Continua verso i tuoi workspace.</p>
                    <form data-login-form class="mt-6 space-y-4">
                        <label class="block text-sm font-medium">Email
                            <input name="email" type="email" required autocomplete="email" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                        </label>
                        <label class="block text-sm font-medium">Password
                            <input name="password" type="password" required autocomplete="current-password" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                        </label>
                        <button class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 font-semibold text-white hover:bg-indigo-700">Login</button>
                    </form>
                </div>

                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                    <h2 class="text-xl font-semibold">Crea account</h2>
                    <p class="mt-1 text-sm text-slate-500">Inizia con il tuo workspace personale.</p>
                    <form data-register-form class="mt-6 space-y-4">
                        <label class="block text-sm font-medium">Nome
                            <input name="name" type="text" required autocomplete="name" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                        </label>
                        <label class="block text-sm font-medium">Cognome
                            <input name="last_name" type="text" required autocomplete="family-name" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                        </label>
                        <label class="block text-sm font-medium">Username
                            <input name="username" type="text" required minlength="3" maxlength="30" pattern="[A-Za-z0-9_-]+" autocomplete="username" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                            <span class="mt-1 block text-xs text-slate-500">3-30 caratteri: lettere, numeri, _ e -</span>
                        </label>
                        <label class="block text-sm font-medium">Email
                            <input name="email" type="email" required autocomplete="email" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                        </label>
                        <label class="block text-sm font-medium">Password
                            <input name="password" type="password" required minlength="8" autocomplete="new-password" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                        </label>
                        <label class="block text-sm font-medium">Conferma password
                            <input name="password_confirmation" type="password" required minlength="8" autocomplete="new-password" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                        </label>
                        <button class="w-full rounded-lg bg-slate-900 px-4 py-2.5 font-semibold text-white hover:bg-slate-700">Registrati</button>
                    </form>
                </div>
                <p data-auth-message class="hidden md:col-span-2 text-sm"></p>
            </section>

            <section data-dashboard class="hidden">
                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <p class="text-sm text-slate-500">Autenticato come</p>
                            <p data-user-email class="font-semibold"></p>
                        </div>
                        <button data-logout class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold hover:bg-slate-50">Logout</button>
                    </div>
                </div>
                <div class="mt-8">
                    <h2 class="text-2xl font-semibold">I tuoi workspace</h2>
                    <ul data-workspaces class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3"></ul>
                    <p data-dashboard-message class="mt-4 hidden text-sm"></p>
                </div>
            </section>
        </main>
    </body>
</html>
