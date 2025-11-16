<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Database;
use App\Models\User;
use App\Models\Notification;
use App\Services\GitHubClient;
use App\Services\GitHubException;
use App\Services\GitHubAuthException;
use App\Services\GitHubRateLimitException;
use App\Services\GitHubNotFoundException;

class GitHubController extends BaseController
{
    private GitHubClient $client;

    public function __construct()
    {
        $this->client = new GitHubClient();
    }

    public function authenticate(): void
    {
        $uid = $this->requireAuth();
        $cfg = $this->config();
        
        if (($cfg['client_id'] ?? '') === '' || ($cfg['client_secret'] ?? '') === '') {
            $this->json(['error' => 'config'], 500);
            return;
        }
        
        $redirect = $this->absoluteUrl('/github/callback', true);
        $state = bin2hex(random_bytes(16));
        $_SESSION['github_oauth_state'] = $state;
        
        $params = [
            'client_id' => $cfg['client_id'],
            'redirect_uri' => $redirect,
            'scope' => $cfg['scope'] ?? 'repo user',
            'state' => $state,
            'allow_signup' => 'false'
        ];
        
        header('Location: ' . $this->client->oauthAuthorizeUrl($params));
    }

    public function callback(): void
    {
        $uid = $this->requireAuth();
        $code = (string)($_GET['code'] ?? '');
        $state = (string)($_GET['state'] ?? '');
        $expect = (string)($_SESSION['github_oauth_state'] ?? '');
        $_SESSION['github_oauth_state'] = null;
        
        if ($code === '' || $state === '' || $state !== $expect) {
            $this->json(['error' => 'invalid_oauth'], 400);
            return;
        }
        
        $cfg = $this->config();
        $redirect = $this->absoluteUrl('/github/callback', true);
        
        try {
            // Échanger le code contre un token
            $tokenRes = $this->client->oauthAccessToken([
                'client_id' => $cfg['client_id'],
                'client_secret' => $cfg['client_secret'],
                'code' => $code,
                'redirect_uri' => $redirect,
                'state' => $state
            ]);
            
            $accessToken = $tokenRes['access_token'];
            
            // Récupérer les infos utilisateur
            $userRes = $this->client->getUser($accessToken);
            $login = $userRes['login'] ?? '';
            
            // Sauvegarder en BDD
            $enc = $this->encryptToken($accessToken);
            $pdo = Database::connection();
            $stmt = $pdo->prepare('UPDATE users SET github_token = ?, github_username = ? WHERE id = ?');
            $stmt->execute([$enc, $login, $uid]);
            $this->logUserAction('github_connected', ['github_username' => $login]);
            
            $this->redirectWithMessage('/profile', 'Connexion GitHub réussie', 'success');
            
        } catch (GitHubAuthException $e) {
            error_log('GitHub OAuth failed: ' . $e->getMessage());
            $this->json(['error' => 'oauth_exchange_failed', 'message' => $e->getMessage()], 500);
        } catch (GitHubException $e) {
            error_log('GitHub API error: ' . $e->getMessage());
            $this->json(['error' => 'api_failed', 'message' => $e->getMessage()], 502);
        }
    }

    private function config(): array
    {
        $file = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'github.php';
        $cfg = file_exists($file) ? (array)require $file : [];
        $cid = getenv('GITHUB_CLIENT_ID') ?: ($cfg['client_id'] ?? '');
        $sec = getenv('GITHUB_CLIENT_SECRET') ?: ($cfg['client_secret'] ?? '');
        $scope = $cfg['scope'] ?? 'repo delete_repo user';
        return ['client_id' => $cid, 'client_secret' => $sec, 'scope' => $scope];
    }

