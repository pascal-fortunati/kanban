<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Dashboard;
use App\Models\Notification;
use App\Models\User;
use App\Models\Task;
use App\Models\Database;
use App\Services\GitHubClient;

class DashboardController extends BaseController
{
    private GitHubClient $client;

    public function __construct()
    {
        $this->client = new GitHubClient();
    }
    public function index(): void
    {
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $stats = Dashboard::getStats();
        $raw = User::allStudents();
        $students = [];
        foreach ($raw as $s) {
            $sid = (int)($s['id'] ?? 0);
            $reposCount = 0; $activeRepoName = null; $activeRepoUrl = null;
            try { $reposCount = \App\Models\Repository::countByUser($sid); } catch (\Throwable $e) { $reposCount = 0; }
            try {
                $activeId = \App\Models\User::getActiveRepoId($sid);
                if ($activeId) {
                    $ar = \App\Models\Repository::findByIdForUser((int)$activeId, $sid);
                    if ($ar) { $activeRepoName = (string)($ar['name'] ?? ''); $activeRepoUrl = (string)($ar['github_url'] ?? ''); }
                }
            } catch (\Throwable $e) {}
            $tasksCount = 0; try { $tasksCount = \App\Models\Task::countByUser($sid); } catch (\Throwable $e) { $tasksCount = 0; }
            $s['repos_count'] = $reposCount;
            $s['active_repo_name'] = $activeRepoName;
            $s['active_repo_url'] = $activeRepoUrl;
            $s['tasks_count'] = $tasksCount;
            $students[] = $s;
        }
        $latest = Notification::latestOfTypeForUser($uid, 'broadcast');
        $latestMissing = [];
        if ($latest) {
            $data = json_decode((string)($latest['data'] ?? ''), true);
            if (is_array($data)) { $latestMissing = (array)($data['missing_all'] ?? ($data['missing'] ?? [])); }
        }
        $history = Notification::listOfTypeForUser($uid, 'broadcast', 5);
        $this->render('dashboard/index', [
            'stats' => $stats,
            'uid' => $uid,
            'students' => $students,
            'latestBroadcastMissing' => $latestMissing,
            'broadcastHistory' => $history
        ]);
    }

    public function logs(): void
    {
        $this->requireRole('formateur');
        $q = trim((string)($_GET['q'] ?? ''));
        $period = (string)($_GET['period'] ?? '7d');
        $perPage = (int)($_GET['perPage'] ?? 50);
        if ($perPage < 10) { $perPage = 10; }
        if ($perPage > 200) { $perPage = 200; }
        $page = (int)($_GET['page'] ?? 1);
        if ($page < 1) { $page = 1; }
        $stats = \App\Models\Dashboard::getUserLogsStats($q, $period);
        $total = (int)($stats['total'] ?? 0);
        $totalPages = max(1, (int)ceil(($total ?: 0) / $perPage));
        if ($page > $totalPages) { $page = $totalPages; }
        $logs = \App\Models\Dashboard::getUserLogs($q, $period, $perPage, $page);
        $this->render('dashboard/logs', [
            'logs' => $logs,
            'q' => $q,
            'period' => $period,
            'stats' => $stats,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'totalPages' => $totalPages
        ]);
    }

    public function getStats(): void
    {
        $this->json(Dashboard::getStats());
    }

    public function getNotifications(): void
    {
        $uid = $this->requireAuth();
        $list = Notification::latestForUser($uid, 30);
        $filtered = [];
        $seen = [];
        foreach ($list as $n) {
            $type = (string)($n['type'] ?? '');
            if ($type === 'commit') {
                $data = json_decode((string)($n['data'] ?? ''), true);
                $tid = is_array($data) ? (int)($data['task_id'] ?? 0) : 0;
                if ($tid > 0) {
                    $key = 'commit:' . $tid;
                    if (!isset($seen[$key])) { $filtered[] = $n; $seen[$key] = true; }
                    continue;
                }
            }
            $filtered[] = $n;
        }
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND is_read = 0 AND type <> ?');
        $stmt->execute([$uid, 'commit']);
        $row = $stmt->fetch();
        $countNav = (int)($row['c'] ?? 0);
        $this->json(['count' => $countNav, 'items' => $filtered]);
    }

