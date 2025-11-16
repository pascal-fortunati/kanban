<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use App\Models\Dashboard;

class BaseController
{
    // ============================================
    // RENDU DE VUES
    // ============================================

    // Rendre une vue avec layout
    protected function render(string $view, array $params = []): void
    {
        $baseDir = dirname(__DIR__);
        $viewFile = $baseDir . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . $view . '.php';
        $layout = $baseDir . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'layout.php';

        if (!file_exists($viewFile)) {
            http_response_code(404);
            error_log("View not found: {$viewFile}");
            echo 'View not found';
            return;
        }

        // Préparer les données globales de la vue
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $user = $uid > 0 ? User::findById($uid) : null;
        
        $baseUrl = $this->baseUrl();
        $currentPath = $this->getCurrentPath();

        $params['baseUrl'] = $baseUrl;
        $params['currentPath'] = $currentPath;
        $params['user'] = $user;
        $params['isAuthenticated'] = $uid > 0;
        $params['csrfToken'] = $this->generateCsrfToken();
        $params['flash'] = $this->getFlashMessage();

        // Générer le contenu de la vue
        $content = $this->renderView($viewFile, $params);

        // Charger le layout avec le contenu
        $this->renderLayout($layout, $content, $params);
    }

    // Rendre une vue sans layout (partiel)
    protected function renderPartial(string $view, array $params = []): string
    {
        $baseDir = dirname(__DIR__);
        $viewFile = $baseDir . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . $view . '.php';

        if (!file_exists($viewFile)) {
            error_log("Partial view not found: {$viewFile}");
            return '';
        }

        return $this->renderView($viewFile, $params);
    }

    // Rendre un fichier de vue
    private function renderView(string $viewFile, array $params): string
    {
        extract($params, EXTR_SKIP);
        
        ob_start();
        try {
            require $viewFile;
            return ob_get_clean() ?: '';
        } catch (\Throwable $e) {
            ob_end_clean();
            error_log("Error rendering view: {$e->getMessage()}");
            return '';
        }
    }

    // Rendre le layout
    private function renderLayout(string $layoutFile, string $content, array $params): void
    {
        if (!file_exists($layoutFile)) {
            echo $content; // Fallback sans layout
            return;
        }

        extract($params, EXTR_SKIP);
        require $layoutFile;
    }

    // ============================================
    // RÉPONSES JSON
    // ============================================

    // Envoyer une réponse JSON
    protected function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        
        // Ajouter des headers de sécurité
        $this->addSecurityHeaders();
        
        echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Envoyer une réponse JSON de succès
    protected function jsonSuccess(array $data = [], string $message = ''): void
    {
        $response = ['success' => true];
        
        if ($message !== '') {
            $response['message'] = $message;
        }
        
        $response = array_merge($response, $data);
        $this->json($response);
    }

    // Envoyer une réponse JSON d'erreur
    protected function jsonError(string $error, int $status = 400, array $extra = []): void
    {
        $response = array_merge(['error' => $error], $extra);
        $this->json($response, $status);
    }

    // Ajouter des headers de sécurité
    private function addSecurityHeaders(): void
    {
        if (!headers_sent()) {
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: DENY');
            header('X-XSS-Protection: 1; mode=block');
        }
    }

    // ============================================
    // NAVIGATION & URLS
    // ============================================

    // Obtenir l'URL de base de l'application
    protected function baseUrl(): string
    {
        static $baseUrl = null;
        
        if ($baseUrl === null) {
            $script = $_SERVER['SCRIPT_NAME'] ?? '';
            $dir = rtrim(str_replace('index.php', '', $script), '/');
            $baseUrl = preg_replace('#/public$#', '', $dir);
        }
        
        return $baseUrl;
    }

    // Obtenir le path actuel (sans base URL)
    protected function getCurrentPath(): string
    {
        $reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '/';
        $baseUrl = $this->baseUrl();
        
        if ($baseUrl && strpos($reqPath, $baseUrl) === 0) {
            $currentPath = substr($reqPath, strlen($baseUrl));
            return $currentPath === '' ? '/' : $currentPath;
        }
        
        return $reqPath;
    }

    // Rediriger vers un path
    protected function redirect(string $path, int $statusCode = 302): void
    {
        $base = $this->baseUrl();
        $url = $base . $path;
        
        http_response_code($statusCode);
        header('Location: ' . $url);
        exit;
    }

    // Rediriger vers une URL externe
    protected function redirectExternal(string $url, int $statusCode = 302): void
    {
        http_response_code($statusCode);
        header('Location: ' . $url);
        exit;
    }
        
    // Rediriger avec un message flash
    protected function redirectWithMessage(string $path, string $message, string $type = 'success'): void
    {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
        $this->redirect($path);
    }

