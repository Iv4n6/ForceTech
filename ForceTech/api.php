<?php
declare(strict_types=1);

/*
 * Lightweight URL Shortener API
 * - POST /api.php      -> Create/reuse a short link
 * - GET  /api.php?c=xx -> Redirect to long URL
 */

// ------------------------------
// Basic CORS and method handling
// ------------------------------
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ------------------------------
// Configuration (adjust for your environment)
// Prefer using environment variables to avoid committing secrets.
// Supported env vars: DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_CHARSET, BASE_SHORT_URL
// Example (.env): DB_HOST=127.0.0.1
// ------------------------------
$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbName = getenv('DB_NAME') ?: 'url_shortener';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: ''; // Set your DB password in production via env var.
$dbCharset = getenv('DB_CHARSET') ?: 'utf8mb4';
$baseShortUrl = getenv('BASE_SHORT_URL') ?: ''; // Empty => auto-detect current host

$dsn = "mysql:host={$dbHost};dbname={$dbName};charset={$dbCharset}";

try {
    $pdo = new PDO(
        $dsn,
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    jsonResponse([
        'success' => false,
        'error' => 'Database connection failed.',
    ], 500);
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET' && isset($_GET['c'])) {
    handleRedirect($pdo);
}

if ($method === 'GET') {
    $pathCode = extractShortCodeFromPath();
    if ($pathCode !== null) {
        $_GET['c'] = $pathCode;
        handleRedirect($pdo);
    }
}

if ($method === 'POST') {
    handleCreateShortUrl($pdo);
}

jsonResponse([
    'success' => false,
    'error' => 'Unsupported endpoint or method.',
], 405);

/**
 * Handles POST /api.php
 */
function handleCreateShortUrl(PDO $pdo): void
{
    header('Content-Type: application/json; charset=utf-8');

    $rawBody = file_get_contents('php://input');
    $payload = json_decode($rawBody, true);

    if (!is_array($payload)) {
        jsonResponse([
            'success' => false,
            'error' => 'Invalid JSON payload.',
        ], 400);
    }

    $url = isset($payload['url']) ? trim((string)$payload['url']) : '';

    if ($url === '') {
        jsonResponse([
            'success' => false,
            'error' => 'URL is required.',
        ], 422);
    }

    // Require explicit http/https URL.
    if (!preg_match('#^https?://#i', $url) || !filter_var($url, FILTER_VALIDATE_URL)) {
        jsonResponse([
            'success' => false,
            'error' => 'Please provide a valid URL starting with http:// or https://.',
        ], 422);
    }

    // Reuse existing mapping when long URL already exists.
    $selectExisting = $pdo->prepare('SELECT short_code FROM urls WHERE long_url = :long_url LIMIT 1');
    $selectExisting->execute([':long_url' => $url]);
    $existing = $selectExisting->fetch();

    if ($existing && isset($existing['short_code'])) {
        $shortLink = buildShortLink((string)$existing['short_code']);
        jsonResponse([
            'success' => true,
            'short_code' => (string)$existing['short_code'],
            'short_url' => $shortLink,
            'reused' => true,
        ]);
    }

    $maxAttempts = 12;
    $shortCode = '';

    for ($i = 0; $i < $maxAttempts; $i++) {
        $shortCode = generateShortCode(random_int(6, 8));

        $checkCode = $pdo->prepare('SELECT id FROM urls WHERE short_code = :short_code LIMIT 1');
        $checkCode->execute([':short_code' => $shortCode]);

        if (!$checkCode->fetch()) {
            break;
        }

        $shortCode = '';
    }

    if ($shortCode === '') {
        jsonResponse([
            'success' => false,
            'error' => 'Could not generate a unique short code. Try again.',
        ], 500);
    }

    $insert = $pdo->prepare(
        'INSERT INTO urls (long_url, short_code, created_at) VALUES (:long_url, :short_code, NOW())'
    );

    try {
        $insert->execute([
            ':long_url' => $url,
            ':short_code' => $shortCode,
        ]);
    } catch (PDOException $e) {
        // Handles rare race condition collisions on UNIQUE(short_code).
        if ((int)$e->getCode() === 23000) {
            jsonResponse([
                'success' => false,
                'error' => 'Collision detected, please retry your request.',
            ], 409);
        }

        jsonResponse([
            'success' => false,
            'error' => 'Failed to save URL mapping.',
        ], 500);
    }

    jsonResponse([
        'success' => true,
        'short_code' => $shortCode,
        'short_url' => buildShortLink($shortCode),
        'reused' => false,
    ], 201);
}

/**
 * Handles GET /api.php?c={short_code}
 */
function handleRedirect(PDO $pdo): void
{
    $shortCode = trim((string)($_GET['c'] ?? ''));

    if ($shortCode === '' || !preg_match('/^[A-Za-z0-9]{6,8}$/', $shortCode)) {
        renderNotFound();
    }

    $stmt = $pdo->prepare('SELECT long_url FROM urls WHERE short_code = :short_code LIMIT 1');
    $stmt->execute([':short_code' => $shortCode]);

    $row = $stmt->fetch();

    if (!$row || !isset($row['long_url'])) {
        renderNotFound();
    }

    header_remove('Content-Type');
    header('Location: ' . $row['long_url'], true, 302);
    exit;
}

/**
 * Creates a secure random alphanumeric short code.
 */
function generateShortCode(int $length): string
{
    $alphabet = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $alphabetLength = strlen($alphabet);
    $code = '';

    for ($i = 0; $i < $length; $i++) {
        $index = random_int(0, $alphabetLength - 1);
        $code .= $alphabet[$index];
    }

    return $code;
}

/**
 * Builds a full short URL based on current host and script path.
 */
function buildShortLink(string $shortCode): string
{
    global $baseShortUrl;

    if ($baseShortUrl !== '') {
        return rtrim($baseShortUrl, '/') . '/' . rawurlencode($shortCode);
    }

    $isHttps = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        ((int)($_SERVER['SERVER_PORT'] ?? 80) === 443)
    );

    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptPath = dirname($_SERVER['SCRIPT_NAME'] ?? '/');

    if ($scriptPath === DIRECTORY_SEPARATOR || $scriptPath === '.') {
        $scriptPath = '';
    }

    return sprintf('%s://%s%s/%s', $scheme, $host, $scriptPath, rawurlencode($shortCode));
}