    public function getMissedBroadcasts(): void
    {
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $me = $uid > 0 ? User::findById($uid) : null;
        if (!$me || ($me['role'] ?? '') !== 'student') {
            $this->json(['error' => 'forbidden'], 403);
            return;
        }
        $tok = (string)($me['github_token'] ?? '');
        $activeRepoId = \App\Models\User::getActiveRepoId($uid);
        $hasToken = ($tok !== '');
        $hasRepo = ($activeRepoId !== null && $activeRepoId !== 0);
        $eligible = ($hasToken && $hasRepo);
        $trainers = User::allFormateurs();
        $missed = [];
        foreach ($trainers as $tr) {
            $tid = (int)($tr['id'] ?? 0);
            if ($tid <= 0) continue;
            $history = Notification::listOfTypeForUser($tid, 'broadcast', 20);
            foreach ($history as $h) {
                $data = json_decode((string)($h['data'] ?? ''), true);
                $missing = [];
                if (is_array($data)) {
                    if (!empty($data['still_missing']) && is_array($data['still_missing'])) {
                        $missing = (array)$data['still_missing'];
                    } else {
                        $missing = (array)($data['missing_all'] ?? ($data['missing'] ?? []));
                    }
                }
                if (in_array($uid, $missing, true)) {
                    $missed[] = [
                        'id' => (int)($h['id'] ?? 0),
                        'owner_id' => $tid,
                        'name' => is_array($data) ? (string)($data['name'] ?? '') : '',
                        'created_at' => (string)($h['created_at'] ?? ''),
                    ];
                }
            }
        }
        if ($eligible && !empty($missed)) {
            $existing = Notification::latestForUser($uid, 20);
            $byBid = [];
            foreach ($existing as $n) {
                if ((string)($n['type'] ?? '') !== 'broadcast_missed') continue;
                $d = json_decode((string)($n['data'] ?? ''), true);
                $bid = is_array($d) ? (int)($d['broadcast_id'] ?? 0) : 0;
                if ($bid > 0) { $byBid[$bid] = true; }
            }
            foreach ($missed as $m) {
                $bid = (int)($m['id'] ?? 0);
                if ($bid > 0 && empty($byBid[$bid])) {
                    $msg = 'Déploiement des tâches manquées: ' . (string)($m['name'] ?? '');
                    Notification::create($uid, $msg !== '' ? $msg : 'Déploiement des tâches manquées', ['broadcast_id' => $bid, 'name' => (string)($m['name'] ?? '')], 'broadcast_missed');
                }
            }
        }
        $this->logUserAction('missed_broadcasts_fetch', ['eligible' => $eligible, 'count' => (int)count($missed)]);
        $this->json(['eligible' => $eligible, 'missed' => $missed]);
    }

    public function markNotificationRead(): void
    {
        $this->requirePost();
        $uid = $this->requireAuth();
        $tok = (string)($_POST['csrf_token'] ?? '');
        if (!$this->validateCsrfToken($tok)) { $this->json(['error' => 'CSRF'], 400); }
        $id = (int)($_POST['id'] ?? 0);
        $this->json(['success' => Notification::markRead($id)]);
    }

    public function getStudentRepos(): void
    {
        $this->requireRole('formateur');
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $sid = (int)($_GET['user_id'] ?? 0);
        if ($sid <= 0) {
            $this->json(['repos' => []]);
            return;
        }
        $repos = \App\Models\Repository::byUser($sid);
        $this->json(['repos' => $repos]);
    }

