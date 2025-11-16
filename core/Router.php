<?php
declare(strict_types=1);

namespace App\Core;

// ============================================
// EXCEPTIONS
// ============================================

class RouterException extends \Exception {}
class RouteNotFoundException extends RouterException {}
class MethodNotAllowedException extends RouterException {}
class ForbiddenException extends RouterException {}

// ============================================
// ROUTE MATCH (DTO)
// ============================================

class RouteMatch
{
    public function __construct(
        public readonly string $controller,
        public readonly string $action,
        public readonly array $params = [],
        public readonly array $middleware = []
    ) {}
}

// ============================================
// MIDDLEWARE INTERFACE
// ============================================

interface MiddlewareInterface
{
    public function handle(RouteMatch $route): void;
}

// ============================================
// AUTH MIDDLEWARE
// ============================================

class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly string $mode = 'redirect' // 'redirect' ou 'json'
    ) {}

    public function handle(RouteMatch $route): void
    {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        
        if ($userId <= 0) {
            if ($this->mode === 'json') {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'forbidden']);
                exit;
            } else {
                // Rediriger vers la page de connexion
                $script = $_SERVER['SCRIPT_NAME'] ?? '';
                $dir = rtrim(str_replace('index.php', '', $script), '/');
                $base = preg_replace('#/public$#', '', $dir);
                header('Location: ' . $base . '/auth/login');
                exit;
            }
        }
    }
}

// ============================================
// ROLE MIDDLEWARE
// ============================================

class RoleMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly string $requiredRole,
        private readonly ?\Closure $userLoader = null
    ) {}

    // Vérifier le rôle de l'utilisateur
    public function handle(RouteMatch $route): void
    {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        
        if ($userId <= 0) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'forbidden']);
            exit;
        }

        $user = $this->loadUser($userId);
        
        if (!$user || ($user['role'] ?? '') !== $this->requiredRole) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'forbidden']);
            exit;
        }
    }

    // Charger l'utilisateur à partir de la base de données
    private function loadUser(int $userId): ?array
    {
        static $userCache = [];
        
        if (isset($userCache[$userId])) {
            return $userCache[$userId];
        }

        try {
            if ($this->userLoader) {
                $user = ($this->userLoader)($userId);
            } elseif (class_exists('\App\Models\User')) {
                $user = \App\Models\User::findById($userId);
            } else {
                $user = null;
            }
            
            $userCache[$userId] = $user;
            return $user;
        } catch (\Throwable $e) {
            return null;
        }
    }
}

// ============================================
// ROUTER PRINCIPAL
// ============================================

class Router
{
    private array $routes = [];
    private array $globalMiddleware = [];
    private array $middlewareAliases = [
        'auth' => AuthMiddleware::class,
        'role' => RoleMiddleware::class,
    ];

    // Ajouter une route (compatible avec l'ancien format)
    public function add(
        string $method,
        string $path,
        string $controller,
        string $action,
        array $options = []
    ): self {
        // Gérer middleware à partir des options
        $middleware = $this->convertOptionsToMiddleware($options);
        $constraints = [];
        
        $this->routes[] = [
            'method' => strtoupper(trim($method)),
            'path' => '/' . trim($path, '/'),
            'controller' => $controller,
            'action' => $action,
            'middleware' => $middleware,
            'options' => $options,
            'compiled' => $this->compile($path, $constraints)
        ];
        
        return $this;
    }

    // Convertir les options en middleware
    private function convertOptionsToMiddleware(array $options): array
    {
        $middleware = [];
        
        // Gérer authentification
        if (isset($options['auth'])) {
            $authMode = $options['auth']; // 'redirect' ou 'json'
            $middleware[] = ['auth', $authMode];
        }
        
        // Gérer rôle
        if (isset($options['role'])) {
            $middleware[] = ['role', $options['role']];
        }
        
        return $middleware;
    }

    // Raccourcis pour les méthodes HTTP
    public function get(string $path, string $controller, string $action, array $options = []): self
    {
        return $this->add('GET', $path, $controller, $action, $options);
    }

    public function post(string $path, string $controller, string $action, array $options = []): self
    {
        return $this->add('POST', $path, $controller, $action, $options);
    }

    public function put(string $path, string $controller, string $action, array $options = []): self
    {
        return $this->add('PUT', $path, $controller, $action, $options);
    }

    public function delete(string $path, string $controller, string $action, array $options = []): self
    {
        return $this->add('DELETE', $path, $controller, $action, $options);
    }

    public function patch(string $path, string $controller, string $action, array $options = []): self
    {
        return $this->add('PATCH', $path, $controller, $action, $options);
    }

