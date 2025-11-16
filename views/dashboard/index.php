<?php
declare(strict_types=1);
?>
<div class="max-w-7xl mx-auto">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-gray-800 rounded-lg shadow ring-1 ring-gray-700 p-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg" style="background-color:#e06c75;color:#282c34"><i class="fa-solid fa-code-branch"></i></span>
                    <div class="text-sm text-gray-300">Total commits</div>
                </div>
                <div class="text-2xl font-semibold"><?= (int)$stats['commits'] ?></div>
            </div>
        </div>
        <div class="bg-gray-800 rounded-lg shadow ring-1 ring-gray-700 p-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg" style="background-color:#98c379;color:#282c34"><i class="fa-solid fa-users"></i></span>
                    <div class="text-sm text-gray-300">Étudiants actifs</div>
                </div>
                <div class="text-2xl font-semibold"><?= (int)$stats['students'] ?></div>
            </div>
        </div>
        <div class="bg-gray-800 rounded-lg shadow ring-1 ring-gray-700 p-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg" style="background-color:#61afef;color:#282c34"><i class="fa-solid fa-check-circle"></i></span>
                    <div class="text-sm text-gray-300">Tâches complétées</div>
                </div>
                <div class="text-2xl font-semibold"><?= (int)$stats['tasksDone'] ?></div>
            </div>
        </div>
        <div class="bg-gray-800 rounded-lg shadow ring-1 ring-gray-700 p-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg" style="background-color:#d19a66;color:#282c34"><i class="fa-solid fa-book"></i></span>
                    <div class="text-sm text-gray-300">Repositories créés</div>
                </div>
                <div class="text-2xl font-semibold"><?= (int)$stats['repos'] ?></div>
            </div>
        </div>
    </div>
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2">
            <button id="open-broadcast" class="px-3 py-2 rounded-md bg-blue-600 hover:bg-blue-500 text-white inline-flex items-center"><i class="fa-solid fa-file-lines mr-2"></i>Template Tâche</button>
            <button id="clear-tasks-btn" class="px-3 py-2 rounded-md bg-red-600 hover:bg-red-500 text-white inline-flex items-center"><i class="fa-solid fa-trash mr-2"></i>Vider les tâches</button>
        </div>
        <div id="clear-status" class="text-sm text-gray-300"></div>
    </div>
    <div class="bg-gray-800 rounded-lg shadow ring-1 ring-gray-700 p-4">
        <div class="font-semibold mb-2 inline-flex items-center gap-2"><i class="fa-brands fa-github"></i><span>Étudiants (GitHub: connectés et non connectés)</span></div>
        <div class="flex items-center justify-between mb-3">
            <input id="students-search" type="text" placeholder="Rechercher un étudiant ou un @github" class="bg-gray-700 p-2 rounded w-full max-w-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            <div class="flex items-center gap-2">
                <select id="broadcast-select" class="bg-gray-700 p-2 rounded">
                    <?php foreach (($broadcastHistory ?? []) as $h): ?>
                        <?php 
                        $d = json_decode((string)($h['data'] ?? ''), true); 
                        $miss = is_array($d) ? ($d['missing_all'] ?? ($d['missing'] ?? ($d['still_missing'] ?? []))) : []; 
                        $missB64 = base64_encode(json_encode($miss)); 
                        $nm = is_array($d) ? trim((string)($d['name'] ?? '')) : ''; 
                        $createdCount = is_array($d) ? (int)($d['created'] ?? 0) : 0; 
                        $templateTasks = is_array($d) ? (int)($d['tasks'] ?? 0) : 0;
                        if ($templateTasks === 0 && $createdCount > 0) { $templateTasks = $createdCount; }
                        
                        // Formatage de la date en DD/MM/YYYY
                        $createdAt = (string)($h['created_at'] ?? '');
                        $dateFormatted = '';
                        if ($createdAt !== '') {
                            $timestamp = strtotime($createdAt);
                            if ($timestamp !== false) {
                                $dateFormatted = date('d/m/Y', $timestamp);
                            }
                        }
                        ?>
                        <option value="<?= (int)($h['id'] ?? 0) ?>" data-missing-b64="<?= htmlspecialchars($missB64) ?>">
                            <?= ($nm !== '' ? htmlspecialchars($nm) : 'Template Tâche #' . (int)($h['id'] ?? 0)) ?><?= ($dateFormatted !== '' ? ' · ' . $dateFormatted : '') ?><?= ($templateTasks > 0 ? ' · ' . (int)$templateTasks . ' tâches' : '') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button id="btn-select-missing-from" class="px-2 py-1 rounded bg-purple-700 hover:bg-purple-600 text-white"><i class="fa-solid fa-user-xmark mr-1"></i>Elèves</button>
                <button id="btn-redeploy-selected-from" class="px-2 py-1 rounded bg-blue-700 hover:bg-blue-600 text-white"><i class="fa-solid fa-rotate mr-1"></i>Redéployer</button>
                <button id="btn-delete-broadcast" class="px-2 py-1 rounded bg-red-700 hover:bg-red-600 text-white"><i class="fa-solid fa-trash mr-1"></i>diffusion</button>
            </div>
        </div>
        <div class="overflow-x-auto hidden md:block">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-300">
                        <th class="px-2 py-2">Sel.</th>
                        <th class="px-2 py-2">Étudiant</th>
                        <th class="px-2 py-2">GitHub</th>
                        <th class="px-2 py-2">Statut</th>
                        <th class="px-2 py-2">Repos</th>
                        <th class="px-2 py-2">Repo actif</th>
                        <th class="px-2 py-2">Actions</th>
                    </tr>
                </thead>
                <tbody id="students-tbody">
                    <?php $missingIds = array_map('intval', ($latestBroadcastMissing ?? [])); ?>
                    <?php foreach (($students ?? []) as $stu): ?>
                        <tr class="border-t border-gray-700">
                            <td class="px-2 py-2"><input type="checkbox" class="stu-checkbox" value="<?= (int)($stu['id'] ?? 0) ?>" data-missing="<?= in_array((int)($stu['id'] ?? 0), $missingIds, true) ? '1' : '0' ?>" data-tasks-count="<?= (int)($stu['tasks_count'] ?? 0) ?>" data-gh="<?= (!empty($stu['github_token'] ?? '')) ? '1' : '0' ?>" data-active="<?= (!empty($stu['active_repo_name'] ?? '')) ? '1' : '0' ?>"></td>
                            <td class="px-2 py-2">
                                <div class="flex items-center gap-3">
                                    <?php $gh = (string)($stu['github_username'] ?? ''); ?>
                                    <?php if ($gh !== ''): ?>
                                        <img src="https://avatars.githubusercontent.com/<?= urlencode($gh) ?>?s=32" alt="Avatar" class="w-8 h-8 rounded-full ring-1 ring-gray-600" onerror="this.onerror=null;this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'32\' height=\'32\' viewBox=\'0 0 32 32\'><rect width=\'32\' height=\'32\' fill=\'%23282c34\'/><circle cx=\'16\' cy=\'12\' r=\'6\' fill=\'%23abb2bf\'/><rect x=\'6\' y=\'20\' width=\'20\' height=\'10\' rx=\'5\' fill=\'%2361afef\'/></svg>'">
                                    <?php else: ?>
                                        <div class="w-8 h-8 rounded-full ring-1 ring-gray-600 bg-gray-700 flex items-center justify-center"><i class="fa-solid fa-user text-gray-300"></i></div>
                                    <?php endif; ?>
                                    <span><?= htmlspecialchars((string)($stu['name'] ?? '')) ?></span>
                                </div>
                            </td>
                            <?php $gh = (string)($stu['github_username'] ?? ''); ?>
                            <td class="px-2 py-2">
                                <?php if ($gh !== ''): ?>
                                    <a class="text-blue-400 hover:text-blue-300 transition" href="https://github.com/<?= htmlspecialchars($gh) ?>" target="_blank">@<?= htmlspecialchars($gh) ?></a>
                                <?php else: ?>
                                    <span class="text-gray-400">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-2 py-2">
                                <?php if (!empty($stu['github_token'] ?? '')): ?>
                                    <span class="text-xs px-2 py-1 rounded bg-green-700 text-white inline-flex items-center"><i class="fa-brands fa-github mr-1"></i>Connecté</span>
                                <?php else: ?>
                                    <span class="text-xs px-2 py-1 rounded bg-gray-700 text-gray-200 inline-flex items-center"><i class="fa-brands fa-github mr-1"></i>Non connecté</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-2 py-2"><?= (int)($stu['repos_count'] ?? 0) ?></td>
                            <td class="px-2 py-2">
                                <?php if (!empty($stu['active_repo_name'])): ?>
                                    <a class="text-blue-400 hover:text-blue-300 transition" href="<?= htmlspecialchars($stu['active_repo_url'] ?? '#') ?>" target="_blank"><?= htmlspecialchars($stu['active_repo_name'] ?? '—') ?></a>
                                <?php else: ?>
                                    <span class="text-gray-400">Aucun</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-2 py-2"><button class="px-2 py-1 rounded bg-gray-700 hover:bg-gray-600" data-user-id="<?= (int)$stu['id'] ?>" data-user-name="<?= htmlspecialchars($stu['name']) ?>" onclick="openCommitsModal(this)"><i class="fa-solid fa-clock-rotate-left mr-1"></i>Voir commits</button></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($students ?? [])): ?>
                        <tr><td class="px-2 py-2 text-gray-400" colspan="7">Aucun étudiant connecté à GitHub</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="md:hidden space-y-2" id="students-mobile">
            <?php $missingIds = array_map('intval', ($latestBroadcastMissing ?? [])); ?>
            <?php foreach (($students ?? []) as $stu): ?>
                <div class="p-3 rounded border border-gray-700 bg-gray-700">
                    <div class="flex items-center gap-3 mb-2">
                        <?php $gh = (string)($stu['github_username'] ?? ''); ?>
                        <?php if ($gh !== ''): ?>
                            <img src="https://avatars.githubusercontent.com/<?= urlencode($gh) ?>?s=40" alt="Avatar" class="w-10 h-10 rounded-full ring-1 ring-gray-600" onerror="this.onerror=null;this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'40\' height=\'40\' viewBox=\'0 0 32 32\'><rect width=\'32\' height=\'32\' fill=\'%23282c34\'/><circle cx=\'16\' cy=\'12\' r=\'6\' fill=\'%23abb2bf\'/><rect x=\'6\' y=\'20\' width=\'20\' height=\'10\' rx=\'5\' fill=\'%2361afef\'/></svg>'">
                        <?php else: ?>
                            <div class="w-10 h-10 rounded-full ring-1 ring-gray-600 bg-gray-700 flex items-center justify-center"><i class="fa-solid fa-user text-gray-300"></i></div>
                        <?php endif; ?>
                        <div class="flex-1">
                            <div class="font-semibold text-gray-100"><?= htmlspecialchars((string)($stu['name'] ?? '')) ?></div>
                            <div class="text-xs">
                                <?php if ($gh !== ''): ?>
                                    <a class="text-blue-400 hover:text-blue-300 transition" href="https://github.com/<?= htmlspecialchars($gh) ?>" target="_blank">@<?= htmlspecialchars($gh) ?></a>
                                <?php else: ?>
                                    <span class="text-gray-400">—</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <?php if (!empty($stu['github_token'] ?? '')): ?>
                                <span class="text-xs px-2 py-1 rounded bg-green-700 text-white inline-flex items-center"><i class="fa-brands fa-github mr-1"></i>Connecté</span>
                            <?php else: ?>
                                <span class="text-xs px-2 py-1 rounded bg-gray-700 text-gray-200 inline-flex items-center"><i class="fa-brands fa-github mr-1"></i>Non connecté</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-sm text-gray-300 mb-2">
                        <div>Repos: <span class="text-gray-100"><?= (int)($stu['repos_count'] ?? 0) ?></span></div>
                        <div>
                            Repo actif: 
                            <?php if (!empty($stu['active_repo_name'])): ?>
                                <a class="text-blue-400 hover:text-blue-300 transition" href="<?= htmlspecialchars($stu['active_repo_url'] ?? '#') ?>" target="_blank"><?= htmlspecialchars($stu['active_repo_name'] ?? '—') ?></a>
                            <?php else: ?>
                                <span class="text-gray-400">Aucun</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="flex items-center justify-between">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-200">
                            <input type="checkbox" class="stu-checkbox" value="<?= (int)($stu['id'] ?? 0) ?>" data-missing="<?= in_array((int)($stu['id'] ?? 0), $missingIds, true) ? '1' : '0' ?>" data-tasks-count="<?= (int)($stu['tasks_count'] ?? 0) ?>" data-gh="<?= (!empty($stu['github_token'] ?? '')) ? '1' : '0' ?>" data-active="<?= (!empty($stu['active_repo_name'] ?? '')) ? '1' : '0' ?>">
                            Sélectionner
                        </label>
                        <button class="px-2 py-1 rounded bg-gray-700 hover:bg-gray-600" data-user-id="<?= (int)$stu['id'] ?>" data-user-name="<?= htmlspecialchars($stu['name']) ?>" onclick="openCommitsModal(this)"><i class="fa-solid fa-clock-rotate-left mr-1"></i>Voir commits</button>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($students ?? [])): ?>
                <div class="text-gray-400">Aucun étudiant connecté à GitHub</div>
            <?php endif; ?>
        </div>
    </div>
