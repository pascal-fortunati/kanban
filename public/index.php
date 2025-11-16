<?php
declare(strict_types=1);

// Chemin vers le répertoire racine de l'application
$baseDir = dirname(__DIR__);

// Charger les variables d'environnement
$envFile = $baseDir . DIRECTORY_SEPARATOR . '.env';
if (is_file($envFile) && is_readable($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (is_array($lines)) {
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') { continue; }
            if (strpos($line, '=') === false) { continue; }
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k);
            $v = trim($v);
            if ($v !== '' && (($v[0] === '"' && substr($v, -1) === '"') || ($v[0] === "'" && substr($v, -1) === "'"))) { $v = substr($v, 1, -1); }
            if ($k !== '') { putenv($k . '=' . $v); $_ENV[$k] = $v; $_SERVER[$k] = $v; }
        }
    }
}
// Charger la clé de l'application
$appKey = getenv('APP_KEY') ?: '';
if ($appKey === '') {
    throw new \RuntimeException('APP_KEY is not set in the environment variables');
}
if (!function_exists('env')) {
    function env(string $key, $default = null): string
    {
        $v = $_ENV[$key] ?? getenv($key);
        if ($v === false || $v === null || $v === '') {
            $d = $default;
            if ($d === null) { return ''; }
            return is_string($d) ? $d : (string)$d;
        }
        return (string)$v;
    }
}

// Charger l'autoloader
spl_autoload_register(function ($class) use ($baseDir) {
    $prefix = 'App\\';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relative = substr($class, $len);
    $parts = explode('\\', $relative);
    $first = array_shift($parts);
    $dirMap = [
        'Controllers' => 'controllers',
        'Models' => 'models',
        'Services' => 'services',
        'Core' => 'core',
    ];
    if (!isset($dirMap[$first])) {
        return;
    }
    $file = $baseDir . DIRECTORY_SEPARATOR . $dirMap[$first] . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $parts) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

// Charger les contrôleurs
use App\Controllers\KanbanController;
use App\Controllers\AuthController;
use App\Controllers\ProfileController;
use App\Controllers\DashboardController;
use App\Controllers\GitHubController;
use App\Core\Router;

// Charger la session
session_start();

$map = [
    'kanban' => KanbanController::class,
    'auth' => AuthController::class,
    'profile' => ProfileController::class,
    'dashboard' => DashboardController::class,
    'github' => GitHubController::class,
];

// Charger le chemin de base de l'application
$script = $_SERVER['SCRIPT_NAME'] ?? '';
$dir = rtrim(str_replace('index.php', '', $script), '/');
$basePrefix = preg_replace('#/public$#', '', $dir);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '/';
if ($basePrefix && strpos($path, $basePrefix) === 0) {
    $path = substr($path, strlen($basePrefix));
    if ($path === '') { $path = '/'; }
}

// Définir les routes
$router = new Router();

// Routes publiques
$router
    ->get('/',                    'auth', 'login')
    ->get('/kanban',              'kanban', 'index');

// Routes Auth (publiques)
$router
    ->get('/auth/login',          'auth', 'login')
    ->get('/auth/register',       'auth', 'register')
    ->post('/auth/doLogin',       'auth', 'doLogin')
    ->post('/auth/doRegister',    'auth', 'doRegister')
    ->get('/auth/logout',         'auth', 'logout');

// Routes Kanban avec auth JSON
$router
    ->post('/kanban/create',      'kanban', 'create', ['auth' => 'json'])
    ->post('/kanban/move',        'kanban', 'move', ['auth' => 'json'])
    ->post('/kanban/update',      'kanban', 'update', ['auth' => 'json'])
    ->post('/kanban/delete',      'kanban', 'delete', ['auth' => 'json'])
    ->get('/kanban/task/{id}',    'kanban', 'getTask', ['auth' => 'json'])
    ->post('/api/tasks/move',     'kanban', 'move', ['auth' => 'json']);

// Routes Profile (auth required)
$router
    ->get('/profile',                        'profile', 'index', ['auth' => 'redirect'])
    ->post('/profile/setActiveRepo',         'profile', 'setActiveRepo', ['auth' => 'json'])
    ->post('/profile/deactivateActiveRepo',  'profile', 'deactivateActiveRepo', ['auth' => 'json']);

// Routes Dashboard (auth + role formateur)
$router
    ->get('/dashboard',                      'dashboard', 'index', ['auth' => 'redirect', 'role' => 'formateur'])
    ->get('/dashboard/logs',                 'dashboard', 'logs', ['auth' => 'redirect', 'role' => 'formateur'])
    ->get('/dashboard/getStats',             'dashboard', 'getStats', ['auth' => 'json', 'role' => 'formateur'])
    ->get('/dashboard/getStudentRepos',      'dashboard', 'getStudentRepos', ['auth' => 'json', 'role' => 'formateur'])
    ->post('/dashboard/broadcastTemplate',   'dashboard', 'broadcastTemplate', ['auth' => 'json', 'role' => 'formateur'])
    ->post('/dashboard/redeployFromBroadcast','dashboard', 'redeployFromBroadcast', ['auth' => 'json', 'role' => 'formateur'])
    ->post('/dashboard/deleteBroadcast',     'dashboard', 'deleteBroadcast', ['auth' => 'json', 'role' => 'formateur'])
    ->post('/dashboard/clearTasks',          'dashboard', 'clearTasks', ['auth' => 'json', 'role' => 'formateur'])
    ->get('/dashboard/getCommits',           'dashboard', 'getCommits', ['auth' => 'json', 'role' => 'formateur']);

// Routes Dashboard (auth seulement)
$router
    ->get('/dashboard/getNotifications',         'dashboard', 'getNotifications', ['auth' => 'json'])
    ->get('/dashboard/getMissedBroadcasts',      'dashboard', 'getMissedBroadcasts', ['auth' => 'json'])
    ->post('/dashboard/markNotificationRead',    'dashboard', 'markNotificationRead', ['auth' => 'json'])
    ->post('/dashboard/redeploySelfFromBroadcast','dashboard', 'redeploySelfFromBroadcast', ['auth' => 'json']);

// Routes GitHub
$router
    ->get('/github/authenticate',      'github', 'authenticate', ['auth' => 'redirect'])
    ->get('/github/callback',          'github', 'callback', ['auth' => 'redirect'])
    ->post('/github/createRepository', 'github', 'createRepository', ['auth' => 'json'])
    ->post('/github/deleteRepository', 'github', 'deleteRepository', ['auth' => 'json'])
    ->post('/github/syncRepositories', 'github', 'syncRepositories', ['auth' => 'json'])
    ->get('/github/getCommits',        'github', 'getCommits', ['auth' => 'json', 'role' => 'formateur'])
    ->get('/github/getReposInfo',      'github', 'getReposInfo', ['auth' => 'json'])
    ->get('/github/scanCommits',       'github', 'scanCommits', ['auth' => 'json', 'role' => 'formateur']);

// Dispatcher la requête (remplace les 15 dernières lignes)
$router->dispatch($map, $method, $path);