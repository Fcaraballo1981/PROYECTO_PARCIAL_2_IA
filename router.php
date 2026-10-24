<?php
declare(strict_types=1);

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rawurldecode($requestPath);
$normalized = '/' . ltrim(str_replace('\\', '/', $path), '/');

$blockedPrefixes = ['/var/', '/migrations/'];
$blockedFiles = [
    '/api/cacert.pem',
    '/README.md',
    '/start-local.ps1',
];

$blocked = str_contains($normalized, '/.')
    || str_starts_with($normalized, '/api/_')
    || in_array($normalized, ['/var', '/migrations'], true)
    || in_array($normalized, $blockedFiles, true);

foreach ($blockedPrefixes as $prefix) {
    if (str_starts_with($normalized, $prefix)) {
        $blocked = true;
        break;
    }
}

if ($blocked) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'No encontrado';
    return true;
}

return false;
