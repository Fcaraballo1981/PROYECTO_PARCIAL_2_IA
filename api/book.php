<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

requireMethod('POST');
rateLimit('booking', 8, 3600);
releaseSession();
$data = requestJson();

try {
    $submissionId = filter_var($data['submission_id'] ?? null, FILTER_VALIDATE_INT);
    $token = (string)($data['token'] ?? '');
    $requestedStart = trim((string)($data['starts_at'] ?? ''));

    if (!$submissionId || !isPublicToken($token)) {
        throw new InvalidArgumentException('La solicitud no es válida.');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:00(?:Z|[+-]\d{2}:\d{2})$/', $requestedStart)) {
        throw new InvalidArgumentException('Elegí uno de los horarios publicados.');
    }

    try {
        $date = new DateTimeImmutable($requestedStart);
    } catch (Exception) {
        throw new InvalidArgumentException('El horario seleccionado no es válido.');
    }

    $zone = new DateTimeZone(APP_TIMEZONE);
    $local = $date->setTimezone($zone);
    $now = new DateTimeImmutable('now', $zone);
    if ($local <= $now || $local->format('s') !== '00') {
        throw new InvalidArgumentException('Elegí un horario futuro disponible.');
    }

    $pdo = database();
    $pdo->exec('BEGIN IMMEDIATE TRANSACTION');

    $submission = $pdo->prepare('SELECT id FROM watch_submissions WHERE id = ? AND public_token = ?');
    $submission->execute([$submissionId, $token]);
    if (!$submission->fetch()) {
        throw new InvalidArgumentException('No encontramos la solicitud.');
    }

    $existing = $pdo->prepare('SELECT booking_code FROM appointments WHERE submission_id = ?');
    $existing->execute([$submissionId]);
    if ($existing->fetch()) {
        throw new InvalidArgumentException('Esta solicitud ya tiene una cita registrada.');
    }

    $ruleStatement = $pdo->prepare('SELECT * FROM availability_rules WHERE weekday = ? AND enabled = 1');
    $ruleStatement->execute([(int)$local->format('N')]);
    $rule = $ruleStatement->fetch();
    if (!$rule) {
        throw new InvalidArgumentException('Ese horario no está disponible.');
    }

    [$startHour, $startMinute] = array_map('intval', explode(':', (string)$rule['start_time']));
    [$endHour, $endMinute] = array_map('intval', explode(':', (string)$rule['end_time']));
    $startMinutes = $startHour * 60 + $startMinute;
    $endMinutes = $endHour * 60 + $endMinute;
    $requestedMinutes = (int)$local->format('H') * 60 + (int)$local->format('i');
    $slotMinutes = (int)$rule['slot_minutes'];
    $isPublishedSlot = $requestedMinutes >= $startMinutes
        && $requestedMinutes < $endMinutes
        && ($requestedMinutes - $startMinutes) % $slotMinutes === 0;
    if (!$isPublishedSlot) {
        throw new InvalidArgumentException('Ese horario no está disponible.');
    }

    $iso = $local->format(DateTimeInterface::ATOM);
    $conflict = $pdo->prepare(
        "SELECT 1 FROM blocked_slots WHERE starts_at = ?
         UNION
         SELECT 1 FROM appointments WHERE starts_at = ? AND status IN ('solicitada', 'confirmada')"
    );
    $conflict->execute([$iso, $iso]);
    if ($conflict->fetch()) {
        throw new InvalidArgumentException('Ese horario acaba de ocuparse. Elegí otro.');
    }

    $code = 'EGR-' . $local->format('Y') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    $nowIso = nowIso();
    $pdo->prepare(
        'INSERT INTO appointments(submission_id, booking_code, starts_at, status, created_at, updated_at)
         VALUES(?, ?, ?, ?, ?, ?)'
    )->execute([$submissionId, $code, $iso, 'solicitada', $nowIso, $nowIso]);
    $pdo->prepare(
        'INSERT INTO submission_history(submission_id, event_type, detail, created_at) VALUES(?, ?, ?, ?)'
    )->execute([
        $submissionId,
        'cita_solicitada',
        'Cita solicitada para ' . $local->format('d/m/Y H:i') . '.',
        $nowIso,
    ]);
    $pdo->commit();

    jsonResponse(201, [
        'booking' => ['code' => $code, 'starts_at' => $iso, 'status' => 'solicitada'],
        'timezone' => APP_TIMEZONE,
    ]);
} catch (InvalidArgumentException $exception) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    jsonResponse(409, ['error' => $exception->getMessage(), 'code' => 'slot_unavailable']);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('[Booking] ' . $exception->getMessage());
    jsonResponse(500, ['error' => 'No se guardó la cita. Probá nuevamente.', 'code' => 'server']);
}
