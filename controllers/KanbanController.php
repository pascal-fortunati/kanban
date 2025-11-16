<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Task;
use App\Models\Notification;
use App\Models\User;
use App\Models\Repository;
use App\Models\Database;
use App\Services\GitHubClient;

class KanbanController extends BaseController
{
    private GitHubClient $client;

    public function __construct()
    {
        $this->client = new GitHubClient();
    }

    public function index(): void
    {
        $uid = (int)($_SESSION['user_id'] ?? 0);
        if ($uid > 0) {
            $todo = Task::byStatusForUser('todo', $uid);
            $inProgress = Task::byStatusForUser('in_progress', $uid);
            $review = Task::byStatusForUser('review', $uid);
            $done = Task::byStatusForUser('done', $uid);
        } else {
            $todo = [];
            $inProgress = [];
            $review = [];
            $done = [];
        }
        $this->render('kanban/index', [
            'todo' => $todo,
            'inProgress' => $inProgress,
            'review' => $review,
            'done' => $done,
        ]);
    }

    public function move(): void
    {
        $this->requirePost();
        $input = json_decode(file_get_contents('php://input'), true);
        $tok = (string)($input['csrf_token'] ?? '');
        if (!$this->validateCsrfToken($tok)) { $this->json(['error' => 'CSRF'], 400); }
        $uid = $this->requireAuth();
        $id = (int)($input['id'] ?? 0);
        $status = (string)($input['status'] ?? '');
        $allowed = ['todo', 'in_progress', 'review', 'done'];
        if ($id <= 0 || !in_array($status, $allowed, true)) { $this->json(['error' => 'Invalid data'], 400); return; }
        $task = Task::findById($id);
        if (!$task) { $this->json(['error' => 'not_found'], 404); return; }
        if ((int)($task['user_id'] ?? 0) !== $uid) { $this->json(['error' => 'forbidden'], 403); return; }
        $old = (string)($task['status'] ?? '');
        $labelsMap = ['todo' => 'À faire', 'in_progress' => 'En cours', 'review' => 'Revue', 'done' => 'Terminé'];
        $oldLabel = $labelsMap[$old] ?? $old;
        $newLabel = $labelsMap[$status] ?? $status;
        if ($old === $status) { $this->json(['success' => true]); return; }
        $ok = Task::move($id, $status);
        if (!$ok) { $this->json(['error' => 'Update failed'], 500); return; }
        $student = \App\Models\User::findById($uid);
        $name = (string)($student['name'] ?? 'Étudiant');
        $title = (string)($task['title'] ?? ('#'.$id));
        $trainers = \App\Models\User::allFormateurs();
        $sent = false;
        try {
            $rid = (int)($task['repo_id'] ?? 0);
            if ($rid > 0) {
                $repo = Repository::findByIdForUser($rid, $uid);
                $ownerRepo = $repo ? $this->parseOwnerRepo((string)($repo['github_url'] ?? ''), (string)($student['github_username'] ?? ''), (string)($repo['name'] ?? '')) : null;
                $plain = $this->decryptToken((string)($student['github_token'] ?? ''));
                if ($plain && $ownerRepo) {
                    $path = 'kanban.log';
                    $existing = $this->client->getContent((string)$plain, $ownerRepo['owner'], $ownerRepo['repo'], $path);
                    $sha = null; $body = '';
                    if (is_array($existing) && !empty($existing['sha']) && !empty($existing['content'])) {
                        $sha = (string)$existing['sha'];
                        $curr = base64_decode((string)$existing['content']);
                        if (is_string($curr)) { $body = $curr; }
                    }
                    $line = '[' . date('Y-m-d H:i:s') . '] ' . $name . ' a déplacé ' . $title . ' de ' . $oldLabel . ' vers ' . $newLabel;
                    $newContent = $body === '' ? ($line . "\n") : ($body . $line . "\n");
                    $res = $this->client->putContent((string)$plain, $ownerRepo['owner'], $ownerRepo['repo'], $path, $name . ' a déplacé ' . $title . ' de ' . $oldLabel . ' vers ' . $newLabel, base64_encode($newContent), $sha);
                    if (is_array($res) && !empty($res['commit']['sha'])) {
                        $csha = (string)$res['commit']['sha'];
                        $pdo = Database::connection();
                        $stmt = $pdo->prepare('INSERT INTO commits (message, sha, user_id, repo_id, task_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
                        $stmt->execute([$name . ' a déplacé ' . $title . ' de ' . $oldLabel . ' vers ' . $newLabel, $csha, (int)$uid, (int)$rid, (int)$id]);
                        foreach ($trainers as $tr) {
                            $tid = (int)($tr['id'] ?? 0);
                            if ($tid > 0) { Notification::create($tid, $name . ' a déplacé ' . $title . ' de ' . $oldLabel . ' vers ' . $newLabel, ['student_id' => (int)$uid, 'repo_id' => (int)$rid, 'task_id' => (int)$id, 'sha' => $csha], 'commit'); }
                        }
                        $sent = true;
                    }
                }
            }
        } catch (\Throwable $e) {}
        if (!$sent) {
            foreach ($trainers as $tr) {
                $tid = (int)($tr['id'] ?? 0);
                if ($tid > 0) { Notification::create($tid, $name . ' a déplacé ' . $title . ' de ' . $oldLabel . ' vers ' . $newLabel, ['task_id' => $id, 'from' => $old, 'to' => $status, 'student_id' => $uid]); }
            }
        }
        $this->logUserAction('task_move', ['task_id' => (int)$id, 'from' => $old, 'to' => $status]);
        $this->json(['success' => true]);
    }

    public function create(): void
    {
        $this->requirePost();
        $uid = $this->requireAuth();
        $tok = (string)($_POST['csrf_token'] ?? '');
        if (!$this->validateCsrfToken($tok)) { $this->json(['error' => 'CSRF'], 400); }
        $title = trim((string)($_POST['title'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $priority = (string)($_POST['priority'] ?? 'medium');
        $labelsRaw = trim((string)($_POST['labels'] ?? ''));
        $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : null;
        $repoId = isset($_POST['repo_id']) ? (int)$_POST['repo_id'] : (isset($_SESSION['active_repo_id']) ? (int)$_SESSION['active_repo_id'] : null);
        if ($title === '') { $this->json(['error' => 'Titre requis'], 400); return; }
        $palette = ['bug' => '#e06c75','feature' => '#98c379','docs' => '#c678dd','improvement' => '#61afef','chore' => '#d19a66'];
        $labels = [];
        if ($labelsRaw !== '') {
            $items = array_filter(array_map('trim', explode(',', $labelsRaw)));
            foreach ($items as $item) {
                $name = $item;
                $color = $palette[strtolower($name)] ?? '#abb2bf';
                if (strpos($item, ':') !== false) {
                    [$n, $c] = array_map('trim', explode(':', $item, 2));
                    $name = $n !== '' ? $n : $name;
                    $color = preg_match('/^#?[0-9a-fA-F]{6}$/', $c) ? (strpos($c, '#')===0?$c:'#'.$c) : $color;
                }
                $labels[] = ['name' => $name, 'color' => $color];
            }
        }
        $labelsJson = $labels ? json_encode($labels) : null;
        $ownerId = $userId !== null ? $userId : ($uid > 0 ? $uid : null);
        $id = Task::create($title, $description, $priority, $ownerId, $repoId, $labelsJson);
        if (!$id) { $this->json(['error' => 'Création impossible'], 500); return; }
        if ($uid > 0) { \App\Models\Notification::create($uid, 'Nouvelle tâche: ' . $title, ['task_id' => $id]); }
        try {
            if ($ownerId && $repoId) {
                $stu = User::findById((int)$ownerId);
                $repo = Repository::findByIdForUser((int)$repoId, (int)$ownerId);
                $tokenEnc = is_array($stu) ? (string)($stu['github_token'] ?? '') : '';
                $ownerRepo = $repo ? $this->parseOwnerRepo((string)($repo['github_url'] ?? ''), (string)($stu['github_username'] ?? ''), (string)($repo['name'] ?? '')) : null;
                $plain = $this->decryptToken($tokenEnc);
                if ($plain && $ownerRepo) {
                    $path = 'tasks/task-' . (int)$id . '.md';
                    $content = '# ' . $title . "\n\n" . $description;
                    $res = $this->client->putContent((string)$plain, $ownerRepo['owner'], $ownerRepo['repo'], $path, 'Nouvelle tâche créée: ' . $title, base64_encode($content));
                    if (is_array($res) && !empty($res['commit']['sha'])) {
                        $sha = (string)$res['commit']['sha'];
                        $pdo = Database::connection();
                        $stmt = $pdo->prepare('INSERT INTO commits (message, sha, user_id, repo_id, task_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
                        $stmt->execute(['Nouvelle tâche créée: ' . $title, $sha, (int)$ownerId, (int)$repoId, (int)$id]);
                        $trainers = User::allFormateurs();
                        foreach ($trainers as $tr) {
                            $tid = (int)($tr['id'] ?? 0);
                            if ($tid > 0) { Notification::create($tid, 'Nouvelle tâche créée: ' . $title, ['student_id' => (int)$ownerId, 'repo_id' => (int)$repoId, 'task_id' => (int)$id, 'sha' => $sha], 'commit'); }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {}
        $this->logUserAction('task_create', ['task_id' => (int)$id, 'title' => $title, 'priority' => $priority]);
        $this->json(['success' => true, 'task' => ['id' => $id, 'title' => $title, 'description' => $description, 'priority' => $priority, 'status' => 'todo', 'labels' => $labels]]);
    }

    public function update(): void
    {
        $this->requirePost();
        $uid = $this->requireAuth();
        $tok = (string)($_POST['csrf_token'] ?? '');
        if (!$this->validateCsrfToken($tok)) { $this->json(['error' => 'CSRF'], 400); }
        $id = (int)($_POST['id'] ?? 0);
        $title = trim((string)($_POST['title'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $priority = (string)($_POST['priority'] ?? 'medium');
        $labelsRaw = trim((string)($_POST['labels'] ?? ''));
        if ($id <= 0 || $title === '') { $this->json(['error' => 'Invalid data'], 400); return; }
        $task = Task::findById($id);
        if (!$task || (int)($task['user_id'] ?? 0) !== $uid) { $this->json(['error' => 'forbidden'], 403); return; }
        $palette = [ 'bug' => '#e06c75','feature' => '#98c379','docs' => '#c678dd','improvement' => '#61afef','chore' => '#d19a66' ];
        $labels = [];
        if ($labelsRaw !== '') {
            $items = array_filter(array_map('trim', explode(',', $labelsRaw)));
            foreach ($items as $item) {
                $name = $item;
                $color = $palette[strtolower($name)] ?? '#abb2bf';
                if (strpos($item, ':') !== false) {
                    [$n, $c] = array_map('trim', explode(':', $item, 2));
                    $name = $n !== '' ? $n : $name;
                    $color = preg_match('/^#?[0-9a-fA-F]{6}$/', $c) ? (strpos($c, '#')===0?$c:'#'.$c) : $color;
                }
                $labels[] = ['name' => $name, 'color' => $color];
            }
        }
        $labelsJson = $labels ? json_encode($labels) : null;
        $ok = Task::updateForUser($id, $uid, $title, $description, $priority, $labelsJson);
        if (!$ok) { $this->json(['error' => 'Update failed'], 500); return; }
        $this->logUserAction('task_update', ['task_id' => (int)$id, 'title' => $title, 'priority' => $priority]);
        $this->json(['success' => true, 'task' => ['id' => $id, 'title' => $title, 'description' => $description, 'priority' => $priority, 'status' => (string)($task['status'] ?? 'todo'), 'labels' => $labels]]);
    }

    public function delete(): void
    {
        $this->requirePost();
        $uid = $this->requireAuth();
        $tok = (string)($_POST['csrf_token'] ?? '');
        if (!$this->validateCsrfToken($tok)) { $this->json(['error' => 'CSRF'], 400); }
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { $this->json(['error' => 'Invalid data'], 400); return; }
        $task = Task::findById($id);
        if (!$task || (int)($task['user_id'] ?? 0) !== $uid) { $this->json(['error' => 'forbidden'], 403); return; }
        $ok = Task::deleteByUser($id, $uid);
        if (!$ok) { $this->json(['error' => 'Delete failed'], 500); return; }
        $this->logUserAction('task_delete', ['task_id' => (int)$id]);
        $this->json(['success' => true]);
    }

    public function getTask(): void
    {
        $uid = $this->requireAuth();
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) { $this->json(['error' => 'invalid_id'], 400); return; }
        $task = Task::findById($id);
        if (!$task || (int)($task['user_id'] ?? 0) !== $uid) { $this->json(['error' => 'forbidden'], 403); return; }
        $labels = [];
        $raw = (string)($task['labels'] ?? '');
        if ($raw !== '') {
            try { $labels = json_decode($raw, true) ?: []; } catch (\Throwable $e) { $labels = []; }
        }
        $this->json(['success' => true, 'task' => [
            'id' => (int)$task['id'],
            'title' => (string)$task['title'],
            'description' => (string)$task['description'],
            'priority' => (string)$task['priority'],
            'status' => (string)$task['status'],
            'labels' => $labels,
        ]]);
    }
}