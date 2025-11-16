<?php
declare(strict_types=1);

namespace App\Models;

class Notification
{
    public static function create(int $userId, string $message, array $data = [], string $type = 'event'): bool
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO notifications (user_id, type, message, data, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())');
        return $stmt->execute([$userId, $type, $message, json_encode($data)]);
    }

    public static function updateByIdForUser(int $userId, int $id, array $data, ?string $message = null): bool
    {
        if ($userId <= 0 || $id <= 0) return false;
        $pdo = Database::connection();
        if ($message !== null && $message !== '') {
            $stmt = $pdo->prepare('UPDATE notifications SET message = ?, data = ? WHERE id = ? AND user_id = ?');
            return $stmt->execute([$message, json_encode($data), $id, $userId]);
        }
        $stmt = $pdo->prepare('UPDATE notifications SET data = ? WHERE id = ? AND user_id = ?');
        return $stmt->execute([json_encode($data), $id, $userId]);
    }

    public static function unreadCountForUser(int $userId): int
    {
        if ($userId <= 0) return 0;
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND is_read = 0');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return (int)($row['c'] ?? 0);
    }

    public static function latestForUser(int $userId, int $limit = 10): array
    {
        if ($userId <= 0) return [];
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT ' . (int)$limit);
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function latestOfTypeForUser(int $userId, string $type): ?array
    {
        if ($userId <= 0) return null;
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? AND type = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$userId, $type]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function listOfTypeForUser(int $userId, string $type, int $limit = 10): array
    {
        if ($userId <= 0) return [];
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? AND type = ? ORDER BY id DESC LIMIT ' . (int)$limit);
        $stmt->execute([$userId, $type]);
        return $stmt->fetchAll();
    }

    public static function findByIdForUser(int $userId, int $id): ?array
    {
        if ($userId <= 0 || $id <= 0) return null;
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM notifications WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function deleteByIdForUser(int $userId, int $id): bool
    {
        if ($userId <= 0 || $id <= 0) return false;
        $pdo = Database::connection();
        $stmt = $pdo->prepare('DELETE FROM notifications WHERE id = ? AND user_id = ?');
        return $stmt->execute([$id, $userId]);
    }

    public static function markRead(int $id): bool
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ?');
        return $stmt->execute([$id]);
    }
}