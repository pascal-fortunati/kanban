<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;

class AuthController extends BaseController
{
    // Constantes de configuration
    private const PASSWORD_MIN_LENGTH = 8;
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOGIN_LOCKOUT_TIME = 900; // 15 minutes
    private const SESSION_LIFETIME = 7200; // 2 heures

    // Afficher la page de connexion
    public function login(): void
    {
        // Si déjà connecté, rediriger vers l'accueil utilisateur
        if ($this->isAuthenticated()) {
            $this->redirectToUserHome();
            return;
        }

        $this->render('auth/login');
    }

    // Afficher la page d'inscription
    public function register(): void
    {
        // Si déjà connecté, rediriger vers l'accueil utilisateur
        if ($this->isAuthenticated()) {
            $this->redirectToUserHome();
            return;
        }

        $this->render('auth/register');
    }

    // Traiter la connexion
    public function doLogin(): void
    {
        $this->requirePost();
        $tok = (string)($_POST['csrf_token'] ?? '');
        if (!$this->validateCsrfToken($tok)) { $this->json(['error' => 'CSRF'], 400); }
        // Vérifier si l'IP est verrouillée pour trop de tentatives échouées
        if ($this->isIpLocked()) {
            $this->json([
                'error' => 'Trop de tentatives. Réessayez dans 15 minutes.'
            ], 429);
            return;
        }

        // Valider les entrées de connexion
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        $validation = $this->validateLoginInput($email, $password);
        if ($validation !== true) {
            $this->recordLoginAttempt(false);
            $this->json(['error' => $validation], 400);
            return;
        }

        // Rechercher l'utilisateur par email
        $user = User::findByEmail($email);
        
        if (!$user) {
            $this->recordLoginAttempt(false);
            // Message générique pour éviter l'énumération d'emails
            $this->json(['error' => 'Email ou mot de passe incorrect'], 401);
            return;
        }

        // Vérifier le mot de passe
        if (!password_verify($password, $user['password'])) {
            $this->recordLoginAttempt(false);
            $this->json(['error' => 'Email ou mot de passe incorrect'], 401);
            return;
        }

        // Mettre à jour le mot de passe si nécessaire
        if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
            User::updatePassword((int)$user['id'], password_hash($password, PASSWORD_DEFAULT));
        }

        // Connexion réussie : enregistrer tentative et créer session
        $this->recordLoginAttempt(true);
        $this->createUserSession((int)$user['id'], $user);
        $this->logUserAction('login', ['email' => $email]);

        $role = (string)($user['role'] ?? '');
        $dest = ($role === 'formateur') ? '/dashboard' : '/kanban';