    public function getCommits(): void
    {
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $sid = (int)($_GET['user_id'] ?? 0);
        $rid = (int)($_GET['repo_id'] ?? 0);
        
        if ($sid <= 0 || $rid <= 0) {
            $this->json(['error' => 'invalid'], 400);
            return;
        }
        
        $student = User::findById($sid);
        if (!$student) {
            $this->json(['error' => 'not_found'], 404);
            return;
        }
        
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM repositories WHERE id = ? AND user_id = ?');
        $stmt->execute([$rid, $sid]);
        $repo = $stmt->fetch();
        
        if (!$repo) {
            $this->json(['error' => 'not_found'], 404);
            return;
        }
        
        $token = $this->decryptToken((string)($student['github_token'] ?? ''));
        if (!$token) {
            $this->json(['error' => 'not_connected'], 400);
            return;
        }
        
        $ownerRepo = $this->parseOwnerRepo(
            (string)($repo['github_url'] ?? ''),
            (string)($student['github_username'] ?? ''),
            (string)($repo['name'] ?? '')
        );
        
        if (!$ownerRepo) {
            $this->json(['error' => 'repo_url_invalid'], 400);
            return;
        }
        
        try {
            // Récupérer les commits
            $commits = $this->client->listCommits($token, $ownerRepo['owner'], $ownerRepo['repo'], 20);
            
            // Formater les commits
            $list = [];
            foreach ($commits as $c) {
                $list[] = [
                    'sha' => (string)($c['sha'] ?? ''),
                    'message' => (string)($c['commit']['message'] ?? ''),
                    'date' => (string)($c['commit']['author']['date'] ?? ''),
                    'html_url' => (string)($c['html_url'] ?? '')
                ];
            }
            
            // Sauvegarder les nouveaux commits
            $this->saveNewCommits($pdo, $list, $sid, $rid, $student);
            $this->logUserAction('repo_commits_fetch', ['user_id' => (int)$sid, 'repo_id' => (int)$rid, 'count' => (int)count($list)]);
            $this->json(['commits' => $list]);
            
        } catch (GitHubNotFoundException $e) {
            $this->json(['error' => 'repo_not_found', 'message' => $e->getMessage()], 404);
        } catch (GitHubAuthException $e) {
            $this->json(['error' => 'auth_failed', 'message' => $e->getMessage()], 401);
        } catch (GitHubRateLimitException $e) {
            $limit = $this->client->getRateLimit();
            $this->json([
                'error' => 'rate_limit',
                'message' => $e->getMessage(),
                'reset_at' => date('Y-m-d H:i:s', $limit['reset'])
            ], 429);
        } catch (GitHubException $e) {
            error_log('GitHub API error: ' . $e->getMessage());
            $this->json(['error' => 'api_failed', 'message' => $e->getMessage()], 502);
        }
    }

