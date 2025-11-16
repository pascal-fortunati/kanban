<?php
declare(strict_types=1);

namespace App\Services;

// ============================================
// GITHUB CLIENT EXCEPTIONS
// ============================================

class GitHubException extends \Exception {}
class GitHubAuthException extends GitHubException {}
class GitHubRateLimitException extends GitHubException {}
class GitHubNotFoundException extends GitHubException {}
class GitHubValidationException extends GitHubException {}

// ============================================
// GITHUB CLIENT RESPONSE
// ============================================

class GitHubResponse
{
    public function __construct(
        public readonly int $statusCode,
        public readonly ?array $data = null,
        public readonly ?string $error = null,
        public readonly array $headers = []
    ) {}

    public function isSuccess(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    public function getRateLimit(): array
    {
        return [
            'limit' => (int)($this->headers['x-ratelimit-limit'] ?? 0),
            'remaining' => (int)($this->headers['x-ratelimit-remaining'] ?? 0),
            'reset' => (int)($this->headers['x-ratelimit-reset'] ?? 0),
        ];
    }
}

// ============================================
// GITHUB CLIENT
// ============================================

class GitHubClient
{
    private const API_BASE = 'https://api.github.com';
    private const OAUTH_BASE = 'https://github.com';
    private const USER_AGENT = 'KanbanApp/1.0';
    private const TIMEOUT = 30;
    private const CONNECT_TIMEOUT = 10;

    private ?string $lastError = null;
    private array $lastRateLimit = [];

    // Préparer les headers pour une requête
    private function headers(?string $token = null, array $extra = []): array
    {
        $headers = [
            'Accept: application/vnd.github+json',
            'User-Agent: ' . self::USER_AGENT,
            'X-GitHub-Api-Version: 2022-11-28'
        ];
        
        if ($token) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }
        
        return array_merge($headers, $extra);
    }

    // Initialiser une session cURL avec options communes
    private function initCurl(string $url, array $headers = []): \CurlHandle
    {
        $ch = curl_init($url);
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_HEADER => true, // Pour récupérer les headers dans la réponse
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        
        return $ch;
    }

    // Exécuter une requête cURL et parser la réponse
    private function execCurl(\CurlHandle $ch): GitHubResponse
    {
        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $error = curl_error($ch);
        
        curl_close($ch);

        // Erreur cURL ou réponse vide
        if ($response === false || $error) {
            $this->lastError = $error ?: 'Unknown cURL error';
            throw new GitHubException('Request failed: ' . $this->lastError);
        }

        // Diviser la réponse en headers et body
        $headerText = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        
        // Parser les headers HTTP
        $headers = $this->parseHeaders($headerText);
        $this->lastRateLimit = [
            'limit' => (int)($headers['x-ratelimit-limit'] ?? 0),
            'remaining' => (int)($headers['x-ratelimit-remaining'] ?? 0),
            'reset' => (int)($headers['x-ratelimit-reset'] ?? 0),
        ];

        // Parser le body JSON si présent
        $data = null;
        if ($body) {
            $decoded = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $data = $decoded;
            }
        }

        $response = new GitHubResponse($statusCode, $data, null, $headers);

        // Gérer les erreurs HTTP basées sur le code de statut
        $this->handleHttpError($response);

