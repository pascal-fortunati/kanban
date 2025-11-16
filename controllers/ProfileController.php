<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use App\Models\Repository;
 

class ProfileController extends BaseController
{
    public function index(): void
    {
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $user = User::findById($uid);
        $repos = Repository::byUser($uid);
        $activeRepoId = (int)(User::getActiveRepoId($uid) ?? ($_SESSION['active_repo_id'] ?? 0));
        if ($activeRepoId > 0 && !empty($repos)) {
            usort($repos, function($a, $b) use ($activeRepoId) {
                $aid = (int)($a['id'] ?? 0);
                $bid = (int)($b['id'] ?? 0);
                if ($aid === $activeRepoId) return -1;
                if ($bid === $activeRepoId) return 1;
                return strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? ''));
            });
        }
        $this->render('profile/index', ['user' => $user, 'repos' => $repos, 'activeRepoId' => $activeRepoId]);
    }

    public function setActiveRepo(): void
    {
        $this->requirePost();
        $uid = $this->requireAuth();
        $tok = (string)($_POST['csrf_token'] ?? '');
        if (!$this->validateCsrfToken($tok)) { $this->json(['error' => 'CSRF'], 400); }
        $rid = (int)($_POST['id'] ?? 0);
        $repo = Repository::findByIdForUser($rid, $uid);
        if (!$repo) { $this->json(['error' => 'not_found'], 404); return; }
        $_SESSION['active_repo_id'] = (int)$repo['id'];
        User::setActiveRepoId($uid, (int)$repo['id']);
        $this->logUserAction('active_repo_set', ['repo_id' => (int)$repo['id'], 'name' => (string)($repo['name'] ?? ''), 'github_url' => (string)($repo['github_url'] ?? '')]);
        $this->json(['success' => true, 'active_repo_id' => (int)$repo['id']]);
    }

    public function deactivateActiveRepo(): void
    {
        $this->requirePost();
        $uid = $this->requireAuth();
        $tok = (string)($_POST['csrf_token'] ?? '');
        if (!$this->validateCsrfToken($tok)) { $this->json(['error' => 'CSRF'], 400); }
        $prev = (int)(User::getActiveRepoId($uid) ?? 0);
        $_SESSION['active_repo_id'] = 0;
        User::setActiveRepoId($uid, null);
        $this->logUserAction('active_repo_unset', ['prev_repo_id' => $prev]);
        $this->json(['success' => true]);
    }    
}