    public function broadcastTemplate(): void
    {
        $this->requirePost();
        $this->requireRole('formateur');
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $tok = (string)($_POST['csrf_token'] ?? '');
        if (!$this->validateCsrfToken($tok)) { $this->json(['error' => 'CSRF'], 400); }
        $markdown = trim((string)($_POST['markdown'] ?? ''));
        $broadcastName = trim((string)($_POST['broadcast_name'] ?? ''));
        $onlyNoTasks = ((string)($_POST['only_no_tasks'] ?? '') === '1');
        if ($markdown === '') {
            $this->json(['error' => 'Contenu requis'], 400);
            return;
        }
        $palette = [
            'bug' => '#e06c75',
            'feature' => '#98c379',
            'docs' => '#c678dd',
            'improvement' => '#61afef',
            'chore' => '#d19a66',
            'bdd' => '#d19a66',
            'backend' => '#61afef',
            'front' => '#98c379',
            'frontend' => '#98c379',
            'admin' => '#c678dd',
            'test' => '#e06c75',
            'seo' => '#c678dd',
            'design' => '#c678dd'
        ];
        $lines = preg_split('/\r?\n/', $markdown);
        $tasks = [];
        $current = null;
        $buf = [];
        $timeVal = null;
        foreach ($lines as $line) {
            if (preg_match('/^-\s*\*\*\[([^\]]+)\]\s*(.*?)\*\*/', $line, $m)) {
                if ($current) {
                    $current['description'] = trim(implode("\n", $buf));
                    $current['time'] = $timeVal;
                    $tasks[] = $current;
                }
                $label = strtolower(trim($m[1]));
                $title = trim($m[2]);
                $current = ['title' => $title, 'label' => $label, 'priority' => 'medium', 'description' => '', 'time' => null];
                $buf = [];
                $timeVal = null;
            } else {
                if ($current) {
                    if (preg_match('/^---\s*$/', trim($line)) || preg_match('/^##\s+.+$/', trim($line))) {
                        $current['description'] = trim(implode("\n", $buf));
                        $current['time'] = $timeVal;
                        $tasks[] = $current;
                        $current = null;
                        $buf = [];
                        $timeVal = null;
                        continue;
                    }
                    if (preg_match('/Priorit[ée]\s*:\s*P([123])/', $line, $pm)) {
                        $p = (int)$pm[1];
                        $current['priority'] = $p === 1 ? 'high' : ($p === 3 ? 'low' : 'medium');
                        continue;
                    }
                    if (preg_match('/Temps\s*:\s*([0-9]+(?:\.[0-9]+)?\s*h)/i', $line, $tm)) {
                        $timeVal = trim($tm[1]);
                        continue;
                    }
                    $buf[] = $line;
                }
            }
        }
        if ($current) {
            $current['description'] = trim(implode("\n", $buf));
            $current['time'] = $timeVal;
            $tasks[] = $current;
        }
        if (empty($tasks)) {
            $this->json(['error' => 'Aucune tâche détectée dans le Markdown'], 400);
            return;
        }
        $students = User::allStudents();
        $created = 0;
        $eligible = 0;
        $missing = [];
        $missingAll = [];
        $pdo = Database::connection();
        foreach ($students as $s) {
            $sid = (int)$s['id'];
            $tok = (string)($s['github_token'] ?? '');
            $activeRepoId = \App\Models\User::getActiveRepoId($sid);
            $hasToken = ($tok !== '');
            $hasRepo = ($activeRepoId !== null && $activeRepoId !== 0);
            if (!$hasToken) { $missingAll[] = $sid; }
            if (!$hasRepo) { $missing[] = $sid; $missingAll[] = $sid; }
            if ($onlyNoTasks) {
                $cnt = \App\Models\Task::countByUser($sid);
                if ($cnt > 0) { continue; }
            }
            if ($hasToken && $hasRepo) { $eligible++; } else { continue; }
            $createdForStudent = 0;
            $createdIds = [];
            $repo = $hasRepo ? \App\Models\Repository::findByIdForUser((int)$activeRepoId, $sid) : null;
            $ownerRepo = $repo ? $this->parseOwnerRepo((string)($repo['github_url'] ?? ''), (string)($s['github_username'] ?? ''), (string)($repo['name'] ?? '')) : null;
            $plain = $this->decryptToken($tok);
            $issueUrls = [];
            foreach ($tasks as $t) {
                $labelName = strtolower($t['label'] ?? '');
                $color = $palette[$labelName] ?? '#abb2bf';
                $labels = [['name' => $labelName, 'color' => $color]];
                $prioLabel = $t['priority'] === 'high' ? 'p1' : ($t['priority'] === 'low' ? 'p3' : 'p2');
                $labels[] = ['name' => $prioLabel, 'color' => ($t['priority'] === 'high' ? '#e06c75' : ($t['priority'] === 'low' ? '#98c379' : '#d19a66'))];
                if (!empty($t['time'])) {
                    $labels[] = ['name' => 'temps:' . strtolower($t['time']), 'color' => '#61afef'];
                }
                $labelsJson = json_encode($labels);
                $taskId = Task::create($t['title'], $t['description'], $t['priority'], $sid, $hasRepo ? (int)$activeRepoId : null, $labelsJson);
                if ($taskId) {
                    $created++;
                    $createdForStudent++;
                    $createdIds[] = $taskId;
                    if ($hasToken && $hasRepo && $ownerRepo && $plain) {
                        $labelUpper = strtoupper($labelName);
                        $prioUpper = ($t['priority'] === 'high') ? 'P1' : (($t['priority'] === 'low') ? 'P3' : 'P2');
                        $pairs = [];
                        $pairs[$labelUpper] = strtoupper(ltrim(($palette[$labelName] ?? '#abb2bf'), '#'));
                        $pairs[$prioUpper] = ($prioUpper === 'P1' ? 'E06C75' : ($prioUpper === 'P3' ? '98C379' : 'D19A66'));
                        $this->ensureRepoLabels($ownerRepo, (string)$plain, $pairs);
                        $lbls = [$labelUpper, $prioUpper];
                        $body = [ 'title' => (string)$t['title'], 'body' => (string)$t['description'], 'labels' => $lbls ];
                        $res = $this->client->createIssue((string)$plain, $ownerRepo['owner'], $ownerRepo['repo'], $body);
                        if (is_array($res) && !empty($res['html_url'])) { $issueUrls[] = (string)$res['html_url']; }
                    }
                }
            }
            if ($createdForStudent > 0) {
                Notification::create($sid, 'Nouvelles tâches créées: ' . $createdForStudent, ['task_ids' => $createdIds, 'count' => $createdForStudent, 'bulk' => true, 'issues' => count($issueUrls), 'issue_urls' => $issueUrls]);
            }
        }
        Notification::create($uid, 'Template des tâches créées', [
            'created' => $created,
            'tasks' => count($tasks),
            'eligible' => $eligible,
            'missing' => $missing,
            'missing_count' => count($missing),
            'missing_all' => $missingAll,
            'missing_all_count' => count($missingAll),
            'only_no_tasks' => $onlyNoTasks ? 1 : 0,
            'name' => $broadcastName,
            'markdown' => $markdown
        ], 'broadcast');
        $this->json(['success' => true, 'created' => $created, 'tasks' => count($tasks), 'eligible' => $eligible, 'missing' => $missing, 'missing_count' => count($missing)]);
    }