    // Récupérer et supprimer le message flash
    protected function getFlashMessage(): ?array
    {
        if (!isset($_SESSION['flash_message'])) {
            return null;
        }
        
        $message = [
            'message' => $_SESSION['flash_message'],
            'type' => $_SESSION['flash_type'] ?? 'info'
        ];
        
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        
        return $message;
    }

    // Obtenir l'origine (protocole + domaine)
    protected function origin(bool $forceHttps = false): string
    {
        static $origin = null;
        
        if ($origin === null) {
            $envForce = getenv('FORCE_HTTPS');
            $shouldForce = $forceHttps || ($envForce === '1' || strtolower((string)$envForce) === 'true');
            
            $https = $this->isHttps();
            $scheme = ($shouldForce || $https) ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            
            $origin = $scheme . '://' . $host;
        }
        
        return $origin;
    }

    // Vérifier si la connexion est en HTTPS
    protected function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            || (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on')
            || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    }

    // Générer une URL absolue
    protected function absoluteUrl(string $path, bool $forceHttps = false): string
    {
        $cleanPath = '/' . ltrim($path, '/');
        return $this->origin($forceHttps) . $this->baseUrl() . $cleanPath;
    }

    // ============================================
    // VALIDATION & SÉCURITÉ
    // ============================================

    // Valider un token CSRF
    protected function validateCsrfToken(string $token): bool
    {
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        
        if ($sessionToken === '' || $token === '') {
            return false;
        }
        
        return hash_equals($sessionToken, $token);
    }

