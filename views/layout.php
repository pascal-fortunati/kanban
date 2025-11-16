<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="fr">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kanban</title>
    <script>(function(){var t=localStorage.getItem('theme');try{if(!t){t=(window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches)?'dark':'light';}if(t==='light'){document.documentElement.classList.add('theme-light');}}catch(e){}})();</script>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/atom-one-dark.min.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/kanban.css">
    <link rel="manifest" href="<?= $baseUrl ?>/manifest.webmanifest">
    <meta name="theme-color" content="#61afef">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<?php $isAuthPage = (empty($user) && ($currentPath === '/' || strpos($currentPath, '/auth/login') === 0 || strpos($currentPath, '/auth/register') === 0)); ?>
<body class="bg-gray-900 text-gray-200 <?= $isAuthPage ? 'auth-page' : '' ?>">
    <?php
    $clsActive = 'px-3 py-2 rounded-md bg-blue-600 text-white';
    $clsDefault = 'px-3 py-2 rounded-md bg-gray-800 text-gray-300 hover:bg-gray-700 hover:text-white transition';
    ?>
<?php $hasHeader = !$isAuthPage; ?>
    <?php if ($hasHeader): ?>
    <header class="fixed top-0 left-0 right-0 bg-gradient-to-r from-gray-800 via-gray-900 to-gray-900 border-b border-gray-700 z-50">
        <div class="max-w-7xl mx-auto px-4 py-3">
            <div class="flex items-center justify-between">
                <span class="inline-flex items-center text-gray-100 font-semibold tracking-wide">
                    <i class="fa-solid fa-layer-group mr-2 text-blue-400"></i>
                    Kanban
                </span>
                <button class="md:hidden inline-flex items-center px-3 py-2 rounded bg-gray-800 text-gray-200 hover:bg-gray-700" onclick="toggleNav()">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <nav id="nav-links" class="hidden md:flex items-center space-x-2">
                    <?php if (empty($user)): ?>
                    <?php else: ?>
                        <?php if (($user['role'] ?? '') === 'formateur'): ?>
                            <a href="<?= $baseUrl ?>/dashboard" class="<?= ($currentPath === '/dashboard' ? $clsActive : $clsDefault) ?> inline-flex items-center"><i class="fa-solid fa-chart-line mr-2"></i>Dashboard</a>
                            <a href="<?= $baseUrl ?>/dashboard/logs" class="<?= (strpos($currentPath, '/dashboard/logs') === 0 ? $clsActive : $clsDefault) ?> inline-flex items-center"><i class="fa-solid fa-clipboard-list mr-2"></i>Logs</a>
                            <a href="<?= $baseUrl ?>/auth/logout" class="inline-flex items-center px-3 py-2 rounded-md bg-red-800 text-white hover:bg-red-700 transition">
                                <i class="fa-solid fa-right-from-bracket mr-2"></i>
                                Déconnexion
                            </a>
                        <?php else: ?>
                            <a href="<?= $baseUrl ?>/kanban" class="<?= (strpos($currentPath, '/kanban') === 0 ? $clsActive : $clsDefault) ?> inline-flex items-center"><i class="fa-solid fa-house mr-2"></i>Accueil</a>
                            <a href="<?= $baseUrl ?>/profile" class="<?= (strpos($currentPath, '/profile') === 0 ? $clsActive : $clsDefault) ?> inline-flex items-center"><i class="fa-solid fa-user mr-2"></i>Profil</a>
                            <button class="inline-flex items-center px-3 py-2 rounded-md bg-green-600 hover:bg-green-500 text-white" onclick="openCreateTask()">
                                <i class="fa-solid fa-plus mr-2"></i>
                                Nouvelle tâche
                            </button>
                            <a href="<?= $baseUrl ?>/auth/logout" class="inline-flex items-center px-3 py-2 rounded-md bg-red-800 text-white hover:bg-red-700 transition">
                                <i class="fa-solid fa-right-from-bracket mr-2"></i>
                                Déconnexion
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </nav>
                <div id="nav-actions" class="hidden md:flex items-center space-x-3">
                    <?php if (!empty($user)): ?>
                        <?php if (($user['role'] ?? '') !== 'formateur' && empty($user['github_token'] ?? '')): ?>
                            <a href="<?= $baseUrl ?>/github/authenticate" class="inline-flex items-center px-3 py-2 rounded-md bg-gray-700 text-gray-200 hover:bg-gray-600 transition">
                                <i class="fa-brands fa-github mr-2"></i>
                                Connexion GitHub
                            </a>
                        <?php endif; ?>
                        <?php if (($user['role'] ?? '') !== 'formateur'): ?>
                            <button id="btn-redeploy-missed" class="hidden inline-flex items-center px-3 py-2 rounded-md bg-purple-700 hover:bg-purple-600 text-white">
                                <i class="fa-solid fa-rotate mr-2"></i>
                                Redéployer tâches manquées
                            </button>
                        <?php endif; ?>
                        <a id="notif-btn" href="<?= $baseUrl ?>/dashboard" onclick="openNotifModal(event)" class="relative inline-flex items-center px-3 py-2 rounded-md bg-gray-800 text-gray-200 hover:bg-gray-700 transition">
                            <i class="fa-regular fa-bell"></i>
                            <span id="notif-badge" class="absolute -top-2 -right-2 bg-red-500 text-white text-xs px-1 rounded" style="display:none">0</span>
                        </a>
                        <button id="theme-toggle" class="inline-flex items-center px-3 py-2 rounded-md bg-gray-800 text-gray-200 hover:bg-gray-700 transition" type="button">
                            <i id="theme-icon" class="fa-solid fa-moon"></i>
                        </button>
                        <div id="notif-panel" class="absolute right-4 top-14 w-80 bg-gray-800 border border-gray-700 rounded-md shadow-xl hidden">
                            <div class="px-3 py-2 border-b border-gray-700 flex items-center justify-between">
                                <div class="text-sm font-semibold">Notifications</div>
                                <button class="text-gray-300 hover:text-white" onclick="closeNotifPanel()"><i class="fa-solid fa-xmark"></i></button>
                            </div>
                            <div id="notif-message" class="px-3 py-2 text-sm text-gray-100"></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>
    <?php endif; ?>
    <main class="<?= $hasHeader ? 'pt-20 ' : '' ?>px-4 <?= $isAuthPage ? 'auth-main' : '' ?>">
        <?= $content ?>
    </main>
    <?php if (!empty($user)): ?>
    <div id="create-modal" class="fixed inset-0 hidden items-center justify-center modal">
        <div class="absolute inset-0 bg-gray-900 bg-opacity-60 modal-overlay z-50"></div>
        <div class="relative z-50 bg-gray-800 rounded-lg shadow-xl ring-1 ring-gray-700 w-full max-w-6xl mx-4 modal-content">
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-700">
                <div id="create-modal-title" class="text-lg font-semibold text-gray-100">Créer une tâche</div>
                <button class="inline-flex items-center justify-center w-8 h-8 rounded hover:bg-gray-700 text-gray-300" onclick="closeCreateTask()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form id="create-task-form" class="px-4 py-4 space-y-6">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
                <input type="hidden" name="id" value="">
                <div class="space-y-3">
                    <div class="text-sm text-gray-300">Informations</div>
                    <input type="text" name="title" placeholder="Titre" class="w-full bg-gray-700 p-2 rounded focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    <textarea name="description" placeholder="Description" class="w-full bg-gray-700 p-2 rounded focus:outline-none focus:ring-2 focus:ring-blue-500" rows="10"></textarea>
                    <div class="mt-3">
                        <div class="text-xs text-gray-400 mb-1">Prévisualisation</div>
                        <div class="text-sm text-gray-300"><pre><code id="create-code-preview" class="language-markdown"></code></pre></div>
                    </div>
                </div>
                <div class="space-y-3">
                    <div class="text-sm text-gray-300">Métadonnées</div>
                    <div>
                        <div class="text-xs text-gray-400 mb-1">Priorité</div>
                        <div id="priority-group" class="flex items-center gap-2">
                            <input type="radio" name="priority" id="prio-low" value="low" class="sr-only">
                            <label for="prio-low" class="inline-flex items-center px-3 py-1 rounded-md bg-blue-600 text-white text-xs cursor-pointer">Basse</label>
                            <input type="radio" name="priority" id="prio-medium" value="medium" class="sr-only" checked>
                            <label for="prio-medium" class="inline-flex items-center px-3 py-1 rounded-md bg-yellow-600 text-white text-xs cursor-pointer ring-2 ring-white">Moyenne</label>
                            <input type="radio" name="priority" id="prio-high" value="high" class="sr-only">
                            <label for="prio-high" class="inline-flex items-center px-3 py-1 rounded-md bg-red-600 text-white text-xs cursor-pointer">Élevée</label>
                        </div>
                        <div class="text-xs text-gray-400 mt-2">Priorité actuelle</div>
                        <div id="priority-preview" class="mt-2"></div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-400 mb-1">Étiquettes</div>
                        <div class="flex flex-wrap gap-2" id="quick-labels">
                            <button type="button" data-name="bug" class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium bg-red-600 text-white">Bug</button>
                            <button type="button" data-name="feature" class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium bg-green-600 text-white">Fonctionnalité</button>
                            <button type="button" data-name="docs" class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium bg-purple-600 text-white">Documentation</button>
                            <button type="button" data-name="improvement" class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium bg-blue-600 text-white">Amélioration</button>
                            <button type="button" data-name="chore" class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium bg-yellow-600 text-white">Maintenance</button>
                        </div>
                        <div class="text-xs text-gray-400 mt-2">Étiquettes actuelles</div>
                        <div id="labels-preview" class="mt-2 flex flex-wrap gap-2"></div>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-2 border-t border-gray-700 pt-4">
                    <button type="button" class="px-3 py-2 rounded-md bg-gray-700 hover:bg-gray-600 text-gray-200" onclick="closeCreateTask()">Annuler</button>
                    <button id="create-submit" type="submit" class="px-3 py-2 rounded-md bg-blue-600 hover:bg-blue-500 text-white">Créer</button>
                </div>
                <div id="create-error" class="text-red-400 text-sm"></div>
            </form>
        </div>
    </div>
    <?php endif; ?>
    <script>
        window.APP_BASE      = '<?= $baseUrl ?>';
        window.CSRF_TOKEN    = '<?= $csrfToken ?>';
        window.IS_LOGGED     = <?= empty($user) ? 'false' : 'true' ?>;
        window.USER_ROLE     = '<?= !empty($user) ? ($user['role'] ?? 'guest') : 'guest' ?>';
        window.HAS_GITHUB    = <?= (!empty($user) && !empty($user['github_token'] ?? '')) ? 'true' : 'false' ?>;
        window.HAS_ACTIVE_REPO = <?= (!empty($user) && !empty($user['active_repo_id'] ?? null)) ? 'true' : 'false' ?>;
        window.IS_AUTH_PAGE  = <?= $isAuthPage ? 'true' : 'false' ?>;

        function toggleNav() {
            var links = document.getElementById('nav-links');
            var actions = document.getElementById('nav-actions');
            [links, actions].forEach(function (el) {
                if (!el) return;
                el.classList.toggle('hidden');
            });
        }

        if (window.hljs) {
            hljs.highlightAll();
        }

        if (window.Swal) {
            window.toast = function (o) {
                var base = {
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true,
                    background: '#1f2937',
                    color: '#e5e7eb',
                    iconColor: '#61afef'
                };
                var opts = Object.assign({}, base, (o || {}));
                window.Swal.fire(opts);
            };

            window.toastSuccess = function (t) {
                window.Swal.fire({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true, background: '#1f2937', color: '#e5e7eb', iconColor: '#98c379', icon: 'success', title: String(t || 'Succès') });
            };

            window.toastError = function (t) {
                window.Swal.fire({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true, background: '#1f2937', color: '#e5e7eb', iconColor: '#e06c75', icon: 'error', title: String(t || 'Erreur') });
            };

            window.alertError = function (t, x) {
                var opts = { icon: 'error', title: String(t || 'Erreur'), text: String(x || ''), background: '#1f2937', color: '#e5e7eb' };
                if (window.IS_AUTH_PAGE) {
                    opts.showConfirmButton = false;
                    opts.timer = 2000;
                    opts.timerProgressBar = true;
                }
                window.Swal.fire(opts);
            };

            window.alertSuccess = function (t, x) {
                window.Swal.fire({ icon: 'success', title: String(t || 'Succès'), text: String(x || ''), background: '#1f2937', color: '#e5e7eb' });
            };

            window.confirmAsk = function (o) {
                return window.Swal.fire({
                    title: String((o && o.title) || 'Confirmer'),
                    text: String((o && o.text) || ''),
                    icon: String((o && o.icon) || 'question'),
                    showCancelButton: true,
                    confirmButtonText: String((o && o.confirmText) || 'Oui'),
                    cancelButtonText: String((o && o.cancelText) || 'Annuler'),
                    background: '#1f2937',
                    color: '#e5e7eb'
                }).then(function (r) { return !!r.isConfirmed; });
            };
            <?php if (!empty($flash) && is_array($flash)): ?>
            (function(){
                var t = <?= json_encode((string)($flash['type'] ?? 'info')) ?>;
                var m = <?= json_encode((string)($flash['message'] ?? '')) ?>;
                if (t === 'success') {
                    var opts = { icon: 'success', title: 'GitHub', text: m || 'Connexion réussie', showConfirmButton: false, timer: 1600, timerProgressBar: true, background: '#1f2937', color: '#e5e7eb', iconColor: '#98c379' };
                    if (window.Swal) { window.Swal.fire(opts); }
                } else {
                    var o2 = { icon: (t === 'error' ? 'error' : 'info'), title: (t === 'error' ? 'Erreur' : 'Information'), text: m, background: '#1f2937', color: '#e5e7eb' };
                    if (window.Swal) { window.Swal.fire(o2); }
                }
            })();
            <?php endif; ?>
        }
    </script>
    <script src="<?= $baseUrl ?>/assets/js/utils.js?v=<?= (int)(time()) ?>"></script>
    <script src="<?= $baseUrl ?>/assets/js/kanban.js?v=<?= (int)(time()) ?>"></script>
    <script src="<?= $baseUrl ?>/assets/js/notifications.js?v=<?= (int)(time()) ?>"></script>
    <script>
        (function(){
            if ('serviceWorker' in navigator) {
                var base = window.APP_BASE || '';
                window.addEventListener('load', function(){
                    navigator.serviceWorker.register(base + '/sw.js').catch(function(){});
                });
            }
        })();
    </script>
    <script>
        (function(){
            var pref = localStorage.getItem('theme');
            if (!pref) {
                try { pref = (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light'; } catch(e) { pref = 'dark'; }
            }
            function apply(t){
                var b = document.body;
                if (t === 'light') { b.classList.add('theme-light'); document.documentElement.classList.add('theme-light'); } else { b.classList.remove('theme-light'); document.documentElement.classList.remove('theme-light'); }
                var ic = document.getElementById('theme-icon');
                if (ic) { ic.className = t === 'light' ? 'fa-solid fa-sun' : 'fa-solid fa-moon'; }
            }
            apply(pref);
            var btn = document.getElementById('theme-toggle');
            if (btn) {
                btn.addEventListener('click', function(){
                    pref = (pref === 'light') ? 'dark' : 'light';
                    localStorage.setItem('theme', pref);
                    apply(pref);
                });
            }
        })();
    </script>
</body>
</html>