/**
 * Extracts short code from path-based URL format (/Ab12Xy).
 */
function extractShortCodeFromPath(): ?string
{
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    if ($requestUri === '') {
        return null;
    }

    $path = parse_url($requestUri, PHP_URL_PATH);
    if (!is_string($path)) {
        return null;
    }

    $scriptPath = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
    if ($scriptPath !== DIRECTORY_SEPARATOR && $scriptPath !== '.') {
        if (strpos($path, $scriptPath) === 0) {
            $path = substr($path, strlen($scriptPath));
        }
    }

    $candidate = trim($path, '/');

    if (preg_match('/^[A-Za-z0-9]{6,8}$/', $candidate)) {
        return $candidate;
    }

    return null;
}

/**
 * Sends a JSON response and exits.
 */
function jsonResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Renders a simple 404 page for unknown short codes.
 */
function renderNotFound(): void
{
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');

    echo '<!doctype html>';
    echo '<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>404 - Link Not Found</title>';
    echo '<style>body{font-family:Arial,sans-serif;background:#f6f7fb;color:#1f2937;display:grid;place-items:center;height:100vh;margin:0}.box{background:#fff;padding:2rem;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.08);text-align:center;max-width:460px}h1{margin:0 0 .5rem}p{margin:.25rem 0;color:#4b5563}</style>';
    echo '</head><body><div class="box"><h1>404</h1><p>Short link not found.</p><p>Please check the URL and try again.</p></div></body></html>';
    exit;
}