    public function redeployFromBroadcast(): void
    {
        $this->requirePost();
        $this->requireRole('formateur');
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $tok = (string)($_POST['csrf_token'] ?? '');
        if (!$this->validateCsrfToken($tok)) { $this->json(['error' => 'CSRF'], 400); }
        $bid = (int)($_POST['broadcast_id'] ?? 0);
        $idsRaw = trim((string)($_POST['student_ids'] ?? ''));
        if ($bid <= 0) { $this->json(['error' => 'no_broadcast'], 400); return; }
        if ($idsRaw === '') { $this->json(['error' => 'no_selection'], 400); return; }
        $ids = array_values(array_unique(array_map(function($x){ return (int)trim((string)$x); }, explode(',', $idsRaw))));
        $ids = array_filter($ids, function($n){ return $n > 0; });
        if (empty($ids)) { $this->json(['error' => 'no_selection'], 400); return; }
        $rec = Notification::findByIdForUser($uid, $bid);
        if (!$rec) { $this->json(['error' => 'not_found'], 404); return; }
        $broadcastNameOverride = trim((string)($_POST['broadcast_name'] ?? ''));
        $recData = json_decode((string)($rec['data'] ?? ''), true);
        $sourceName = is_array($recData) ? trim((string)($recData['name'] ?? '')) : '';
        $snapshotMd = is_array($recData) ? (string)($recData['markdown'] ?? '') : '';
        $mdToUse = '';
        if ($snapshotMd !== '') {
            $mdToUse = $snapshotMd;
        } else {
            $mdToUse = $this->loadTemplateMarkdown();
            if ($mdToUse === '') { $this->json(['error' => 'no_template_file'], 400); return; }
        }
        $tasks = $this->parseTasksFromMarkdown($mdToUse);
        if (!is_array($tasks) || empty($tasks)) { $this->json(['error' => 'no_tasks_template'], 400); return; }
        $created = 0; $eligible = 0; $stillMissing = [];
        foreach ($ids as $sid) {
            $student = User::findById($sid);
            if (!$student) { continue; }
            $tok = (string)($student['github_token'] ?? '');
            $activeRepoId = \App\Models\User::getActiveRepoId($sid);
            $hasToken = ($tok !== '');
            $hasRepo = ($activeRepoId !== null && $activeRepoId !== 0);
            if (!$hasToken || !$hasRepo) { $stillMissing[] = $sid; }
            if ($hasToken && $hasRepo) { $eligible++; } else { continue; }
            $createdForStudent = 0; $createdIds = [];
            $repo = $hasRepo ? \App\Models\Repository::findByIdForUser((int)$activeRepoId, $sid) : null;
            $ownerRepo = $repo ? $this->parseOwnerRepo((string)($repo['github_url'] ?? ''), (string)($student['github_username'] ?? ''), (string)($repo['name'] ?? '')) : null;
            $plain = $this->decryptToken($tok);
            $issueUrls = [];
            foreach ($tasks as $t) {
                $labelName = strtolower($t['label'] ?? '');
                $color = [ 'bug' => '#e06c75','feature' => '#98c379','docs' => '#c678dd','improvement' => '#61afef','chore' => '#d19a66','bdd' => '#d19a66','backend' => '#61afef','front' => '#98c379','frontend' => '#98c379','admin' => '#c678dd','test' => '#e06c75','seo' => '#c678dd','design' => '#c678dd' ][$labelName] ?? '#abb2bf';
                $labels = [['name' => $labelName, 'color' => $color]];
                $prioLabel = ($t['priority'] ?? 'medium') === 'high' ? 'p1' : (($t['priority'] ?? 'medium') === 'low' ? 'p3' : 'p2');
                $labels[] = ['name' => $prioLabel, 'color' => (($t['priority'] ?? 'medium') === 'high' ? '#e06c75' : (($t['priority'] ?? 'medium') === 'low' ? '#98c379' : '#d19a66'))];
                if (!empty($t['time'] ?? '')) { $labels[] = ['name' => 'temps:' . strtolower($t['time']), 'color' => '#61afef']; }
                $labelsJson = json_encode($labels);
                $taskId = Task::create((string)$t['title'], (string)$t['description'], (string)($t['priority'] ?? 'medium'), $sid, $hasRepo ? (int)$activeRepoId : null, $labelsJson);
                if ($taskId) {
                    $created++;
                    $createdForStudent++;
                    $createdIds[] = $taskId;
                    if ($hasToken && $hasRepo && $ownerRepo && $plain) {
                        $labelUpper = strtoupper($labelName);
                        $prioUpper = (($t['priority'] ?? 'medium') === 'high') ? 'P1' : ((($t['priority'] ?? 'medium') === 'low') ? 'P3' : 'P2');
                        $pairs = [];
                        $pairs[$labelUpper] = strtoupper(ltrim(([ 'bug' => '#E06C75','feature' => '#98C379','docs' => '#C678DD','improvement' => '#61AFEF','chore' => '#D19A66','bdd' => '#D19A66','backend' => '#61AFEF','front' => '#98C379','frontend' => '#98C379','admin' => '#C678DD','test' => '#E06C75','seo' => '#C678DD','design' => '#C678DD' ][$labelName] ?? '#ABB2BF'), '#'));
                        $pairs[$prioUpper] = ($prioUpper === 'P1' ? 'E06C75' : ($prioUpper === 'P3' ? '98C379' : 'D19A66'));
                        $this->ensureRepoLabels($ownerRepo, (string)$plain, $pairs);
                        $lbls = [$labelUpper, $prioUpper];
                        $body = [ 'title' => (string)$t['title'], 'body' => (string)$t['description'], 'labels' => $lbls ];
                        $res = $this->client->createIssue((string)$plain, $ownerRepo['owner'], $ownerRepo['repo'], $body);
                        if (is_array($res) && !empty($res['html_url'])) { $issueUrls[] = (string)$res['html_url']; }
                    }
                }
            }
            if ($createdForStudent > 0) {
                Notification::create($sid, 'Nouvelles tâches créées: ' . $createdForStudent, ['task_ids' => $createdIds, 'count' => $createdForStudent, 'bulk' => true, 'issues' => count($issueUrls), 'issue_urls' => $issueUrls]);
            }
        }
        $updated = is_array($recData) ? $recData : [];
        $remSel = function(array $arr) use ($ids): array {
            return array_values(array_filter(array_map(function($x){ return (int)$x; }, $arr), function($n) use ($ids){ return $n > 0 && !in_array($n, $ids, true); }));
        };
        $updated['missing'] = isset($updated['missing']) && is_array($updated['missing']) ? $remSel($updated['missing']) : [];
        $updated['missing_all'] = isset($updated['missing_all']) && is_array($updated['missing_all']) ? $remSel($updated['missing_all']) : [];
        $updated['missing_count'] = count($updated['missing']);
        $updated['missing_all_count'] = count($updated['missing_all']);
        $updated['still_missing'] = $stillMissing;
        $updated['still_missing_count'] = count($stillMissing);
        $updated['selected'] = $ids;
        $updated['name'] = ($broadcastNameOverride !== '' ? $broadcastNameOverride : $sourceName);
        $updated['markdown'] = $mdToUse;
        $updated['redeployed_students'] = array_values(array_unique(array_map(function($x){ return (int)$x; }, array_merge((array)($updated['redeployed_students'] ?? []), $ids))));
        $updated['created'] = $created;
        $updated['eligible'] = $eligible;
        Notification::updateByIdForUser($uid, $bid, $updated, 'Redéploiement diffusion #' . $bid);
        $this->json(['success' => true, 'created' => $created, 'eligible' => $eligible, 'still_missing' => $stillMissing, 'still_missing_count' => count($stillMissing), 'selected_count' => count($ids), 'broadcast_id' => $bid]);
    }

