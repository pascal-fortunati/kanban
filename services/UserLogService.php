<?php
declare(strict_types=1);

namespace App\Services;

class UserLogService
{
    public function getUserLogs(string $q = '', string $period = '7d', int $limit = 200, int $page = 1): array
    {
        $file = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'users.log';
        $now = time();
        $minTs = 0;
        if ($period === '24h') { $minTs = $now - 86400; }
        elseif ($period === '7d') { $minTs = $now - 7 * 86400; }
        elseif ($period === '30d') { $minTs = $now - 30 * 86400; }
        if (!is_readable($file)) { return []; }
        $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) { return []; }
        $res = [];
        $offset = max(0, ($page > 1 ? ($page - 1) * max(1, $limit) : 0));
        $matched = 0;
        $users = [];
        for ($i = count($lines) - 1; $i >= 0 && count($res) < $limit; $i--) {
            $line = trim((string)$lines[$i]);
            if ($line === '') continue;
            $row = json_decode($line, true);
            if (!is_array($row)) continue;
            $ts = strtotime((string)($row['timestamp'] ?? '')) ?: 0;
            if ($minTs > 0 && $ts > 0 && $ts < $minTs) continue;
            $uid = (int)($row['user_id'] ?? 0);
            $info = null;
            if ($uid > 0) {
                if (!array_key_exists($uid, $users)) { $users[$uid] = \App\Models\User::findById($uid) ?: null; }
                $info = $users[$uid];
            }
            $name = $info ? (string)($info['name'] ?? '') : '';
            $email = $info ? (string)($info['email'] ?? '') : '';
            $ip = (string)($row['ip'] ?? '');
            $ua = (string)($row['user_agent'] ?? '');
            $action = (string)($row['action'] ?? '');
            $message = (string)($row['message'] ?? '');
            $data = json_encode((array)($row['data'] ?? []), JSON_UNESCAPED_UNICODE);
            if ($q !== '') {
                $like = (stripos($name, $q) !== false) || (stripos($email, $q) !== false) || (stripos($ip, $q) !== false) || (stripos($action, $q) !== false);
                if (!$like) continue;
            }
            $matched++;
            if ($matched <= $offset) { continue; }
            $res[] = [
                'id' => count($res) + 1,
                'user_id' => $uid,
                'name' => $name,
                'email' => $email,
                'ip' => $ip,
                'user_agent' => $ua,
                'action' => $action,
                'message' => $message,
                'data' => $data,
                'created_at' => ($ts > 0 ? date('Y-m-d H:i:s', $ts) : date('Y-m-d H:i:s')),
            ];
        }
        return $res;
    }

    public function getUserLogsStats(string $q = '', string $period = '7d'): array
    {
        $file = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'users.log';
        $now = time();
        $minTs = 0;
        if ($period === '24h') { $minTs = $now - 86400; }
        elseif ($period === '7d') { $minTs = $now - 7 * 86400; }
        elseif ($period === '30d') { $minTs = $now - 30 * 86400; }
        $lines = is_readable($file) ? @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
        if (!is_array($lines)) { return ['total' => 0, 'students' => 0, 'logins' => 0, 'logouts' => 0, 'actions' => [], 'topIps' => []]; }
        $total = 0; $logins = 0; $logouts = 0;
        $actions = []; $ipCounts = []; $studentIds = [];
        $users = [];
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $line = trim((string)$lines[$i]); if ($line === '') continue;
            $row = json_decode($line, true); if (!is_array($row)) continue;
            $ts = strtotime((string)($row['timestamp'] ?? '')) ?: 0;
            if ($minTs > 0 && $ts > 0 && $ts < $minTs) continue;
            $act = (string)($row['action'] ?? '');
            $name = '';
            $email = '';
            $uid = (int)($row['user_id'] ?? 0);
            if ($uid > 0) {
                if (!array_key_exists($uid, $users)) { $users[$uid] = \App\Models\User::findById($uid) ?: null; }
                $info = $users[$uid];
                $name = $info ? (string)($info['name'] ?? '') : '';
                $email = $info ? (string)($info['email'] ?? '') : '';
                if ($info && (string)($info['role'] ?? '') === 'student') { $studentIds[$uid] = true; }
            }
            if ($q !== '') {
                $ip = (string)($row['ip'] ?? '');
                $like = (stripos($name, $q) !== false) || (stripos($email, $q) !== false) || (stripos($ip, $q) !== false) || (stripos($act, $q) !== false);
                if (!$like) continue;
            }
            $total++;
            $actions[$act] = ($actions[$act] ?? 0) + 1;
            if ($act === 'login') $logins++;
            if ($act === 'logout') $logouts++;
            $ip = (string)($row['ip'] ?? '');
            if ($ip !== '') { $ipCounts[$ip] = ($ipCounts[$ip] ?? 0) + 1; }
        }
        $actList = [];
        foreach ($actions as $k => $v) { $actList[] = ['action' => $this->label($k), 'c' => $v]; }
        usort($actList, function($a, $b){ return ($b['c'] ?? 0) <=> ($a['c'] ?? 0); });
        $ips = [];
        foreach ($ipCounts as $k => $v) { $ips[] = ['ip' => $k, 'c' => $v]; }
        usort($ips, function($a, $b){ return ($b['c'] ?? 0) <=> ($a['c'] ?? 0); });
        if (count($ips) > 5) { $ips = array_slice($ips, 0, 5); }
        return [
            'total' => $total,
            'students' => count($studentIds),
            'logins' => $logins,
            'logouts' => $logouts,
            'actions' => $actList,
            'topIps' => $ips,
        ];
    }

    private function label(string $a): string
    {
        $map = [
            'login' => 'Connexion',
            'logout' => 'Déconnexion',
            'task_move' => 'Déplacement de tâche',
            'task_create' => 'Création de tâche',
            'task_update' => 'Mise à jour de tâche',
            'task_delete' => 'Suppression de tâche',
            'active_repo_set' => 'Activation du repository',
            'active_repo_unset' => 'Désactivation du repository',
            'repo_create' => 'Création de repository',
            'repo_delete' => 'Suppression de repository',
            'repos_sync' => 'Synchronisation des repositories',
            'repo_commits_fetch' => 'Récupération des commits',
            'missed_broadcasts_fetch' => 'Récupération des diffusions manquées',
            'github_connected' => 'Connexion GitHub',
        ];
        return $map[$a] ?? $a;
    }
}