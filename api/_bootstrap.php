<?php
declare(strict_types=1);

function loadDotEnv(string $path): void {
    if (!is_file($path) || !is_readable($path)) return;

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) return;

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (str_starts_with($line, 'export ')) $line = ltrim(substr($line, 7));

        $separator = strpos($line, '=');
        if ($separator === false) continue;

        $name = trim(substr($line, 0, $separator));
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) || getenv($name) !== false) continue;

        $value = trim(substr($line, $separator + 1));
        $length = strlen($value);
        if ($length >= 2 && (($value[0] === '"' && $value[$length - 1] === '"') || ($value[0] === "'" && $value[$length - 1] === "'"))) {
            $value = substr($value, 1, -1);
        }

        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

loadDotEnv(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

const APP_TIMEZONE = 'America/Montevideo';
const MAX_IMAGE_BYTES = 6291456;
const MAX_TOTAL_IMAGE_BYTES = 25165824;
const MAX_IMAGES = 8;

date_default_timezone_set(APP_TIMEZONE);

function jsonResponse(int $status, array $body): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function sameOrigin(): bool {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin === '') return true;
    $parts = parse_url($origin);
    if (!is_array($parts) || empty($parts['host'])) return false;
    $originHost = strtolower($parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : ''));
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return hash_equals(strtolower($_SERVER['HTTP_HOST'] ?? ''), $originHost) && strtolower($parts['scheme'] ?? '') === $scheme;
}

function requireMethod(string $method): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        header('Allow: ' . $method);
        jsonResponse(405, ['error' => 'Método no permitido.', 'code' => 'method_not_allowed']);
    }
    if (!sameOrigin()) jsonResponse(403, ['error' => 'Solicitud no permitida.', 'code' => 'forbidden']);
}

function database(): PDO {
    static $pdo;
    if ($pdo instanceof PDO) return $pdo;
    if (!extension_loaded('pdo_sqlite')) jsonResponse(503, ['error' => 'El módulo necesita la extensión pdo_sqlite de PHP.', 'code' => 'database_configuration']);
    $directory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'var';
    if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) jsonResponse(500, ['error' => 'No se pudo preparar el almacenamiento.', 'code' => 'storage']);
    $pdo = new PDO('sqlite:' . $directory . DIRECTORY_SEPARATOR . 'gabinete.sqlite', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON; PRAGMA journal_mode = WAL; PRAGMA busy_timeout = 5000;');
    $migration = file_get_contents(dirname(__DIR__) . '/migrations/001_sell_module.sql');
    if ($migration === false) throw new RuntimeException('No se encontró la migración del módulo.');
    $pdo->exec($migration);
    return $pdo;
}

function nowIso(): string { return (new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE)))->format(DateTimeInterface::ATOM); }
function cleanText(mixed $value, int $max, bool $required = false): string {
    $text = trim((string)$value);
    if (($required && $text === '') || mb_strlen($text) > $max) throw new InvalidArgumentException('Revisá los campos obligatorios y su extensión.');
    return $text;
}
function randomToken(int $bytes = 24): string { return bin2hex(random_bytes($bytes)); }
function isPublicToken(string $token): bool { return strlen($token) === 48 && ctype_xdigit($token); }
function requestJson(): array {
    $raw = file_get_contents('php://input', false, null, 0, 100000);
    $data = json_decode((string)$raw, true);
    if (!is_array($data)) jsonResponse(400, ['error' => 'La solicitud no es válida.', 'code' => 'invalid_request']);
    return $data;
}
function startSecureSession(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('gabinete_admin');
    session_set_cookie_params(['httponly'=>true,'secure'=>(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),'samesite'=>'Strict','path'=>'/']);
    session_start();
}
function releaseSession(): void {
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
}
function requireAdmin(): void { startSecureSession(); if (empty($_SESSION['admin'])) jsonResponse(401, ['error'=>'Iniciá sesión para continuar.','code'=>'unauthorized']); }
function requireCsrf(array $data): void { if (!isset($data['csrf']) || !hash_equals((string)($_SESSION['csrf'] ?? ''), (string)$data['csrf'])) jsonResponse(403, ['error'=>'La sesión venció. Volvé a ingresar.','code'=>'csrf']); }

function normalizedFiles(string $field): array {
    if (!isset($_FILES[$field])) return [];
    $files = $_FILES[$field];
    if (!is_array($files['name'])) return [$files];
    $result = [];
    foreach ($files['name'] as $i => $name) $result[] = ['name'=>$name,'type'=>$files['type'][$i] ?? '','tmp_name'=>$files['tmp_name'][$i] ?? '','error'=>$files['error'][$i] ?? UPLOAD_ERR_NO_FILE,'size'=>$files['size'][$i] ?? 0];
    return $result;
}

function rateLimit(string $key, int $limit, int $seconds): void {
    startSecureSession();
    $now = time();
    $bucket = array_values(array_filter($_SESSION['rate'][$key] ?? [], fn($v) => is_int($v) && $v > $now - $seconds));
    if (count($bucket) >= $limit) jsonResponse(429, ['error'=>'Recibimos varias solicitudes seguidas. Esperá un momento.','code'=>'rate_limit']);
    $bucket[] = $now; $_SESSION['rate'][$key] = $bucket;
}
