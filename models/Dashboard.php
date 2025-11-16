<?php
declare(strict_types=1);

namespace App\Models;

class Dashboard
{
    public static function getStats(): array
    {
        $pdo = Database::connection();
        $commits = $pdo->query('SELECT COUNT(*) c FROM commits')->fetch()['c'] ?? 0;
        $students = $pdo->query("SELECT COUNT(*) c FROM users WHERE role = 'student'")->fetch()['c'] ?? 0;
        $tasksDone = $pdo->query("SELECT COUNT(*) c FROM tasks WHERE status = 'done'")->fetch()['c'] ?? 0;
        $repos = $pdo->query('SELECT COUNT(*) c FROM repositories')->fetch()['c'] ?? 0;
        return [
            'commits' => (int)$commits,
            'students' => (int)$students,
            'tasksDone' => (int)$tasksDone,
            'repos' => (int)$repos,
        ];
    }

    public static function getUserLogs(string $q = '', string $period = '7d', int $limit = 200, int $page = 1): array
    {
        $svc = new \App\Services\UserLogService();
        return $svc->getUserLogs($q, $period, $limit, $page);
    }

    public static function getUserLogsStats(string $q = '', string $period = '7d'): array
    {
        $svc = new \App\Services\UserLogService();
        return $svc->getUserLogsStats($q, $period);
    }
}