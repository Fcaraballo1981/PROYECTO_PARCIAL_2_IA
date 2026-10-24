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
// Vercel Functions accept request bodies up to 4.5 MB, including multipart overhead.
const MAX_IMAGE_BYTES = 4000000;
const MAX_TOTAL_IMAGE_BYTES = 4000000;
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
    $scheme = requestIsHttps() ? 'https' : 'http';
    return hash_equals(strtolower($_SERVER['HTTP_HOST'] ?? ''), $originHost) && strtolower($parts['scheme'] ?? '') === $scheme;
}

function requestIsHttps(): bool {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
    $forwarded = strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
    return $forwarded === 'https';
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

    $databaseUrl = trim((string)(getenv('DATABASE_URL') ?: getenv('POSTGRES_URL') ?: ''));
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    if ($databaseUrl !== '') {
        if (!extension_loaded('pdo_pgsql')) jsonResponse(503, ['error' => 'El servidor no tiene habilitada la extensión pdo_pgsql.', 'code' => 'database_configuration']);
        $parts = parse_url($databaseUrl);
        if (!is_array($parts) || !isset($parts['host'], $parts['path'], $parts['user'])) {
            jsonResponse(503, ['error' => 'DATABASE_URL no tiene un formato válido.', 'code' => 'database_configuration']);
        }
        parse_str($parts['query'] ?? '', $query);
        $sslMode = preg_replace('/[^a-z-]/', '', (string)($query['sslmode'] ?? 'require')) ?: 'require';
        $dsn = sprintf(
            'pgsql:host=%s;port=%d;dbname=%s;sslmode=%s',
            $parts['host'],
            (int)($parts['port'] ?? 5432),
            ltrim($parts['path'], '/'),
            $sslMode
        );
        $pdo = new PDO($dsn, rawurldecode($parts['user']), rawurldecode($parts['pass'] ?? ''), $options);
        $migrationPath = dirname(__DIR__) . '/migrations/001_sell_module_postgres.sql';
    } else {
        if (!extension_loaded('pdo_sqlite')) jsonResponse(503, ['error' => 'El módulo necesita la extensión pdo_sqlite de PHP.', 'code' => 'database_configuration']);
        $directory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'var';
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) jsonResponse(500, ['error' => 'No se pudo preparar el almacenamiento.', 'code' => 'storage']);
        $pdo = new PDO('sqlite:' . $directory . DIRECTORY_SEPARATOR . 'gabinete.sqlite', null, null, $options);
        $pdo->exec('PRAGMA foreign_keys = ON; PRAGMA journal_mode = WAL; PRAGMA busy_timeout = 5000;');
        $migrationPath = dirname(__DIR__) . '/migrations/001_sell_module.sql';
    }

    $migration = file_get_contents($migrationPath);
    if ($migration === false) throw new RuntimeException('No se encontró la migración del módulo.');
    $pdo->exec($migration);
    return $pdo;
}

function databaseDriver(): string {
    return (string)database()->getAttribute(PDO::ATTR_DRIVER_NAME);
}

function insertAndGetId(string $sql, array $values): int {
    $pdo = database();
    if (databaseDriver() === 'pgsql') {
        $statement = $pdo->prepare(rtrim($sql, " \t\n\r\0\x0B;") . ' RETURNING id');
        $statement->execute($values);
        return (int)$statement->fetchColumn();
    }
    $statement = $pdo->prepare($sql);
    $statement->execute($values);
    return (int)$pdo->lastInsertId();
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
function startSecureSession(string $name = 'gabinete_admin', string $sameSite = 'Strict'): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    static $handlerConfigured = false;
    if (!$handlerConfigured) {
        session_set_save_handler(new DatabaseSessionHandler(), true);
        $handlerConfigured = true;
    }
    session_name($name);
    session_set_cookie_params(['httponly'=>true,'secure'=>requestIsHttps(),'samesite'=>$sameSite,'path'=>'/']);
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

final class DatabaseSessionHandler implements SessionHandlerInterface {
    public function open(string $path, string $name): bool { return true; }
    public function close(): bool { return true; }
    public function read(string $id): string|false {
        $statement = database()->prepare('SELECT data FROM app_sessions WHERE id = ?');
        $statement->execute([$id]);
        $data = $statement->fetchColumn();
        return $data === false ? '' : (string)$data;
    }
    public function write(string $id, string $data): bool {
        $statement = database()->prepare(
            'INSERT INTO app_sessions(id, data, last_activity) VALUES(?, ?, ?)
             ON CONFLICT (id) DO UPDATE SET data = excluded.data, last_activity = excluded.last_activity'
        );
        return $statement->execute([$id, $data, time()]);
    }
    public function destroy(string $id): bool {
        $statement = database()->prepare('DELETE FROM app_sessions WHERE id = ?');
        return $statement->execute([$id]);
    }
    public function gc(int $max_lifetime): int|false {
        $statement = database()->prepare('DELETE FROM app_sessions WHERE last_activity < ?');
        $statement->execute([time() - $max_lifetime]);
        return $statement->rowCount();
    }
}