    public function scanCommits(): void
    {
        $uid = $this->requireAuth();
        $this->requireRole('formateur');
        $now = time();
        $last = (int)($_SESSION['scan_commits_last'] ?? 0);
        $minInterval = 60;
        
        if ($now - $last < $minInterval) {
            $this->json([
                'success' => true,
                'skipped' => true,
                'next_in' => $minInterval - ($now - $last)
            ]);
            return;
        }
        
        $_SESSION['scan_commits_last'] = $now;
        
        $pdo = Database::connection();
        $students = User::studentsWithGitHub();
        $count = count($students);
        
        if ($count === 0) {
            $this->json(['success' => true, 'created' => 0, 'processed' => 0]);
            return;
        }
        
        $limit = max(1, (int)($_GET['limit'] ?? 1));
        $idx = (int)($_SESSION['scan_commits_index'] ?? 0);
        if ($idx >= $count) {
            $idx = 0;
        }
        
        $processed = 0;
        $created = 0;
        $errors = [];
        
        for ($i = 0; $i < $limit && $i < $count; $i++) {
            $s = $students[($idx + $i) % $count];
            $sid = (int)($s['id'] ?? 0);
            $tokEnc = (string)($s['github_token'] ?? '');
            $token = $this->decryptToken($tokEnc);
            
            if (!$token) {
                continue;
            }
            
            $rid = (int)(User::getActiveRepoId($sid) ?? 0);
            if ($rid <= 0) {
                continue;
            }
            
            $repo = \App\Models\Repository::findByIdForUser($rid, $sid);
            if (!$repo) {
                continue;
            }
            
            $ownerRepo = $this->parseOwnerRepo(
                (string)($repo['github_url'] ?? ''),
                (string)($s['github_username'] ?? ''),
                (string)($repo['name'] ?? '')
            );
            
            if (!$ownerRepo) {
                continue;
            }
            
            try {
                // Récupérer les 3 derniers commits
                $commits = $this->client->listCommits($token, $ownerRepo['owner'], $ownerRepo['repo'], 3);
                
                $list = [];
                foreach ($commits as $c) {
                    $list[] = [
                        'sha' => (string)($c['sha'] ?? ''),
                        'message' => (string)($c['commit']['message'] ?? ''),
                        'date' => (string)($c['commit']['author']['date'] ?? ''),
                        'html_url' => (string)($c['html_url'] ?? '')
                    ];
                }
                
                // Sauvegarder les nouveaux commits
                $newCount = $this->saveNewCommits($pdo, $list, $sid, $rid, $s);
                $created += $newCount;
                $processed++;
                
            } catch (GitHubRateLimitException $e) {
                $errors[] = "Rate limit atteint pour {$s['name']}";
                break; // Arrêter le scan si rate limit
            } catch (GitHubException $e) {
                $errors[] = "Erreur pour {$s['name']}: " . $e->getMessage();
                $processed++;
                continue;
            }
        }
        
        $_SESSION['scan_commits_index'] = ($idx + $limit) % $count;
        
        $response = [
            'success' => true,
            'created' => $created,
            'processed' => $processed
        ];
        
        if (!empty($errors)) {
            $response['errors'] = $errors;
        }
        
        // Avertir si on approche du rate limit
        if ($this->client->isApproachingRateLimit(20)) {
            $limit = $this->client->getRateLimit();
            $response['warning'] = "Rate limit bientôt atteint: {$limit['remaining']}/{$limit['limit']} restantes";
        }
        
        $this->json($response);
    }

