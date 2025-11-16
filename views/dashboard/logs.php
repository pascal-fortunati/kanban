<?php
declare(strict_types=1);
?>
<div class="max-w-7xl mx-auto">
    <div class="bg-gray-800 rounded-lg shadow ring-1 ring-gray-700 p-6 mb-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div class="text-lg font-semibold text-gray-100 inline-flex items-center gap-2">
                <i class="fa-solid fa-clipboard-list"></i>
                Logs des élèves
            </div>
            <form method="get" action="" class="flex items-center gap-2">
                <select name="period" class="bg-gray-700 text-gray-200 rounded px-2 py-1">
                    <option value="24h" <?= ($period === '24h' ? 'selected' : '') ?>>24h</option>
                    <option value="7d" <?= ($period === '7d' ? 'selected' : '') ?>>7 jours</option>
                    <option value="30d" <?= ($period === '30d' ? 'selected' : '') ?>>30 jours</option>
                </select>
                <input type="text" name="q" value="<?= htmlspecialchars((string)($q ?? '')) ?>" placeholder="Recherche (nom, email, IP, action)" class="bg-gray-700 text-gray-200 rounded px-3 py-2 w-64">
                <select name="perPage" class="bg-gray-700 text-gray-200 rounded px-2 py-1">
                    <?php $pp = (int)($perPage ?? 50); ?>
                    <option value="25" <?= ($pp === 25 ? 'selected' : '') ?>>25</option>
                    <option value="50" <?= ($pp === 50 ? 'selected' : '') ?>>50</option>
                    <option value="100" <?= ($pp === 100 ? 'selected' : '') ?>>100</option>
                    <option value="200" <?= ($pp === 200 ? 'selected' : '') ?>>200</option>
                </select>
                <input type="hidden" name="page" value="1">
                <button type="submit" class="px-3 py-2 rounded-md bg-blue-600 hover:bg-blue-500 text-white inline-flex items-center"><i class="fa-solid fa-magnifying-glass mr-2"></i>Filtrer</button>
            </form>
        </div>
        <?php if (!empty($stats) && is_array($stats)): ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-6">
            <div class="p-4 rounded bg-gray-900 ring-1 ring-gray-700">
                <div class="text-sm text-gray-400">Logs (période)</div>
                <div class="text-2xl font-semibold text-gray-100"><?= (int)($stats['total'] ?? 0) ?></div>
            </div>
            <div class="p-4 rounded bg-gray-900 ring-1 ring-gray-700">
                <div class="text-sm text-gray-400">Étudiants actifs</div>
                <div class="text-2xl font-semibold text-gray-100"><?= (int)($stats['students'] ?? 0) ?></div>
            </div>
            <div class="p-4 rounded bg-gray-900 ring-1 ring-gray-700">
                <div class="text-sm text-gray-400">Connexions</div>
                <div class="text-2xl font-semibold text-green-400"><?= (int)($stats['logins'] ?? 0) ?></div>
            </div>
            <div class="p-4 rounded bg-gray-900 ring-1 ring-gray-700">
                <div class="text-sm text-gray-400">Déconnexions</div>
                <div class="text-2xl font-semibold text-yellow-400"><?= (int)($stats['logouts'] ?? 0) ?></div>
            </div>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-6">
            <div class="p-4 rounded bg-gray-900 ring-1 ring-gray-700">
                <div class="text-sm text-gray-400 mb-2">Top Actions</div>
                <div class="space-y-2">
                    <?php foreach ((array)($stats['actions'] ?? []) as $a): ?>
                        <div class="flex items-center justify-between">
                            <span class="inline-flex items-center px-2 py-1 rounded bg-gray-800 text-gray-200 text-xs"><?= htmlspecialchars((string)($a['action'] ?? '')) ?></span>
                            <span class="text-gray-300 text-sm"><?= (int)($a['c'] ?? 0) ?></span>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($stats['actions'])): ?><div class="text-gray-500 text-sm">—</div><?php endif; ?>
                </div>
            </div>
            <div class="p-4 rounded bg-gray-900 ring-1 ring-gray-700">
                <div class="text-sm text-gray-400 mb-2">Top IP</div>
                <div class="space-y-2">
                    <?php foreach ((array)($stats['topIps'] ?? []) as $ip): ?>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-300 text-sm"><?= htmlspecialchars((string)($ip['ip'] ?? '')) ?></span>
                            <span class="text-gray-300 text-sm"><?= (int)($ip['c'] ?? 0) ?></span>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($stats['topIps'])): ?><div class="text-gray-500 text-sm">—</div><?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <div class="bg-gray-800 rounded-lg shadow ring-1 ring-gray-700 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-700">
            <thead class="bg-gray-900">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-300">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-300">Étudiant</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-300">Action</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-300">IP</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-300">User Agent</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-300">Détails</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-700">
                <?php foreach (($logs ?? []) as $row): ?>
                    <tr class="hover:bg-gray-700">
                        <td class="px-4 py-2 text-sm text-gray-300"><?php $ts = strtotime((string)($row['created_at'] ?? '')); echo htmlspecialchars($ts ? date('d/m/Y H:i:s', $ts) : (string)($row['created_at'] ?? '')); ?></td>
                        <td class="px-4 py-2 text-sm text-gray-300">
                            <div class="font-semibold text-gray-100"><?= htmlspecialchars((string)($row['name'] ?? '')) ?></div>
                            <div class="text-xs text-gray-400"><?= htmlspecialchars((string)($row['email'] ?? '')) ?></div>
                        </td>
                        <td class="px-4 py-2 text-sm text-gray-200"><span class="inline-flex items-center px-2 py-1 rounded bg-gray-700" title="<?= htmlspecialchars((string)($row['action'] ?? '')) ?>"><?= htmlspecialchars((string)($row['message'] ?? ($row['action'] ?? ''))) ?></span></td>
                        <td class="px-4 py-2 text-sm text-gray-300">
                            <div><?= htmlspecialchars((string)($row['ip'] ?? '')) ?></div>
                            <?php $d = json_decode((string)($row['data'] ?? ''), true); if (is_array($d)): ?>
                                <?php if (!empty($d['cf_ip'] ?? '')): ?><div class="text-xs text-gray-400">CF-IP: <?= htmlspecialchars((string)$d['cf_ip']) ?></div><?php endif; ?>
                                <?php if (!empty($d['true_client_ip'] ?? '')): ?><div class="text-xs text-gray-400">True-Client-IP: <?= htmlspecialchars((string)$d['true_client_ip']) ?></div><?php endif; ?>
                                <?php if (!empty($d['x_forwarded_for'] ?? '')): ?><div class="text-xs text-gray-400">XFF: <?= htmlspecialchars((string)$d['x_forwarded_for']) ?></div><?php endif; ?>
                                <?php if (!empty($d['x_real_ip'] ?? '')): ?><div class="text-xs text-gray-400">X-Real-IP: <?= htmlspecialchars((string)$d['x_real_ip']) ?></div><?php endif; ?>
                                <?php if (!empty($d['remote_addr'] ?? '')): ?><div class="text-xs text-gray-400">Remote-Addr: <?= htmlspecialchars((string)$d['remote_addr']) ?></div><?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-2 text-xs text-gray-400 truncate max-w-xs" title="<?= htmlspecialchars((string)($row['user_agent'] ?? '')) ?>"><?= htmlspecialchars((string)($row['user_agent'] ?? '')) ?></td>
                        <td class="px-4 py-2 text-xs text-gray-300">
                            <?php $d = json_decode((string)($row['data'] ?? ''), true); if (is_array($d)): ?>
                                <?php foreach ($d as $k => $v): ?>
                                    <?php if (in_array($k, ['cf_ip','true_client_ip','x_forwarded_for','x_real_ip','remote_addr'], true)) continue; ?>
                                    <div><span class="text-gray-400"><?= htmlspecialchars((string)$k) ?>:</span> <?= htmlspecialchars(is_scalar($v) ? (string)$v : json_encode($v)) ?></div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="text-gray-500">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-gray-400">Aucun log pour la période sélectionnée</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <?php if (!empty($totalPages) && (int)$totalPages > 1): ?>
        <div class="flex items-center justify-between px-4 py-3 bg-gray-900 border-t border-gray-700">
            <div class="text-sm text-gray-400">
                Page <?= (int)($page ?? 1) ?> / <?= (int)$totalPages ?> — <?= (int)($perPage ?? 50) ?> par page
            </div>
            <?php 
                $base = (string)($baseUrl ?? '');
                $qv = htmlspecialchars((string)($q ?? ''));
                $pd = htmlspecialchars((string)($period ?? '7d'));
                $ppv = (int)($perPage ?? 50);
                $pg = (int)($page ?? 1);
                $prev = max(1, $pg - 1);
                $next = min((int)$totalPages, $pg + 1);
                $mk = function($p) use ($base, $pd, $qv, $ppv) {
                    return $base . '/dashboard/logs?period=' . $pd . '&q=' . $qv . '&perPage=' . $ppv . '&page=' . (int)$p;
                };
            ?>
            <div class="inline-flex items-center gap-2">
                <a href="<?= $mk(1) ?>" class="px-3 py-2 rounded bg-gray-800 text-gray-200 hover:bg-gray-700 <?= ($pg === 1 ? 'pointer-events-none opacity-50' : '') ?>"><i class="fa-solid fa-angles-left"></i></a>
                <a href="<?= $mk($prev) ?>" class="px-3 py-2 rounded bg-gray-800 text-gray-200 hover:bg-gray-700 <?= ($pg === 1 ? 'pointer-events-none opacity-50' : '') ?>"><i class="fa-solid fa-angle-left"></i></a>
                <span class="px-3 py-2 rounded bg-gray-700 text-gray-100 text-sm"><?= (int)$pg ?></span>
                <a href="<?= $mk($next) ?>" class="px-3 py-2 rounded bg-gray-800 text-gray-200 hover:bg-gray-700 <?= ($pg >= (int)$totalPages ? 'pointer-events-none opacity-50' : '') ?>"><i class="fa-solid fa-angle-right"></i></a>
                <a href="<?= $mk((int)$totalPages) ?>" class="px-3 py-2 rounded bg-gray-800 text-gray-200 hover:bg-gray-700 <?= ($pg >= (int)$totalPages ? 'pointer-events-none opacity-50' : '') ?>"><i class="fa-solid fa-angles-right"></i></a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>