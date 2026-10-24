<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

requireMethod('GET');

$imageId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$token = (string)($_GET['token'] ?? '');
if (!$imageId) jsonResponse(404, ['error' => 'Imagen no encontrada.']);

$pdo = database();
$allowed = false;

startSecureSession();
if (!empty($_SESSION['admin'])) {
    $allowed = true;
} elseif (isPublicToken($token)) {
    $permission = $pdo->prepare(
        'SELECT 1
         FROM watch_images i
         JOIN watch_submissions s ON s.id = i.submission_id
         WHERE i.id = ? AND s.public_token = ?'
    );
    $permission->execute([$imageId, $token]);
    $allowed = (bool)$permission->fetch();
}
releaseSession();

if (!$allowed) jsonResponse(403, ['error' => 'No tenés acceso a esta imagen.']);

$statement = $pdo->prepare('SELECT mime_type, image_data FROM watch_images WHERE id = ?');
$statement->execute([$imageId]);
$image = $statement->fetch();
if (!$image) jsonResponse(404, ['error' => 'Imagen no encontrada.']);

header('Content-Type: ' . $image['mime_type']);
header('Content-Length: ' . strlen($image['image_data']));
header('Cache-Control: private, max-age=300');
header('X-Content-Type-Options: nosniff');
echo $image['image_data'];