    // Groupe de routes avec préfixe et options communs
    public function group(string $prefix, array $options, callable $callback): self
    {
        $originalRoutes = count($this->routes);
        $callback($this);
        
        // Ajouter le préfixe et fusionner les options aux nouvelles routes
        for ($i = $originalRoutes; $i < count($this->routes); $i++) {
            $this->routes[$i]['path'] = '/' . trim($prefix, '/') . $this->routes[$i]['path'];
            
            // Fusionner les options avec celles existantes
            $this->routes[$i]['options'] = array_merge($options, $this->routes[$i]['options']);
            
            // Convertir les options en middleware
            $this->routes[$i]['middleware'] = $this->convertOptionsToMiddleware($this->routes[$i]['options']);
            
            // Compiler le nouveau chemin
            $this->routes[$i]['compiled'] = $this->compile($this->routes[$i]['path'], []);
        }
        
        return $this;
    }

    // Compiler un pattern de route
    private function compile(string $path, array $constraints): array
    {
        $params = [];
        $pattern = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            function($matches) use (&$params, $constraints) {
                $name = $matches[1];
                $params[] = $name;
                
                // Ajouter la contrainte si présente
                $constraint = $constraints[$name] ?? '[^/]+';
                return '(' . $constraint . ')';
            },
            $path
        );
        
        return [
            'regex' => '#^' . $pattern . '$#',
            'params' => $params
        ];
    }

    // Résoudre une route (compatible avec l'ancienne signature)
    public function resolve(array $map, string $method, string $uri): ?array
    {
        $method = strtoupper(trim($method));
        $path = '/' . trim($uri, '/');
        
        $matchedRoute = null;
        $params = [];

        foreach ($this->routes as $route) {
            // Vérifier si le chemin correspond
            if (preg_match($route['compiled']['regex'], $path, $matches)) {
                // Vérifier si la méthode correspond
                if ($route['method'] === $method) {
                    $matchedRoute = $route;
                    
                    // Enlever le match complet
                    array_shift($matches);
                    $params = array_combine($route['compiled']['params'], $matches) ?: [];
                    
                    break;
                }
            }
        }

        // Injecter les paramètres dans $_GET si trouvée
        if (!$matchedRoute) {
            $trim = trim($path, '/');
            $segments = $trim === '' ? [] : explode('/', $trim);
            if (!empty($segments)) {
                $first = $segments[0];
                if (isset($map[$first])) {
                    return [$first, $segments[1] ?? 'index'];
                }
            }
            return null;
        }

        // Injecter les paramètres dans $_GET
        foreach ($params as $key => $value) {
            $_GET[$key] = $value;
        }

        // Exécuter les middlewares d'une route
        $routeMatch = new RouteMatch(
            $matchedRoute['controller'],
            $matchedRoute['action'],
            $params,
            $matchedRoute['middleware']
        );

        try {
            $this->runMiddleware($routeMatch);
        } catch (\Throwable $e) {
            return null;
        }

        // Retourner les informations de la route
        return [$matchedRoute['controller'], $matchedRoute['action']];
    }

    // Exécuter les middlewares d'une route 
    private function runMiddleware(RouteMatch $route): void
    {
        foreach ($route->middleware as $middleware) {
            $instance = $this->resolveMiddleware($middleware);
            $instance->handle($route);
        }
    }

    // Résoudre une instance de middleware
    private function resolveMiddleware(string|array|MiddlewareInterface $middleware): MiddlewareInterface
    {
        // Déjà une instance de middleware
        if ($middleware instanceof MiddlewareInterface) {
            return $middleware;
        }

        // Tableau [alias, ...params]
        if (is_array($middleware)) {
            $alias = $middleware[0];
            $params = array_slice($middleware, 1);
            $className = $this->middlewareAliases[$alias] ?? $alias;
            return new $className(...$params);
        }

        // Classe nommée directe
        $className = $this->middlewareAliases[$middleware] ?? $middleware;
        return new $className();
    }

    // Dispatcher complet
    public function dispatch(array $map, string $method, string $uri): void
    {
        $resolved = $this->resolve($map, $method, $uri);
        
        if (!$resolved) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Route not found']);
            exit;
        }
        
        [$controller, $action] = $resolved;

        if (!isset($map[$controller])) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Controller not found']);
            exit;
        }

        $class = $map[$controller];
        $instance = new $class();

        if (!method_exists($instance, $action)) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Action not found']);
            exit;
        }

        call_user_func([$instance, $action]);
    }
}