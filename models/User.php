<?php
declare(strict_types=1);

namespace App\Models;

class User
{
    public static function findByEmail(string $email): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(string $name, string $email, string $password): ?int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO users (name, email, password, role, created_at) VALUES (?, ?, ?, "student", NOW())');
        $ok = $stmt->execute([$name, $email, $password]);
        if (!$ok) return null;
        return (int)$pdo->lastInsertId();
    }

    public static function findById(int $id): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function allStudents(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->query('SELECT id, name, email, github_username, github_token, active_repo_id FROM users WHERE role = "student" ORDER BY id ASC');
        return $stmt->fetchAll();
    }

    public static function allFormateurs(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->query('SELECT id, name, email FROM users WHERE role = "formateur" ORDER BY id ASC');
        return $stmt->fetchAll();
    }

    public static function studentsWithGitHub(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->query('SELECT id, name, email, github_username, github_token FROM users WHERE role = "student" AND github_username IS NOT NULL AND github_username <> "" ORDER BY name ASC');
        return $stmt->fetchAll();
    }

    public static function clearGitHubToken(int $id): bool
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE users SET github_token = NULL WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public static function updatePassword(int $id, string $passwordHash): bool
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
        return $stmt->execute([$passwordHash, $id]);
    }

    public static function setActiveRepoId(int $id, ?int $repoId): bool
    {
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare('UPDATE users SET active_repo_id = ? WHERE id = ?');
            return $stmt->execute([$repoId, $id]);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function getActiveRepoId(int $id): ?int
    {
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare('SELECT active_repo_id FROM users WHERE id = ?');
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            $rid = $row['active_repo_id'] ?? null;
            return $rid !== null ? (int)$rid : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}