</div>
<div id="broadcast-modal" class="fixed inset-0 hidden items-center justify-center modal">
    <div class="absolute inset-0 bg-gray-900 bg-opacity-60 modal-overlay"></div>
    <div class="relative z-10 bg-gray-800 rounded-lg shadow-xl ring-1 ring-gray-700 w-full max-w-4xl mx-4 modal-content">
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-700">
            <div class="text-lg font-semibold text-gray-100">Envoyer un template de tâche aux étudiants éligibles</div>
            <button class="inline-flex items-center justify-center w-8 h-8 rounded hover:bg-gray-700 text-gray-300" onclick="closeBroadcastModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form id="broadcast-form" class="px-4 py-4 space-y-3">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
            <textarea name="markdown" placeholder="Collez le Markdown (ex: lignes commençant par - **[LABEL] Titre** avec Priorité : P1/P2/P3)" class="w-full bg-gray-700 p-2 rounded focus:outline-none focus:ring-2 focus:ring-blue-500" rows="12" required></textarea>
            <input type="text" name="broadcast_name" placeholder="Nom de la diffusion (optionnel)" class="w-full bg-gray-700 p-2 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
            <div class="flex items-center justify-between gap-2">
                <div class="text-xs text-gray-300">Envoi uniquement aux étudiants connectés à GitHub avec un repository actif.</div>
                <div class="flex items-center gap-3">
                    <button type="submit" class="px-3 py-2 rounded-md bg-blue-600 hover:bg-blue-500 text-white">Envoyer</button>
                </div>
            </div>
            <div id="broadcast-status" class="text-sm"></div>
        </form>
    </div>
</div>

<div id="commits-modal" class="fixed inset-0 hidden items-center justify-center modal">
    <div class="absolute inset-0 bg-gray-900 bg-opacity-60 modal-overlay"></div>
    <div class="relative z-10 bg-gray-800 rounded-lg shadow-xl ring-1 ring-gray-700 w-full max-w-4xl mx-4 modal-content">
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-700">
            <div id="commits-title" class="text-lg font-semibold text-gray-100"></div>
            <button class="inline-flex items-center justify-center w-8 h-8 rounded hover:bg-gray-700 text-gray-300" onclick="closeCommitsModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="px-4 py-4 space-y-4">
            <div>
                <div class="text-xs text-gray-400 mb-1">Repository</div>
                <select id="commits-repo-select" class="bg-gray-700 p-2 rounded w-full"></select>
            </div>
            <div id="commits-list" class="space-y-2 modal-scroll scrollbar-dark"></div>
        </div>
    </div>
</div>