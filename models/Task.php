<?php
declare(strict_types=1);

namespace App\Models;

class Task
{
    public static function findById(int $id): ?array
    {
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare('SELECT id, title, description, status, priority, user_id, repo_id, labels, created_at, updated_at FROM tasks WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            return $row ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }
    public static function create(string $title, string $description, string $priority, ?int $userId, ?int $repoId, ?string $labelsJson): ?int
    {
        $allowed = ['low','medium','high'];
        if (!in_array($priority, $allowed, true)) {
            $priority = 'medium';
        }
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO tasks (title, description, status, priority, user_id, repo_id, labels, created_at) VALUES (?, ?, "todo", ?, ?, ?, ?, NOW())');
        $ok = $stmt->execute([$title, $description, $priority, $userId, $repoId, $labelsJson]);
        if (!$ok) return null;
        return (int)$pdo->lastInsertId();
    }
    public static function all(): array
    {
        try {
            $pdo = Database::connection();
            $sql = 'SELECT id, title, description, status, priority, user_id, repo_id, labels FROM tasks ORDER BY id ASC';
            $stmt = $pdo->query($sql);
            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function byStatusForUser(string $status, int $userId): array
    {
        try {
            $pdo = Database::connection();
            $sql = 'SELECT id, title, description, status, priority, user_id, repo_id, labels FROM tasks WHERE status = ? AND user_id = ? ORDER BY id ASC';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$status, $userId]);
            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function countByUser(int $userId): int
    {
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare('SELECT COUNT(*) c FROM tasks WHERE user_id = ?');
            $stmt->execute([$userId]);
            $row = $stmt->fetch();
            return (int)($row['c'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public static function move(int $id, string $newStatus): bool
    {
        try {
            $pdo = Database::connection();
            $sql = 'UPDATE tasks SET status = ?, updated_at = NOW() WHERE id = ?';
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([$newStatus, $id]);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function updateForUser(int $id, int $userId, string $title, string $description, string $priority, ?string $labelsJson): bool
    {
        try {
            $allowed = ['low','medium','high'];
            if (!in_array($priority, $allowed, true)) {
                $priority = 'medium';
            }
            $pdo = Database::connection();
            $sql = 'UPDATE tasks SET title = ?, description = ?, priority = ?, labels = ?, updated_at = NOW() WHERE id = ? AND user_id = ?';
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([$title, $description, $priority, $labelsJson, $id, $userId]);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function deleteByUser(int $id, int $userId): bool
    {
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare('DELETE FROM tasks WHERE id = ? AND user_id = ?');
            return $stmt->execute([$id, $userId]);
        } catch (\Throwable $e) {
            return false;
        }
    }
}