    // Générer un token CSRF
    protected function generateCsrfToken(): string
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }

    // Vérifier que la requête est en POST
    protected function requirePost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonError('Method not allowed', 405);
        }
    }

    // Vérifier que l'utilisateur est authentifié
    protected function requireAuth(): int
    {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        
        if ($userId <= 0) {
            $this->jsonError('Authentication required', 401);
        }
        
        return $userId;
    }

    // Vérifier que l'utilisateur a un rôle spécifique
    protected function requireRole(string $role): void
    {
        $userId = $this->requireAuth();
        $user = User::findById($userId);
        
        if (!$user || ($user['role'] ?? '') !== $role) {
            $this->jsonError('Forbidden', 403);
        }
    }

    // Nettoyer une chaîne pour éviter XSS
    protected function sanitize(string $input): string
    {
        return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    // Valider un email
    protected function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    // ============================================
    // CHIFFREMENT (vos méthodes existantes)
    // ============================================

    // Déchiffrer un token
    protected function decryptToken(string $enc): ?string
    {
        $key = getenv('APP_KEY') ?: '';
        
        if ($enc === '') {
            return null;
        }
        
        if ($key === '') {
            return $enc;
        }
        
        $raw = base64_decode($enc, true);
        if ($raw === false) {
            return $enc;
        }
        
        $parts = explode('::', $raw);
        if (count($parts) !== 2) {
            return $enc;
        }
        
        [$iv, $cipher] = $parts;
        $ek = substr(hash('sha256', $key, true), 0, 32);
        $plain = openssl_decrypt($cipher, 'AES-256-CBC', $ek, 0, $iv);
        
        return $plain !== false ? $plain : null;
    }

    // Chiffrer un token
    protected function encryptToken(string $token): string
    {
        $key = getenv('APP_KEY') ?: '';
        
        if ($key === '') {
            return $token;
        }
        
        try {
            $iv = random_bytes(16);
            $ek = substr(hash('sha256', $key, true), 0, 32);
            $cipher = openssl_encrypt($token, 'AES-256-CBC', $ek, 0, $iv);
            
            if ($cipher === false) {
                return $token;
            }
            
            return base64_encode($iv . '::' . $cipher);
        } catch (\Exception $e) {
            error_log("Encryption error: {$e->getMessage()}");
            return $token;
        }
    }

    // ============================================
    // UTILITAIRES GITHUB
    // ============================================

    // Parser une URL GitHub pour extraire owner et repo
    protected function parseOwnerRepo(string $url, string $ownerFallback = '', string $nameFallback = ''): ?array
    {
        $url = trim($url);
        
        // Essayer de parser l'URL GitHub
        if ($url !== '' && preg_match('#github\.com/([^/]+)/([^/]+)#i', $url, $m)) {
            return [
                'owner' => $m[1],
                'repo' => preg_replace('/\.git$/', '', $m[2])
            ];
        }
        
        // Fallback sur les valeurs fournies
        if ($ownerFallback !== '' && $nameFallback !== '') {
            return [
                'owner' => $ownerFallback,
                'repo' => $nameFallback
            ];
        }
        
        return null;
    }

    // ============================================
    // HELPERS
    // ============================================

    // Vérifier si la requête est AJAX
    protected function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) 
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    // Obtenir l'IP du client
    protected function getClientIp(): string
    {
        $h = $_SERVER;
        $cf = $h['HTTP_CF_CONNECTING_IP'] ?? '';
        if ($cf !== '' && filter_var($cf, FILTER_VALIDATE_IP)) {
            return $cf;
        }
        $tci = $h['HTTP_TRUE_CLIENT_IP'] ?? '';
        if ($tci !== '' && filter_var($tci, FILTER_VALIDATE_IP)) {
            return $tci;
        }
        $xff = $h['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($xff !== '') {
            $parts = array_map('trim', explode(',', $xff));
            foreach ($parts as $p) {
                if (filter_var($p, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $p;
                }
            }
            foreach ($parts as $p) {
                if (filter_var($p, FILTER_VALIDATE_IP)) {
                    return $p;
                }
            }
        }
        $xri = $h['HTTP_X_REAL_IP'] ?? '';
        if ($xri !== '' && filter_var($xri, FILTER_VALIDATE_IP)) {
            return $xri;
        }
        $ra = $h['REMOTE_ADDR'] ?? '';
        return $ra !== '' ? $ra : '0.0.0.0';
    }

    // Logger une action utilisateur
    protected function logUserAction(string $action, array $data = []): void
    {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $ip = $this->getClientIp();
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $timestamp = date('Y-m-d H:i:s');

        $h = $_SERVER;
        $cf = $h['HTTP_CF_CONNECTING_IP'] ?? '';
        $tci = $h['HTTP_TRUE_CLIENT_IP'] ?? '';
        $xff = $h['HTTP_X_FORWARDED_FOR'] ?? '';
        $xri = $h['HTTP_X_REAL_IP'] ?? '';
        $ra = $h['REMOTE_ADDR'] ?? '';
        $payload = $data;
        $payload['cf_ip'] = $cf;
        $payload['true_client_ip'] = $tci;
        $payload['x_forwarded_for'] = $xff;
        $payload['x_real_ip'] = $xri;
        $payload['remote_addr'] = $ra;

        $message = $this->formatActionMessage($action, $payload);

        $logData = [
            'timestamp' => $timestamp,
            'user_id' => $userId,
            'ip' => $ip,
            'user_agent' => $ua,
            'action' => $action,
            'message' => $message,
            'data' => $payload
        ];

        $file = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'users.log';
        $dir = dirname($file);
        if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
        if (is_file($file) && @filesize($file) > (5 * 1024 * 1024)) { @rename($file, $file . '.' . date('Ymd_His')); }
        @file_put_contents($file, json_encode($logData, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    // Formater un message d'action utilisateur
    private function formatActionMessage(string $action, array $data): string
    {
        switch ($action) {
            case 'login':
                return 'Connexion';
            case 'logout':
                return 'Déconnexion';
            case 'task_move':
                $from = (string)($data['from'] ?? '');
                $to = (string)($data['to'] ?? '');
                return ($from !== '' && $to !== '') ? ('Tâche déplacée de ' . $from . ' à ' . $to) : 'Tâche déplacée';
            case 'task_create':
                $title = (string)($data['title'] ?? '');
                return $title !== '' ? ('Tâche créée: ' . $title) : 'Tâche créée';
            case 'task_update':
                $title = (string)($data['title'] ?? '');
                return $title !== '' ? ('Tâche mise à jour: ' . $title) : 'Tâche mise à jour';
            case 'task_delete':
                return 'Tâche supprimée';
            case 'active_repo_set':
                $name = (string)($data['name'] ?? '');
                return $name !== '' ? ('Repository actif défini: ' . $name) : 'Repository actif défini';
            case 'active_repo_unset':
                return 'Repository actif désactivé';
            case 'repo_create':
                $name = (string)($data['name'] ?? '');
                return $name !== '' ? ('Repository créé: ' . $name) : 'Repository créé';
            case 'repo_delete':
                $name = (string)($data['name'] ?? '');
                return $name !== '' ? ('Repository supprimé: ' . $name) : 'Repository supprimé';
            case 'repos_sync':
                $created = (int)($data['created'] ?? 0);
                $updated = (int)($data['updated'] ?? 0);
                return 'Synchronisation des repositories (' . $created . ' créés, ' . $updated . ' mis à jour)';
            case 'repo_commits_fetch':
                $count = (int)($data['count'] ?? 0);
                return 'Commits récupérés: ' . $count;
            case 'missed_broadcasts_fetch':
                $count = (int)($data['count'] ?? 0);
                return 'Diffusions manquées récupérées: ' . $count;
            case 'github_connected':
                return 'Connexion GitHub';
            default:
                return $action;
        }
    }
}