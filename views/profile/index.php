<?php
declare(strict_types=1);
?>
<div class="max-w-7xl mx-auto">
    <div class="bg-gray-800 rounded-lg shadow ring-1 ring-gray-700 p-6 mb-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
            <div class="flex items-center gap-5">
                <?php if (!empty($user['github_token'] ?? '') && !empty($user['github_username'] ?? '')): ?>
                    <img src="https://avatars.githubusercontent.com/<?= urlencode((string)($user['github_username'] ?? '')) ?>?s=128" alt="Avatar GitHub" class="w-20 h-20 rounded-full ring-2 ring-gray-700 shadow">
                <?php else: ?>
                    <div class="w-20 h-20 rounded-full bg-gray-700 flex items-center justify-center text-gray-300 ring-2 ring-gray-700 shadow"><i class="fa-brands fa-github text-3xl"></i></div>
                <?php endif; ?>
                <div class="space-y-1">
                    <div class="text-xl font-semibold text-gray-100 flex items-center gap-3">
                        <?= htmlspecialchars($user['name'] ?? '') ?>
                        <?php if (!empty($user['github_token'] ?? '') && !empty($user['github_username'] ?? '')): ?>
                            <span class="text-xs px-2 py-1 rounded bg-green-700 text-white inline-flex items-center"><i class="fa-brands fa-github mr-1"></i>Connecté</span>
                        <?php else: ?>
                            <span class="text-xs px-2 py-1 rounded bg-gray-700 text-gray-200 inline-flex items-center"><i class="fa-brands fa-github mr-1"></i>Non connecté</span>
                        <?php endif; ?>
                    </div>
                    <div class="text-gray-300"><?= htmlspecialchars($user['email'] ?? '') ?></div>
                    <?php if (!empty($user['github_username'] ?? '') && !empty($user['github_token'] ?? '')): ?>
                        <div class="text-sm text-gray-300">GitHub: <a href="https://github.com/<?= htmlspecialchars((string)$user['github_username']) ?>" target="_blank" class="text-blue-400 hover:text-blue-300 transition">@<?= htmlspecialchars((string)$user['github_username']) ?></a></div>
                    <?php endif; ?>
                    <div class="text-xs text-gray-400">Repositories liés: <?= (int)count($repos) ?></div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="https://github.com/" target="_blank" class="inline-flex items-center px-3 py-2 rounded-md bg-gray-700 hover:bg-gray-600 text-gray-200"><i class="fa-brands fa-github mr-2"></i>Ouvrir GitHub</a>
                <a href="<?= htmlspecialchars((string)($_SERVER['REQUEST_URI'] ?? '/profile')) ?>" class="inline-flex items-center px-3 py-2 rounded-md bg-gray-700 hover:bg-gray-600 text-gray-200"><i class="fa-solid fa-rotate mr-2"></i>Rafraîchir</a>
                <button id="profile-instructions" class="inline-flex items-center px-3 py-2 rounded-md bg-gray-700 hover:bg-gray-600 text-gray-200" onclick="openProfileInstructions()"><i class="fa-solid fa-circle-info mr-2"></i>Instructions</button>
            </div>
        </div>
    </div>
    <div class="bg-gray-800 p-4 rounded">
        <div class="flex items-center justify-between mb-3">
            <div class="inline-flex items-center text-lg gap-2"><i class="fa-brands fa-github"></i><span>Repositories GitHub</span></div>
            <div class="flex items-center gap-2">
                <button id="open-create-repo" class="px-3 py-2 rounded-md bg-green-600 hover:bg-green-500 text-white inline-flex items-center"><i class="fa-solid fa-plus mr-2"></i>Créer un repository</button>
                <button id="sync-repos" class="px-3 py-2 rounded-md bg-blue-600 hover:bg-blue-500 text-white inline-flex items-center"><i class="fa-solid fa-rotate mr-2"></i>Synchroniser</button>
                <button id="sort-date" class="px-3 py-2 rounded-md bg-gray-700 hover:bg-gray-600 text-white inline-flex items-center"><i class="fa-solid fa-sort mr-2"></i>Date</button>
                <button id="sort-size" class="px-3 py-2 rounded-md bg-gray-700 hover:bg-gray-600 text-white inline-flex items-center"><i class="fa-solid fa-sort mr-2"></i>Taille</button>
                <button id="sort-stars" class="px-3 py-2 rounded-md bg-gray-700 hover:bg-gray-600 text-white inline-flex items-center"><i class="fa-solid fa-sort mr-2"></i>Stars</button>
            </div>
        </div>
        <div id="repos-status" class="text-sm text-gray-300 mb-2"></div>
        <div class="space-y-2">
            <?php foreach ($repos as $r): ?>
                <?php $isActive = !empty($activeRepoId) && (int)$activeRepoId === (int)$r['id']; ?>
                <div class="p-3 rounded flex items-center justify-between transition-colors duration-150 border <?= $isActive ? 'border-purple-500 bg-gray-700' : 'border-gray-700 bg-gray-700 hover:bg-gray-600' ?>" data-id="<?= (int)$r['id'] ?>" data-name="<?= htmlspecialchars($r['name']) ?>" data-created="<?= htmlspecialchars(str_replace('T',' ', (string)($r['created_at'] ?? ''))) ?>">
                    <div>
                        <div class="font-semibold flex items-center gap-2">
                            <?= htmlspecialchars($r['name']) ?>
                            <?php if (!empty($activeRepoId) && (int)$activeRepoId === (int)$r['id']): ?>
                                <span class="text-xs px-2 py-1 rounded bg-purple-600 text-white">Actif</span>
                            <?php endif; ?>
                        </div>
                        <div class="text-sm text-gray-300"><a class="text-blue-400 hover:text-blue-300 transition" href="<?= htmlspecialchars($r['github_url']) ?>" target="_blank">Ouvrir</a></div>
                        <div class="text-xs text-gray-400">Créé le <span class="repo-date">—</span> • Taille: <span class="repo-size">—</span> • <span class="repo-lang-badge"></span> • <i class="fa-solid fa-star"></i> <span class="repo-stars">0</span> • <i class="fa-solid fa-code-branch"></i> <span class="repo-forks">0</span></div>
                    </div>
                    <div class="flex items-center gap-2">
                        <?php if (!empty($activeRepoId) && (int)$activeRepoId === (int)$r['id']): ?>
                            <button class="px-2 py-1 rounded bg-yellow-600 hover:bg-yellow-500 text-white" data-id="<?= (int)$r['id'] ?>" onclick="deactivateRepo(this)"><i class="fa-solid fa-ban mr-1"></i>Désactiver</button>
                        <?php else: ?>
                            <button class="px-2 py-1 rounded bg-gray-600 hover:bg-gray-500 text-white" data-id="<?= (int)$r['id'] ?>" onclick="setActiveRepo(this)"><i class="fa-solid fa-check mr-1"></i>Activer</button>
                        <?php endif; ?>
                        <button class="px-2 py-1 rounded bg-red-600 hover:bg-red-500 text-white" data-id="<?= (int)$r['id'] ?>" onclick="deleteRepo(this)"><i class="fa-solid fa-trash mr-1"></i>Supprimer</button>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($repos)): ?>
                <div class="text-gray-400">Aucun repository lié</div>
            <?php endif; ?>
        </div>
    </div>

    <div id="create-repo-modal" class="fixed inset-0 hidden items-center justify-center modal">
        <div class="absolute inset-0 bg-gray-900 bg-opacity-60 modal-overlay"></div>
        <div class="relative z-10 bg-gray-800 rounded-lg shadow-xl ring-1 ring-gray-700 w-full max-w-lg mx-4 modal-content">
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-700">
                <div class="text-lg font-semibold text-gray-100">Créer un repository</div>
                <button class="inline-flex items-center justify-center w-8 h-8 rounded hover:bg-gray-700 text-gray-300" onclick="closeCreateRepoModal()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form id="create-repo-form" class="px-4 py-4 space-y-4">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
                <input type="text" name="name" placeholder="Nom du repository" class="w-full bg-gray-700 p-2 rounded focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                <textarea name="description" placeholder="Description" class="w-full bg-gray-700 p-2 rounded focus:outline-none focus:ring-2 focus:ring-blue-500" rows="4"></textarea>
                <div class="flex items-center justify-end gap-2">
                    <button type="button" class="px-3 py-2 rounded-md bg-gray-700 hover:bg-gray-600 text-gray-200" onclick="closeCreateRepoModal()">Annuler</button>
                    <button type="submit" class="px-3 py-2 rounded-md bg-blue-600 hover:bg-blue-500 text-white">Créer</button>
                </div>
                <div id="create-repo-status" class="text-sm"></div>
            </form>
        </div>
    </div>
</div>