        $this->json([
            'success' => true,
            'redirect' => $this->baseUrl() . $dest
        ]);
    }

    // Traiter l'inscription
    public function doRegister(): void
    {
        $this->requirePost();
        $tok = (string)($_POST['csrf_token'] ?? '');
        if (!$this->validateCsrfToken($tok)) { $this->json(['error' => 'CSRF'], 400); }
        // Valider les entrées
        $name = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['confirm'] ?? '');

        // Validation détaillée
        $validation = $this->validateRegisterInput($name, $email, $password, $confirm);
        if ($validation !== true) {
            $this->json(['error' => $validation], 400);
            return;
        }

        // Vérifier si l'email existe déjà
        if (User::findByEmail($email)) {
            $this->json(['error' => 'Email déjà utilisé'], 409);
            return;
        }

        // Créer l'utilisateur
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $userId = User::create($name, $email, $hashedPassword);

        if (!$userId) {
            error_log("Failed to create user: {$email}");
            $this->json(['error' => 'Inscription impossible. Veuillez réessayer.'], 500);
            return;
        }

        // Créer la session utilisateur
        $this->createUserSession($userId, ['id' => $userId, 'name' => $name, 'email' => $email, 'role' => 'student']);
        $this->json(['success' => true, 'redirect' => $this->baseUrl() . '/kanban']);
    }

    // Déconnexion
    public function logout(): void
    {
        $uid = (int)($_SESSION['user_id'] ?? 0);

        // Nettoyer le token GitHub si nécessaire
        if ($uid > 0) {
            try {
                User::clearGitHubToken($uid);
            } catch (\Throwable $e) {
                error_log('Failed to clear GitHub token: ' . $e->getMessage());
            }
        }

        // Détruire la session utilisateur
        $this->logUserAction('logout');
        $this->destroySession();

        // Rediriger vers la page de connexion
        $this->redirect('/auth/login');
    }

    // ============================================
    // MÉTHODES PRIVÉES DE VALIDATION
    // ============================================

    // Valider les entrées de connexion
    private function validateLoginInput(string $email, string $password): string|bool
    {
        if ($email === '') {
            return 'Email requis';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Email invalide';
        }

        if ($password === '') {
            return 'Mot de passe requis';
        }

        return true;
    }

    // Valider les entrées d'inscription
    private function validateRegisterInput(
        string $name,
        string $email,
        string $password,
        string $confirm
    ): string|bool {
        // Nom
        if ($name === '') {
            return 'Nom requis';
        }

        if (strlen($name) < 2) {
            return 'Le nom doit contenir au moins 2 caractères';
        }

        if (strlen($name) > 100) {
            return 'Le nom ne peut pas dépasser 100 caractères';
        }

        // Email
        if ($email === '') {
            return 'Email requis';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Email invalide';
        }

        // Mot de passe
        if ($password === '') {
            return 'Mot de passe requis';
        }

        if (strlen($password) < self::PASSWORD_MIN_LENGTH) {
            return 'Le mot de passe doit contenir au moins ' . self::PASSWORD_MIN_LENGTH . ' caractères';
        }

        // Vérifier la complexité du mot de passe
        if (!$this->isPasswordStrong($password)) {
            return 'Le mot de passe doit contenir au moins une majuscule, une minuscule et un chiffre';
        }

        // Confirmation
        if ($password !== $confirm) {
            return 'Les mots de passe ne correspondent pas';
        }

        return true;
    }

    // Vérifier la force du mot de passe
    private function isPasswordStrong(string $password): bool
    {
        // Au moins une minuscule, une majuscule et un chiffre
        return preg_match('/[a-z]/', $password) &&
               preg_match('/[A-Z]/', $password) &&
               preg_match('/[0-9]/', $password);
    }

    // ============================================
    // GESTION DES SESSIONS
    // ============================================

    // Créer une session utilisateur sécurisée
    private function createUserSession(int $userId, array $user): void
    {
        // Régénérer l'ID de session pour éviter le session fixation
        session_regenerate_id(true);

        // Stocker les infos utilisateur
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_role'] = (string)($user['role'] ?? 'student');
        $_SESSION['user_name'] = (string)($user['name'] ?? '');
        
        // Informations de sécurité
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // Définir un timeout de session si la session n'est pas encore active
        if (session_status() !== PHP_SESSION_ACTIVE) {
            ini_set('session.gc_maxlifetime', (string)self::SESSION_LIFETIME);
        }
    }

    // Détruire la session de manière sécurisée
    private function destroySession(): void
    {
        // Vider le tableau de session
        $_SESSION = [];

        // Détruire le cookie de session
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        // Détruire la session
        session_destroy();
    }

    // Vérifier si l'utilisateur est authentifié
    private function isAuthenticated(): bool
    {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        
        if ($userId <= 0) {
            return false;
        }

        // Vérifier le timeout de session
        $lastActivity = (int)($_SESSION['last_activity'] ?? 0);
        if (time() - $lastActivity > self::SESSION_LIFETIME) {
            $this->destroySession();
            return false;
        }

        // Mettre à jour l'activité
        $_SESSION['last_activity'] = time();

        return true;
    }

    // Rediriger l'utilisateur vers sa page d'accueil
    private function redirectToUserHome(): void
    {
        $role = (string)($_SESSION['user_role'] ?? 'student');
        $dest = ($role === 'formateur') ? '/dashboard' : '/kanban';
        $this->redirect($dest);
    }

    // ============================================
    // PROTECTION CONTRE BRUTE FORCE
    // ============================================

    // Vérifier si l'IP est verrouillée
    private function isIpLocked(): bool
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $key = 'login_attempts_' . md5($ip);

        $attempts = (int)($_SESSION[$key] ?? 0);
        $lockTime = (int)($_SESSION[$key . '_locked_until'] ?? 0);

        // Si verrouillé, vérifier si le temps est écoulé
        if ($lockTime > 0 && time() < $lockTime) {
            return true;
        }

        // Réinitialiser si le temps est écoulé
        if ($lockTime > 0 && time() >= $lockTime) {
            $_SESSION[$key] = 0;
            $_SESSION[$key . '_locked_until'] = 0;
        }

        return false;
    }

    // Enregistrer une tentative de connexion
    private function recordLoginAttempt(bool $success): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $key = 'login_attempts_' . md5($ip);

        if ($success) {
            // Réinitialiser en cas de succès
            $_SESSION[$key] = 0;
            $_SESSION[$key . '_locked_until'] = 0;
            return;
        }

        // Incrémenter les tentatives
        $attempts = (int)($_SESSION[$key] ?? 0) + 1;
        $_SESSION[$key] = $attempts;

        // Verrouiller si trop de tentatives
        if ($attempts >= self::MAX_LOGIN_ATTEMPTS) {
            $_SESSION[$key . '_locked_until'] = time() + self::LOGIN_LOCKOUT_TIME;
            error_log("IP locked due to too many login attempts: {$ip}");
        }
    }
}