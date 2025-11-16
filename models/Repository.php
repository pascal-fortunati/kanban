<?php
declare(strict_types=1);

namespace App\Models;

class Repository
{
    public static function byUser(int $userId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM repositories WHERE user_id = ? ORDER BY created_at DESC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function countByUser(int $userId): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT COUNT(*) c FROM repositories WHERE user_id = ?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return (int)($row['c'] ?? 0);
    }

    public static function create(int $userId, string $name, ?string $description, string $githubUrl, ?string $githubCreatedAt = null): ?int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO repositories (name, github_url, description, user_id, created_at) VALUES (?, ?, ?, ?, ?)');
        $ts = $githubCreatedAt ?: date('Y-m-d H:i:s');
        $ok = $stmt->execute([$name, $githubUrl, $description, $userId, $ts]);
        if (!$ok) return null;
        return (int)$pdo->lastInsertId();
    }

    public static function delete(int $id, int $userId): bool
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('DELETE FROM repositories WHERE id = ? AND user_id = ?');
        return $stmt->execute([$id, $userId]);
    }

    public static function findByIdForUser(int $id, int $userId): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM repositories WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function existsForUserByName(int $userId, string $name): bool
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id FROM repositories WHERE user_id = ? AND name = ? LIMIT 1');
        $stmt->execute([$userId, $name]);
        return (bool)$stmt->fetch();
    }

    public static function updateByNameForUser(int $userId, string $name, ?string $description, string $githubUrl, ?string $createdAt = null): bool
    {
        $pdo = Database::connection();
        if ($createdAt !== null && $createdAt !== '') {
            $stmt = $pdo->prepare('UPDATE repositories SET github_url = ?, description = ?, created_at = ? WHERE user_id = ? AND name = ?');
            return $stmt->execute([$githubUrl, $description, $createdAt, $userId, $name]);
        }
        $stmt = $pdo->prepare('UPDATE repositories SET github_url = ?, description = ? WHERE user_id = ? AND name = ?');
        return $stmt->execute([$githubUrl, $description, $userId, $name]);
    }
}