    public function createRepository(): void
    {
        $this->requirePost();
        $uid = $this->requireAuth();
        $tok = (string)($_POST['csrf_token'] ?? '');
        if (!$this->validateCsrfToken($tok)) { $this->json(['error' => 'CSRF'], 400); }
        $user = User::findById($uid);
        
        if (!$user) {
            $this->json(['error' => 'forbidden'], 403);
            return;
        }
        
        $name = trim((string)($_POST['name'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        
        if ($name === '') {
            $this->json(['error' => 'name_required'], 400);
            return;
        }
        
        $tokenEnc = (string)($user['github_token'] ?? '');
        $token = $this->decryptToken($tokenEnc);
        
        if (!$token) {
            $this->json(['error' => 'not_connected'], 400);
            return;
        }
        
        try {
            // Créer le repo sur GitHub
            $res = $this->client->createRepo(
                $token,
                $name,
                $description !== '' ? $description : null,
                false,
                true // auto_init
            );
            
            $url = $res['html_url'] ?? '';
            $createdIso = $res['created_at'] ?? '';
            $createdDb = $createdIso !== '' ? str_replace(['T', 'Z'], [' ', ''], $createdIso) : null;
            
            // Sauvegarder en BDD
            $id = \App\Models\Repository::create($uid, $name, $description !== '' ? $description : null, $url, $createdDb);
            
            if (!$id) {
                $this->json(['error' => 'db_failed'], 500);
                return;
            }
            
            // Créer kanban.log
            try {
                $this->createInitialLog($token, $user, $name, $url, $uid, $id);
            } catch (\Throwable $e) {
                error_log('Failed to create kanban.log: ' . $e->getMessage());
            }
            $this->logUserAction('repo_create', ['repo_id' => (int)$id, 'name' => $name, 'github_url' => $url]);
            $this->json([
                'success' => true,
                'repo' => [
                    'id' => $id,
                    'name' => $name,
                    'github_url' => $url
                ]
            ]);
            
        } catch (GitHubAuthException $e) {
            $this->json(['error' => 'auth_failed', 'message' => $e->getMessage()], 401);
        } catch (GitHubRateLimitException $e) {
            $limit = $this->client->getRateLimit();
            $this->json([
                'error' => 'rate_limit',
                'reset_at' => date('Y-m-d H:i:s', $limit['reset'])
            ], 429);
        } catch (GitHubException $e) {
            error_log('GitHub API error: ' . $e->getMessage());
            $this->json(['error' => 'api_failed', 'message' => $e->getMessage()], 502);
        }
    }

    public function deleteRepository(): void
    {
        $this->requirePost();
        $uid = $this->requireAuth();
        $tok = (string)($_POST['csrf_token'] ?? '');
        if (!$this->validateCsrfToken($tok)) { $this->json(['error' => 'CSRF'], 400); }
        $user = User::findById($uid);
        
        if (!$user) {
            $this->json(['error' => 'forbidden'], 403);
            return;
        }
        
        $rid = (int)($_POST['id'] ?? 0);
        $repo = \App\Models\Repository::findByIdForUser($rid, $uid);
        
        if (!$repo) {
            $this->json(['error' => 'not_found'], 404);
            return;
        }
        
        $tokenEnc = (string)($user['github_token'] ?? '');
        $token = $this->decryptToken($tokenEnc);
        
        if (!$token) {
            $this->json(['error' => 'not_connected'], 400);
            return;
        }
        
        $ownerRepo = $this->parseOwnerRepo(
            (string)($repo['github_url'] ?? ''),
            (string)($user['github_username'] ?? ''),
            (string)($repo['name'] ?? '')
        );
        
        if (!$ownerRepo) {
            $this->json(['error' => 'repo_url_invalid'], 400);
            return;
        }
        
        try {
            // Supprimer sur GitHub
            $this->client->deleteRepo($token, $ownerRepo['owner'], $ownerRepo['repo']);
            
            // Supprimer en BDD
            \App\Models\Repository::delete($rid, $uid);
            $this->logUserAction('repo_delete', ['repo_id' => (int)$rid, 'name' => (string)($repo['name'] ?? ''), 'github_url' => (string)($repo['github_url'] ?? '')]);
            $this->json(['success' => true]);
            
        } catch (GitHubNotFoundException $e) {
            // Repo déjà supprimé sur GitHub, supprimer en BDD
            \App\Models\Repository::delete($rid, $uid);
            $this->logUserAction('repo_delete', ['repo_id' => (int)$rid, 'name' => (string)($repo['name'] ?? ''), 'github_url' => (string)($repo['github_url'] ?? ''), 'warning' => 'not_found_remote']);
            $this->json(['success' => true, 'warning' => 'not_found_remote']);
        } catch (GitHubAuthException $e) {
            $this->json(['error' => 'auth_failed', 'message' => $e->getMessage()], 401);
        } catch (GitHubException $e) {
            error_log('GitHub delete error: ' . $e->getMessage());
            $this->json(['error' => 'api_failed', 'message' => $e->getMessage()], 502);
        }
    }

    public function syncRepositories(): void
    {
        $this->requirePost();
        $uid = $this->requireAuth();
        $tok = (string)($_POST['csrf_token'] ?? '');
        if (!$this->validateCsrfToken($tok)) { $this->json(['error' => 'CSRF'], 400); }
        $user = User::findById($uid);
        
        if (!$user) {
            $this->json(['error' => 'forbidden'], 403);
            return;
        }
        
        $tokenEnc = (string)($user['github_token'] ?? '');
        $token = $this->decryptToken($tokenEnc);
        
        if (!$token) {
            $this->json(['error' => 'not_connected'], 400);
            return;
        }
        
        try {
            $repos = $this->client->listUserRepos($token, 100);
            
            $created = 0;
            $updated = 0;
            $skipped = 0;
            
            foreach ($repos as $rp) {
                $name = (string)($rp['name'] ?? '');
                $url = (string)($rp['html_url'] ?? '');
                $desc = (string)($rp['description'] ?? '');
                
                if ($name === '' || $url === '') {
                    $skipped++;
                    continue;
                }
                
                $createdIso = (string)($rp['created_at'] ?? '');
                $createdDb = $createdIso !== '' ? str_replace(['T', 'Z'], [' ', ''], $createdIso) : null;
                
                if (\App\Models\Repository::existsForUserByName($uid, $name)) {
                    $ok = \App\Models\Repository::updateByNameForUser($uid, $name, $desc !== '' ? $desc : null, $url, $createdDb);
                    if ($ok) {
                        $updated++;
                    } else {
                        $skipped++;
                    }
                    continue;
                }
                
                $id = \App\Models\Repository::create($uid, $name, $desc !== '' ? $desc : null, $url, $createdDb);
                if ($id) {
                    $created++;
                } else {
                    $skipped++;
                }
            }
            
            $this->logUserAction('repos_sync', ['created' => (int)$created, 'updated' => (int)$updated, 'skipped' => (int)$skipped]);
            $this->json([
                'success' => true,
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped
            ]);
            
        } catch (GitHubAuthException $e) {
            $this->json(['error' => 'auth_failed', 'message' => $e->getMessage()], 401);
        } catch (GitHubRateLimitException $e) {
            $limit = $this->client->getRateLimit();
            $this->json([
                'error' => 'rate_limit',
                'reset_at' => date('Y-m-d H:i:s', $limit['reset'])
            ], 429);
        } catch (GitHubException $e) {
            error_log('GitHub sync error: ' . $e->getMessage());
            $this->json(['error' => 'api_failed', 'message' => $e->getMessage()], 502);
        }
    }

    public function getReposInfo(): void
    {
        $uid = $this->requireAuth();
        $user = User::findById($uid);
        
        if (!$user) {
            $this->json(['error' => 'forbidden'], 403);
            return;
        }
        
        $tokenEnc = (string)($user['github_token'] ?? '');
        $token = $this->decryptToken($tokenEnc);
        
        if (!$token) {
            $this->json(['error' => 'not_connected'], 400);
            return;
        }
        
        try {
            $repos = $this->client->listUserRepos($token, 100);
            
            $pdo = Database::connection();
            $stmt = $pdo->prepare('SELECT id, name FROM repositories WHERE user_id = ?');
            $stmt->execute([$uid]);
            $local = $stmt->fetchAll();
            
            $map = [];
            foreach ($local as $lr) {
                $map[strtolower((string)$lr['name'])] = (int)$lr['id'];
            }
            
            $list = [];
            foreach ($repos as $rp) {
                $name = strtolower((string)($rp['name'] ?? ''));
                if ($name === '' || !isset($map[$name])) {
                    continue;
                }
                
                $id = $map[$name];
                $size = (int)($rp['size'] ?? 0);
                $createdIso = (string)($rp['created_at'] ?? '');
                $createdDb = $createdIso !== '' ? str_replace(['T', 'Z'], [' ', ''], $createdIso) : null;
                $lang = (string)($rp['language'] ?? '');
                $stars = (int)($rp['stargazers_count'] ?? 0);
                $forks = (int)($rp['forks_count'] ?? 0);
                
                $list[] = [
                    'id' => $id,
                    'size_kb' => $size,
                    'created_at' => $createdDb,
                    'language' => $lang,
                    'stars' => $stars,
                    'forks' => $forks
                ];
            }
            
            $this->json(['repos' => $list]);
            
        } catch (GitHubAuthException $e) {
            $this->json(['error' => 'auth_failed', 'message' => $e->getMessage()], 401);
        } catch (GitHubException $e) {
            error_log('GitHub API error: ' . $e->getMessage());
            $this->json(['error' => 'api_failed', 'message' => $e->getMessage()], 502);
        }
    }

    // ============================================
    // MÉTHODES PRIVÉES
    // ============================================

    // Sauvegarder les nouveaux commits en BDD et créer des notifications
    private function saveNewCommits(\PDO $pdo, array $list, int $userId, int $repoId, array $user): int
    {
        if (empty($list)) {
            return 0;
        }
        
        $shas = array_filter(array_column($list, 'sha'));
        if (empty($shas)) {
            return 0;
        }
        
        // Vérifier les commits existants
        $placeholders = implode(',', array_fill(0, count($shas), '?'));
        $stmt = $pdo->prepare('SELECT sha FROM commits WHERE user_id = ? AND repo_id = ? AND sha IN (' . $placeholders . ')');
        $params = array_merge([$userId, $repoId], $shas);
        $stmt->execute($params);
        
        $existing = [];
        foreach ($stmt->fetchAll() as $r) {
            $existing[(string)($r['sha'] ?? '')] = true;
        }
        
        // Filtrer les nouveaux commits
        $new = [];
        foreach ($list as $row) {
            $sha = (string)($row['sha'] ?? '');
            if ($sha !== '' && !isset($existing[$sha])) {
                $new[] = $row;
            }
        }
        
        if (empty($new)) {
            return 0;
        }
        
        // Insérer les nouveaux commits
        $ins = $pdo->prepare('INSERT INTO commits (message, sha, user_id, repo_id, task_id, created_at) VALUES (?, ?, ?, ?, NULL, ?)');
        foreach ($new as $row) {
            $dt = (string)($row['date'] ?? '');
            $dbdt = $dt !== '' ? str_replace(['T', 'Z'], [' ', ''], $dt) : date('Y-m-d H:i:s');
            $ins->execute([
                (string)($row['message'] ?? ''),
                (string)($row['sha'] ?? ''),
                $userId,
                $repoId,
                $dbdt
            ]);
        }
        
        // Créer des notifications pour les formateurs
        $cnt = count($new);
        $trainers = User::allFormateurs();
        $stuName = (string)($user['name'] ?? 'Étudiant');
        $msg = $stuName . ' a fait ' . $cnt . ' ' . ($cnt > 1 ? 'nouveaux commits' : 'nouveau commit');
        $data = [
            'student_id' => $userId,
            'repo_id' => $repoId,
            'commit_count' => $cnt,
            'commits' => array_column($new, 'sha')
        ];
        
        foreach ($trainers as $tr) {
            $tid = (int)($tr['id'] ?? 0);
            if ($tid > 0) {
                Notification::create($tid, $msg, $data, 'commit');
            }
        }
        
        return $cnt;
    }

    // Créer le fichier kanban.log initial pour le suivi des commits
    private function createInitialLog(string $token, array $user, string $repoName, string $repoUrl, int $userId, int $repoId): void
    {
        $ownerRepo = $this->parseOwnerRepo($repoUrl, (string)($user['github_username'] ?? ''), $repoName);
        if (!$ownerRepo) {
            return;
        }
        
        $stuName = (string)($user['name'] ?? 'Étudiant');
        $line = '[' . date('Y-m-d H:i:s') . '] Repository créé par ' . $stuName;
        
        $res = $this->client->putContent(
            $token,
            $ownerRepo['owner'],
            $ownerRepo['repo'],
            'kanban.log',
            'Repository ' . $repoName . ' créé par ' . $stuName,
            base64_encode($line . "\n")
        );
        
        if (!empty($res['commit']['sha'])) {
            $sha = (string)$res['commit']['sha'];
            $pdo = Database::connection();
            $stmt = $pdo->prepare('INSERT INTO commits (message, sha, user_id, repo_id, task_id, created_at) VALUES (?, ?, ?, ?, NULL, NOW())');
            $stmt->execute(['Repository ' . $repoName . ' créé par ' . $stuName, $sha, $userId, $repoId]);
            
            // Notifier les formateurs
            $trainers = User::allFormateurs();
            foreach ($trainers as $tr) {
                $tid = (int)($tr['id'] ?? 0);
                if ($tid > 0) {
                    Notification::create($tid, 'Repository ' . $repoName . ' créé par ' . $stuName, [
                        'student_id' => $userId,
                        'repo_id' => $repoId,
                        'sha' => $sha
                    ], 'commit');
                }
            }
        }
    }
}