    private function loadTemplateMarkdown(): string
    {
        $baseDir = dirname(__DIR__);
        $path = $baseDir . DIRECTORY_SEPARATOR . 'template.md';
        try {
            if (!file_exists($path)) return '';
            $s = (string)file_get_contents($path);
            return trim($s);
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function parseTasksFromMarkdown(string $markdown): array
    {
        $lines = preg_split('/\r?\n/', $markdown);
        $tasks = [];
        $current = null;
        $buf = [];
        $timeVal = null;
        foreach ($lines as $line) {
            if (preg_match('/^-\s*\*\*\[([^\]]+)\]\s*(.*?)\*\*/', $line, $m)) {
                if ($current) {
                    $current['description'] = trim(implode("\n", $buf));
                    $current['time'] = $timeVal;
                    $tasks[] = $current;
                }
                $label = strtolower(trim($m[1]));
                $title = trim($m[2]);
                $current = ['title' => $title, 'label' => $label, 'priority' => 'medium', 'description' => '', 'time' => null];
                $buf = [];
                $timeVal = null;
            } else {
                if ($current) {
                    if (preg_match('/^---\s*$/', trim($line)) || preg_match('/^##\s+.+$/', trim($line))) {
                        $current['description'] = trim(implode("\n", $buf));
                        $current['time'] = $timeVal;
                        $tasks[] = $current;
                        $current = null;
                        $buf = [];
                        $timeVal = null;
                        continue;
                    }
                    if (preg_match('/Priorit[ée]\s*:\s*P([123])/', $line, $pm)) {
                        $p = (int)$pm[1];
                        $current['priority'] = $p === 1 ? 'high' : ($p === 3 ? 'low' : 'medium');
                        continue;
                    }
                    if (preg_match('/Temps\s*:\s*([0-9]+(?:\.[0-9]+)?\s*h)/i', $line, $tm)) {
                        $timeVal = trim($tm[1]);
                        continue;
                    }
                    $buf[] = $line;
                }
            }
        }
        if ($current) {
            $current['description'] = trim(implode("\n", $buf));
            $current['time'] = $timeVal;
            $tasks[] = $current;
        }
        return $tasks;
    }

    private function ensureRepoLabels(array $ownerRepo, string $token, array $pairs): void
    {
        if ($token === '' || empty($pairs)) return;
        $list = $this->client->listLabels($token, $ownerRepo['owner'], $ownerRepo['repo'], 100);
        $existing = [];
        if (is_array($list)) {
            foreach ($list as $l) { $existing[strtoupper((string)($l['name'] ?? ''))] = strtolower((string)($l['color'] ?? '')); }
        }
        foreach ($pairs as $name => $color) {
            $n = (string)$name; $c = strtolower((string)$color);
            if (isset($existing[strtoupper($n)])) {
                $curr = (string)$existing[strtoupper($n)];
                if ($curr !== $c) {
                    $this->client->updateLabelColor($token, $ownerRepo['owner'], $ownerRepo['repo'], $n, $c);
                }
            } else {
                $this->client->createLabel($token, $ownerRepo['owner'], $ownerRepo['repo'], $n, $c);
            }
        }
    }

    public function clearTasks(): void
    {
        $this->requirePost();
        $this->requireRole('formateur');
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $tok = (string)($_POST['csrf_token'] ?? '');
        if (!$this->validateCsrfToken($tok)) { $this->json(['error' => 'CSRF'], 400); }
        $pdo = Database::connection();
        try {
            $deleted = $pdo->exec('DELETE FROM tasks');
            $this->json(['success' => true, 'deleted' => (int)$deleted]);
        } catch (\Throwable $e) {
            $this->json(['error' => 'delete_failed'], 500);
        }
    }
    public function deleteBroadcast(): void
    {
        $this->requirePost();
        $this->requireRole('formateur');
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $tok = (string)($_POST['csrf_token'] ?? '');
        if (!$this->validateCsrfToken($tok)) { $this->json(['error' => 'CSRF'], 400); }
        $bid = (int)($_POST['broadcast_id'] ?? 0);
        if ($bid <= 0) { $this->json(['error' => 'no_broadcast'], 400); return; }
        $rec = Notification::findByIdForUser($uid, $bid);
        if (!$rec) { $this->json(['error' => 'not_found'], 404); return; }
        if ((string)($rec['type'] ?? '') !== 'broadcast') { $this->json(['error' => 'invalid_type'], 400); return; }
        $ok = Notification::deleteByIdForUser($uid, $bid);
        if ($ok) { $this->json(['success' => true]); } else { $this->json(['error' => 'delete_failed'], 500); }
    }

    public function redeploySelfFromBroadcast(): void
    {
        $this->requirePost();
        $uid = $this->requireAuth();
        $tok = (string)($_POST['csrf_token'] ?? '');
        if (!$this->validateCsrfToken($tok)) { $this->json(['error' => 'CSRF'], 400); }
        $me = $uid > 0 ? User::findById($uid) : null;
        if (!$me || ($me['role'] ?? '') !== 'student') { $this->json(['error' => 'forbidden'], 403); return; }
        $bid = (int)($_POST['broadcast_id'] ?? 0);
        $ownerId = (int)($_POST['owner_id'] ?? 0);
        if ($bid <= 0 || $ownerId <= 0) { $this->json(['error' => 'invalid'], 400); return; }
        $rec = Notification::findByIdForUser($ownerId, $bid);
        if (!$rec || (string)($rec['type'] ?? '') !== 'broadcast') { $this->json(['error' => 'not_found'], 404); return; }
        $tokEnc = (string)($me['github_token'] ?? '');
        $activeRepoId = \App\Models\User::getActiveRepoId($uid);
        $hasToken = ($tokEnc !== '');
        $hasRepo = ($activeRepoId !== null && $activeRepoId !== 0);
        if (!$hasToken || !$hasRepo) { $this->json(['error' => 'not_eligible'], 400); return; }
        $data = json_decode((string)($rec['data'] ?? ''), true);
        $name = is_array($data) ? (string)($data['name'] ?? '') : '';
        $snapshotMd = is_array($data) ? (string)($data['markdown'] ?? '') : '';
        $md = $snapshotMd !== '' ? $snapshotMd : $this->loadTemplateMarkdown();
        if ($md === '') { $this->json(['error' => 'no_template'], 400); return; }
        $tasks = $this->parseTasksFromMarkdown($md);
        if (!is_array($tasks) || empty($tasks)) { $this->json(['error' => 'no_tasks'], 400); return; }
        $repo = \App\Models\Repository::findByIdForUser((int)$activeRepoId, $uid);
        $ownerRepo = $repo ? $this->parseOwnerRepo((string)($repo['github_url'] ?? ''), (string)($me['github_username'] ?? ''), (string)($repo['name'] ?? '')) : null;
        $plain = $this->decryptToken($tokEnc);
        $created = 0; $createdIds = []; $issueUrls = [];
        foreach ($tasks as $t) {
            $labelName = strtolower((string)($t['label'] ?? ''));
            $color = [ 'bug' => '#e06c75','feature' => '#98c379','docs' => '#c678dd','improvement' => '#61afef','chore' => '#d19a66','bdd' => '#d19a66','backend' => '#61afef','front' => '#98c379','frontend' => '#98c379','admin' => '#c678dd','test' => '#e06c75','seo' => '#c678dd','design' => '#c678dd' ][$labelName] ?? '#abb2bf';
            $labels = [['name' => $labelName, 'color' => $color]];
            $prio = (string)($t['priority'] ?? 'medium');
            $prioLabel = $prio === 'high' ? 'p1' : ($prio === 'low' ? 'p3' : 'p2');
            $labels[] = ['name' => $prioLabel, 'color' => ($prio === 'high' ? '#e06c75' : ($prio === 'low' ? '#98c379' : '#d19a66'))];
            if (!empty((string)($t['time'] ?? ''))) { $labels[] = ['name' => 'temps:' . strtolower((string)$t['time']), 'color' => '#61afef']; }
            $labelsJson = json_encode($labels);
            $taskId = Task::create((string)$t['title'], (string)$t['description'], $prio, $uid, (int)$activeRepoId, $labelsJson);
            if ($taskId) {
                $created++; $createdIds[] = $taskId;
                if ($ownerRepo && $plain) {
                    $labelUpper = strtoupper($labelName);
                    $prioUpper = ($prio === 'high') ? 'P1' : (($prio === 'low') ? 'P3' : 'P2');
                    $pairs = [];
                    $pairs[$labelUpper] = strtoupper(ltrim(([ 'bug' => '#E06C75','feature' => '#98C379','docs' => '#C678DD','improvement' => '#61AFEF','chore' => '#D19A66','bdd' => '#D19A66','backend' => '#61AFEF','front' => '#98C379','frontend' => '#98C379','admin' => '#C678DD','test' => '#E06C75','seo' => '#C678DD','design' => '#C678DD' ][$labelName] ?? '#ABB2BF'), '#'));
                    $pairs[$prioUpper] = ($prioUpper === 'P1' ? 'E06C75' : ($prioUpper === 'P3' ? '98C379' : 'D19A66'));
                    $this->ensureRepoLabels($ownerRepo, (string)$plain, $pairs);
                    $lbls = [$labelUpper, $prioUpper];
                    $body = [ 'title' => (string)$t['title'], 'body' => (string)$t['description'], 'labels' => $lbls ];
                    $res = $this->client->createIssue((string)$plain, $ownerRepo['owner'], $ownerRepo['repo'], $body);
                    if (is_array($res) && !empty($res['html_url'])) { $issueUrls[] = (string)$res['html_url']; }
                }
            }
        }
        if ($created > 0) {
            Notification::create($uid, 'Tâches créées via redéploiement: ' . $created, ['task_ids' => $createdIds, 'count' => $created, 'issues' => count($issueUrls), 'issue_urls' => $issueUrls], 'broadcast_redeploy');
        }
        try {
            $updated = is_array($data) ? $data : [];
            $rem = function(array $arr) use ($uid): array { return array_values(array_filter(array_map(function($x){ return (int)$x; }, $arr), function($n) use ($uid){ return $n !== $uid; })); };
            $missing = isset($updated['missing']) && is_array($updated['missing']) ? $updated['missing'] : [];
            $missingAll = isset($updated['missing_all']) && is_array($updated['missing_all']) ? $updated['missing_all'] : [];
            $still = isset($updated['still_missing']) && is_array($updated['still_missing']) ? $updated['still_missing'] : [];
            $updated['missing'] = $rem($missing);
            $updated['missing_all'] = $rem($missingAll);
            $updated['still_missing'] = $rem($still);
            $updated['missing_count'] = count($updated['missing']);
            $updated['missing_all_count'] = count($updated['missing_all']);
            $updated['still_missing_count'] = count($updated['still_missing']);
            $list = isset($updated['redeployed_students']) && is_array($updated['redeployed_students']) ? $updated['redeployed_students'] : [];
            $list[] = $uid; $updated['redeployed_students'] = array_values(array_unique(array_map(function($x){ return (int)$x; }, $list)));
            Notification::updateByIdForUser($ownerId, $bid, $updated, 'Diffusion mise à jour');
        } catch (\Throwable $e) {}
        try {
            $missList = Notification::listOfTypeForUser($uid, 'broadcast_missed', 20);
            foreach ($missList as $n) {
                $nid = (int)($n['id'] ?? 0);
                $nd = json_decode((string)($n['data'] ?? ''), true);
                $nbid = is_array($nd) ? (int)($nd['broadcast_id'] ?? 0) : 0;
                if ($nid > 0 && $nbid === $bid) { Notification::markRead($nid); }
            }
        } catch (\Throwable $e) {}
        $this->json(['success' => true, 'created' => $created, 'broadcast_id' => $bid, 'owner_id' => $ownerId]);
    }
}
