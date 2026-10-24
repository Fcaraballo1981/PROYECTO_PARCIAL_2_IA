<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

requireMethod('GET');

$pdo = database();
$zone = new DateTimeZone(APP_TIMEZONE);
$now = new DateTimeImmutable('now', $zone);
$today = new DateTimeImmutable('today', $zone);
$lastDay = $today->modify('+60 days');

$rules = [];
foreach ($pdo->query('SELECT * FROM availability_rules WHERE enabled = 1')->fetchAll() as $row) {
    $rules[(int)$row['weekday']] = $row;
}

$busySlots = [];
$busyQuery = "SELECT starts_at FROM appointments WHERE status IN ('solicitada', 'confirmada')
              UNION
              SELECT starts_at FROM blocked_slots";
foreach ($pdo->query($busyQuery)->fetchAll(PDO::FETCH_COLUMN) as $value) {
    $busySlots[(string)$value] = true;
}

$dayNames = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];
$monthNames = [1 => 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
$days = [];

for ($date = $today; $date <= $lastDay; $date = $date->modify('+1 day')) {
    $weekday = (int)$date->format('N');
    if (!isset($rules[$weekday])) continue;

    $rule = $rules[$weekday];
    $cursor = new DateTimeImmutable($date->format('Y-m-d') . ' ' . $rule['start_time'], $zone);
    $finish = new DateTimeImmutable($date->format('Y-m-d') . ' ' . $rule['end_time'], $zone);
    $slotMinutes = (int)$rule['slot_minutes'];
    $slots = [];

    while ($cursor < $finish) {
        $iso = $cursor->format(DateTimeInterface::ATOM);
        if ($cursor > $now && !isset($busySlots[$iso])) {
            $slots[] = ['value' => $iso, 'label' => $cursor->format('H:i')];
        }
        $cursor = $cursor->modify('+' . $slotMinutes . ' minutes');
    }

    if ($slots) {
        $days[] = [
            'date' => $date->format('Y-m-d'),
            'label' => $dayNames[(int)$date->format('w')] . ' ' . $date->format('j') . ' ' . $monthNames[(int)$date->format('n')],
            'slots' => $slots,
        ];
    }
}

jsonResponse(200, ['timezone' => APP_TIMEZONE, 'days' => $days]);