        return $response;
    }

    // Parser les headers HTTP
    private function parseHeaders(string $headerText): array
    {
        $headers = [];
        $lines = explode("\r\n", $headerText);
        
        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                [$key, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($key))] = trim($value);
            }
        }
        
        return $headers;
    }

    // Gérer les erreurs HTTP basées sur le code de statut
    private function handleHttpError(GitHubResponse $response): void
    {
        if ($response->isSuccess()) {
            return;
        }

        $message = $response->data['message'] ?? 'Unknown error';
        $code = $response->statusCode;

        match (true) {
            $code === 401 => throw new GitHubAuthException("Authentication failed: {$message}", $code),
            $code === 403 && isset($response->headers['x-ratelimit-remaining']) && $response->headers['x-ratelimit-remaining'] === '0' 
                => throw new GitHubRateLimitException("Rate limit exceeded. Reset at: " . date('Y-m-d H:i:s', $this->lastRateLimit['reset']), $code),
            $code === 404 => throw new GitHubNotFoundException("Resource not found: {$message}", $code),
            $code === 422 => throw new GitHubValidationException("Validation failed: {$message}", $code),
            $code >= 500 => throw new GitHubException("GitHub server error: {$message}", $code),
            default => throw new GitHubException("GitHub API error: {$message}", $code)
        };
    }

    // Effectuer une requête GET
    private function httpGet(string $url, array $headers = []): GitHubResponse
    {
        $ch = $this->initCurl($url, $headers);
        return $this->execCurl($ch);
    }

    // Effectuer une requête POST avec corps JSON
    private function httpPostJson(string $url, array $json, array $headers = []): GitHubResponse
    {
        $headers[] = 'Content-Type: application/json';
        $ch = $this->initCurl($url, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json));
        return $this->execCurl($ch);
    }

    // Effectuer une requête POST avec corps de formulaire
    private function httpPostForm(string $url, array $params, array $headers = []): GitHubResponse
    {
        $ch = $this->initCurl($url, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        return $this->execCurl($ch);
    }

    // Effectuer une requête PATCH avec corps JSON
    private function httpPatchJson(string $url, array $json, array $headers = []): GitHubResponse
    {
        $headers[] = 'Content-Type: application/json';
        $ch = $this->initCurl($url, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json));
        return $this->execCurl($ch);
    }

    // Effectuer une requête PUT avec corps JSON
    private function httpPutJson(string $url, array $json, array $headers = []): GitHubResponse
    {
        $headers[] = 'Content-Type: application/json';
        $ch = $this->initCurl($url, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json));
        return $this->execCurl($ch);
    }

    // Effectuer une requête DELETE
    private function httpDelete(string $url, array $headers = []): GitHubResponse
    {
        $ch = $this->initCurl($url, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        return $this->execCurl($ch);
    }

    // ============================================
    // GITHUB CLIENT OAUTH METHODS
    // ============================================

    // Obtenir l'URL d'autorisation OAuth
    public function oauthAuthorizeUrl(array $params): string
    {
        return self::OAUTH_BASE . '/login/oauth/authorize?' . http_build_query($params);
    }

    // Échanger le code OAuth contre un access token
    public function oauthAccessToken(array $params): array
    {
        $response = $this->httpPostForm(
            self::OAUTH_BASE . '/login/oauth/access_token',
            $params,
            ['Accept: application/json']
        );

        if (!isset($response->data['access_token'])) {
            throw new GitHubAuthException('Failed to get access token');
        }

        return $response->data;
    }

    // Obtenir les infos de l'utilisateur connecté
    public function getUser(string $token): array
    {
        $response = $this->httpGet(self::API_BASE . '/user', $this->headers($token));
        return $response->data ?? [];
    }

    // Lister les repos de l'utilisateur
    public function listUserRepos(string $token, int $perPage = 50, string $sort = 'updated', string $direction = 'desc'): array
    {
        $params = http_build_query([
            'per_page' => min($perPage, 100),
            'sort' => $sort,
            'direction' => $direction
        ]);
        
        $response = $this->httpGet(
            self::API_BASE . '/user/repos?' . $params,
            $this->headers($token)
        );
        
        return $response->data ?? [];
    }

    // Créer un repository
    public function createRepo(
        string $token,
        string $name,
        ?string $description = null,
        bool $private = false,
        bool $autoInit = false
    ): array {
        $body = [
            'name' => $name,
            'private' => $private,
            'auto_init' => $autoInit
        ];
        
        if ($description !== null) {
            $body['description'] = $description;
        }

        $response = $this->httpPostJson(
            self::API_BASE . '/user/repos',
            $body,
            $this->headers($token)
        );

        return $response->data ?? [];
    }

    // Supprimer un repository
    public function deleteRepo(string $token, string $owner, string $repo): bool
    {
        $response = $this->httpDelete(
            self::API_BASE . '/repos/' . $owner . '/' . $repo,
            $this->headers($token)
        );

        return $response->isSuccess();
    }

    // Lister les commits d'un repo avec pagination et filtre par branche
    public function listCommits(
        string $token,
        string $owner,
        string $repo,
        int $perPage = 20,
        ?string $sha = null
    ): array {
        $params = ['per_page' => min($perPage, 100)];
        if ($sha) {
            $params['sha'] = $sha;
        }

        $response = $this->httpGet(
            self::API_BASE . '/repos/' . $owner . '/' . $repo . '/commits?' . http_build_query($params),
            $this->headers($token)
        );

        return $response->data ?? [];
    }

    // Obtenir le contenu d'un fichier
    public function getContent(string $token, string $owner, string $repo, string $path, ?string $ref = null): array
    {
        $url = self::API_BASE . '/repos/' . $owner . '/' . $repo . '/contents/' . rawurlencode($path);
        if ($ref) {
            $url .= '?ref=' . urlencode($ref);
        }

        $response = $this->httpGet($url, $this->headers($token));
        return $response->data ?? [];
    }

    // Créer ou mettre à jour un fichier
    public function putContent(
        string $token,
        string $owner,
        string $repo,
        string $path,
        string $message,
        string $contentBase64,
        ?string $sha = null,
        ?string $branch = null
    ): array {
        $body = [
            'message' => $message,
            'content' => $contentBase64
        ];

        if ($sha) {
            $body['sha'] = $sha;
        }
        if ($branch) {
            $body['branch'] = $branch;
        }

        $response = $this->httpPutJson(
            self::API_BASE . '/repos/' . $owner . '/' . $repo . '/contents/' . rawurlencode($path),
            $body,
            $this->headers($token)
        );

        return $response->data ?? [];
    }

    // Créer une issue
    public function createIssue(string $token, string $owner, string $repo, array $body): array
    {
        $response = $this->httpPostJson(
            self::API_BASE . '/repos/' . $owner . '/' . $repo . '/issues',
            $body,
            $this->headers($token)
        );

        return $response->data ?? [];
    }

    // Lister les labels d'un repo
    public function listLabels(string $token, string $owner, string $repo, int $perPage = 100): array
    {
        $response = $this->httpGet(
            self::API_BASE . '/repos/' . $owner . '/' . $repo . '/labels?per_page=' . min($perPage, 100),
            $this->headers($token)
        );

        return $response->data ?? [];
    }

    // Mettre à jour la couleur d'un label
    public function updateLabelColor(string $token, string $owner, string $repo, string $name, string $color): array
    {
        $response = $this->httpPatchJson(
            self::API_BASE . '/repos/' . $owner . '/' . $repo . '/labels/' . rawurlencode($name),
            ['color' => ltrim(strtolower($color), '#')],
            $this->headers($token)
        );

        return $response->data ?? [];
    }

    // Créer un label
    public function createLabel(string $token, string $owner, string $repo, string $name, string $color, ?string $description = null): array
    {
        $body = [
            'name' => $name,
            'color' => ltrim(strtolower($color), '#')
        ];

        if ($description) {
            $body['description'] = $description;
        }

        $response = $this->httpPostJson(
            self::API_BASE . '/repos/' . $owner . '/' . $repo . '/labels',
            $body,
            $this->headers($token)
        );

        return $response->data ?? [];
    }

    // Obtenir les infos de rate limit
    public function getRateLimit(): array
    {
        return $this->lastRateLimit;
    }

    // Vérifier si on approche de la limite
    public function isApproachingRateLimit(int $threshold = 10): bool
    {
        return !empty($this->lastRateLimit) 
            && $this->lastRateLimit['remaining'] > 0 
            && $this->lastRateLimit['remaining'] <= $threshold;
    }
}