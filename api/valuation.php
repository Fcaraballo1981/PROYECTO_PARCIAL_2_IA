<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

@set_time_limit(180);
requireMethod('POST');
rateLimit('valuation', 4, 3600);
releaseSession();

try {
    if (trim((string)($_POST['website'] ?? '')) !== '') {
        jsonResponse(400, ['error' => 'No pudimos validar la solicitud.', 'code' => 'spam']);
    }
    if (($_POST['consent'] ?? '') !== 'yes') {
        throw new InvalidArgumentException('Necesitamos tu consentimiento para tratar los datos y las fotografías.');
    }

    $email = filter_var(trim((string)($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    if (!$email) throw new InvalidArgumentException('Ingresá un correo electrónico válido.');

    $movement = (string)($_POST['movement'] ?? '');
    if (!in_array($movement, ['automatico', 'manual', 'cuarzo', 'desconocido'], true)) {
        throw new InvalidArgumentException('Seleccioná un tipo de movimiento válido.');
    }

    $runningStatus = (string)($_POST['running_status'] ?? '');
    $validRunningStatuses = [
        'Funciona correctamente',
        'Funciona, pero presenta fallas',
        'No funciona',
        'No pude comprobarlo',
    ];
    if (!in_array($runningStatus, $validRunningStatuses, true)) {
        throw new InvalidArgumentException('Seleccioná un estado de funcionamiento válido.');
    }

    $contact = (string)($_POST['preferred_contact'] ?? '');
    if (!in_array($contact, ['email', 'telefono', 'whatsapp'], true)) {
        throw new InvalidArgumentException('Seleccioná un medio de contacto válido.');
    }

    $year = trim((string)($_POST['approximate_year'] ?? ''));
    $yearValue = $year === '' ? null : filter_var($year, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1800, 'max_range' => (int)date('Y')],
    ]);
    if ($year !== '' && $yearValue === false) {
        throw new InvalidArgumentException('Revisá el año aproximado del reloj.');
    }

    $askingPrice = trim((string)($_POST['asking_price'] ?? ''));
    $askingPriceValue = $askingPrice === '' ? null : filter_var($askingPrice, FILTER_VALIDATE_FLOAT);
    if ($askingPrice !== '' && ($askingPriceValue === false || $askingPriceValue < 0)) {
        throw new InvalidArgumentException('Revisá el precio pretendido.');
    }

    $validFiles = validateValuationImages(normalizedFiles('photos'));
    $pdo = database();
    $now = nowIso();
    $token = randomToken();
    $pdo->beginTransaction();

    $existingId = filter_var($_POST['submission_id'] ?? null, FILTER_VALIDATE_INT);
    $existingToken = (string)($_POST['submission_token'] ?? '');

    if ($existingId && isPublicToken($existingToken)) {
        $lookup = $pdo->prepare('SELECT id, customer_id FROM watch_submissions WHERE id = ? AND public_token = ?');
        $lookup->execute([$existingId, $existingToken]);
        $existing = $lookup->fetch();
        if (!$existing) throw new InvalidArgumentException('No pudimos verificar la solicitud anterior.');

        $submissionId = (int)$existing['id'];
        $customerId = (int)$existing['customer_id'];
        $token = $existingToken;
        $pdo->prepare(
            'UPDATE customers SET full_name = ?, email = ?, phone = ?, preferred_contact = ?, consent_at = ? WHERE id = ?'
        )->execute([
            cleanText($_POST['full_name'] ?? '', 120, true),
            $email,
            cleanText($_POST['phone'] ?? '', 40, true),
            $contact,
            $now,
            $customerId,
        ]);
        $pdo->prepare(
            'UPDATE watch_submissions SET brand = ?, model = ?, reference = ?, approximate_year = ?, movement = ?, condition_summary = ?, running_status = ?, has_box = ?, has_papers = ?, has_accessories = ?, asking_price = ?, notes = ?, updated_at = ? WHERE id = ?'
        )->execute(watchSubmissionValues($movement, $runningStatus, $yearValue, $askingPriceValue, $now, $submissionId));
        $pdo->prepare('DELETE FROM watch_images WHERE submission_id = ?')->execute([$submissionId]);
        $event = 'actualizada';
        $detail = 'El cliente corrigió la información antes de reservar.';
    } else {
        $customerId = insertAndGetId(
            'INSERT INTO customers(full_name, email, phone, preferred_contact, consent_at, created_at) VALUES(?, ?, ?, ?, ?, ?)',
            [
            cleanText($_POST['full_name'] ?? '', 120, true),
            $email,
            cleanText($_POST['phone'] ?? '', 40, true),
            $contact,
            $now,
            $now,
            ]
        );
        $submissionId = insertAndGetId(
            'INSERT INTO watch_submissions(public_token, customer_id, brand, model, reference, approximate_year, movement, condition_summary, running_status, has_box, has_papers, has_accessories, asking_price, notes, created_at, updated_at)
             VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            array_merge(
                [$token, $customerId],
                watchSubmissionValues($movement, $runningStatus, $yearValue, $askingPriceValue, $now),
                [$now]
            )
        );
        $event = 'creada';
        $detail = 'Solicitud recibida desde la web.';
    }

    $postgresImages = databaseDriver() === 'pgsql';
    $imageStatement = $pdo->prepare($postgresImages
        ? "INSERT INTO watch_images(submission_id, original_name, mime_type, byte_size, image_data, created_at) VALUES(?, ?, ?, ?, decode(?, 'base64'), ?)"
        : 'INSERT INTO watch_images(submission_id, original_name, mime_type, byte_size, image_data, created_at) VALUES(?, ?, ?, ?, ?, ?)'
    );
    foreach ($validFiles as $file) {
        $imageStatement->bindValue(1, $submissionId, PDO::PARAM_INT);
        $imageStatement->bindValue(2, $file['name']);
        $imageStatement->bindValue(3, $file['mime']);
        $imageStatement->bindValue(4, $file['size'], PDO::PARAM_INT);
        $imageStatement->bindValue(5, $postgresImages ? base64_encode($file['data']) : $file['data'], $postgresImages ? PDO::PARAM_STR : PDO::PARAM_LOB);
        $imageStatement->bindValue(6, $now);
        $imageStatement->execute();
    }
    $pdo->prepare(
        'INSERT INTO submission_history(submission_id, event_type, detail, created_at) VALUES(?, ?, ?, ?)'
    )->execute([$submissionId, $event, $detail, $now]);
    $pdo->commit();

    try {
        $valuation = analyseWatch($_POST, $validFiles);
        $pdo->prepare(
            'INSERT INTO valuations(submission_id, provider, model, status, result_json, created_at) VALUES(?, ?, ?, ?, ?, ?)'
        )->execute([
            $submissionId,
            'Google Gemini',
            $valuation['model'],
            $valuation['status'],
            json_encode($valuation['result'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            nowIso(),
        ]);
    } catch (Throwable $analysisException) {
        error_log('[Watch valuation result] ' . $analysisException->getMessage());
        $valuation = unavailableValuation();
    }

    jsonResponse(201, [
        'submission' => ['id' => $submissionId, 'token' => $token],
        'valuation' => $valuation,
    ]);
} catch (InvalidArgumentException $exception) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    jsonResponse(422, ['error' => $exception->getMessage(), 'code' => 'validation']);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('[Watch valuation] ' . $exception->getMessage());
    jsonResponse(500, ['error' => 'No pudimos guardar la solicitud. No se creó ninguna reserva.', 'code' => 'server']);
}

function validateValuationImages(array $files): array
{
    if (count($files) < 1 || count($files) > MAX_IMAGES) {
        throw new InvalidArgumentException('Subí entre 1 y ' . MAX_IMAGES . ' fotografías.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $validFiles = [];
    $totalBytes = 0;
    foreach ($files as $file) {
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            throw new InvalidArgumentException('Una fotografía no pudo cargarse. Probá nuevamente.');
        }
        $size = (int)$file['size'];
        $totalBytes += $size;
        if ($size < 1 || $size > MAX_IMAGE_BYTES || $totalBytes > MAX_TOTAL_IMAGE_BYTES) {
            throw new InvalidArgumentException('Cada foto y el conjunto pueden pesar hasta 3,2 MB.');
        }
        $mime = $finfo->file($file['tmp_name']);
        if (!in_array($mime, ['image/jpeg', 'image/png'], true) || @getimagesize($file['tmp_name']) === false) {
            throw new InvalidArgumentException('Usá imágenes JPG o PNG válidas.');
        }
        $contents = file_get_contents($file['tmp_name']);
        if (!is_string($contents)) throw new InvalidArgumentException('No pudimos leer una de las fotografías.');
        $validFiles[] = [
            'name' => cleanText(basename((string)$file['name']), 180),
            'mime' => $mime,
            'size' => $size,
            'data' => $contents,
        ];
    }
    return $validFiles;
}

function watchSubmissionValues(
    string $movement,
    string $runningStatus,
    int|false|null $year,
    float|false|null $askingPrice,
    string $now,
    ?int $submissionId = null
): array {
    $values = [
        cleanText($_POST['brand'] ?? '', 80, true),
        cleanText($_POST['model'] ?? '', 100, true),
        cleanText($_POST['reference'] ?? '', 100),
        $year,
        $movement,
        cleanText($_POST['condition_summary'] ?? '', 500, true),
        $runningStatus,
        isset($_POST['has_box']) ? 1 : 0,
        isset($_POST['has_papers']) ? 1 : 0,
        isset($_POST['has_accessories']) ? 1 : 0,
        $askingPrice,
        cleanText($_POST['notes'] ?? '', 1500),
        $now,
    ];
    if ($submissionId !== null) $values[] = $submissionId;
    return $values;
}

function analyseWatch(array $fields, array $files): array
{
    $model = trim((string)(getenv('GEMINI_MODEL') ?: 'gemini-3.5-flash-lite'));
    $apiKey = trim((string)getenv('GEMINI_API_KEY'));
    $status = 'not_configured';
    $result = [
        'summary' => 'La solicitud quedó guardada, pero el análisis visual de Gemini todavía no está configurado.',
        'identification' => null,
        'factors' => [],
        'missingInformation' => ['Configurar GEMINI_API_KEY en el servidor para analizar las fotografías.'],
        'warnings' => [],
    ];

    if ($apiKey !== '') {
        $visual = requestVisualAnalysis($fields, $files, $apiKey, $model);
        if ($visual !== null) {
            $status = 'completed';
            $result = $visual;
        } else {
            $status = 'unavailable';
            $result['summary'] = 'La solicitud quedó guardada, pero el análisis visual no estuvo disponible. La revisará nuestro equipo.';
            $result['missingInformation'] = [];
        }
    }

    return ['status' => $status, 'model' => $model, 'result' => $result];
}

function unavailableValuation(): array
{
    return [
        'status' => 'unavailable',
        'model' => trim((string)(getenv('GEMINI_MODEL') ?: 'gemini-3.5-flash-lite')),
        'result' => [
            'summary' => 'La solicitud quedó guardada, pero el análisis visual no estuvo disponible. La revisará nuestro equipo.',
            'identification' => null,
            'factors' => [],
            'missingInformation' => [],
            'warnings' => [],
        ],
    ];
}

function requestVisualAnalysis(array $fields, array $files, string $apiKey, string $model): ?array
{
    $facts = [
        'marca_declarada' => $fields['brand'] ?? '',
        'modelo_declarado' => $fields['model'] ?? '',
        'referencia' => $fields['reference'] ?? '',
        'año' => $fields['approximate_year'] ?? '',
        'movimiento' => $fields['movement'] ?? '',
        'estado' => $fields['condition_summary'] ?? '',
        'funcionamiento' => $fields['running_status'] ?? '',
        'caja' => isset($fields['has_box']),
        'documentación' => isset($fields['has_papers']),
        'accesorios' => isset($fields['has_accessories']),
        'observaciones' => $fields['notes'] ?? '',
    ];
    $parts = [[
        'text' => "Analizá esta solicitud. Datos declarados:\n" .
            json_encode($facts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
    ]];
    foreach ($files as $file) {
        $parts[] = [
            'inlineData' => [
                'mimeType' => $file['mime'],
                'data' => base64_encode($file['data']),
            ],
        ];
    }

    $instruction = <<<'PROMPT'
Sos un asistente de preevaluación de El Gabinete del Relojero. Contrastá los datos declarados con lo visible en las fotos. No afirmes autenticidad ni falsificación: describí sólo indicios o inconsistencias. No inventes referencia, características, precio ni fuentes. Esta etapa es exclusivamente visual y no estima valores. Indicá qué fotos o datos faltan y recomendá revisión presencial. Devolvé exclusivamente el JSON solicitado, sin markdown.
PROMPT;
    $schema = [
        'type' => 'object',
        'properties' => [
            'summary' => ['type' => 'string'],
            'identification' => ['type' => 'string', 'description' => 'Identificación posible o una cadena vacía si no hay evidencia suficiente.'],
            'factors' => ['type' => 'array', 'items' => ['type' => 'string']],
            'missingInformation' => ['type' => 'array', 'items' => ['type' => 'string']],
            'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
        'required' => ['summary', 'identification', 'factors', 'missingInformation', 'warnings'],
    ];
    $body = [
        'systemInstruction' => [
            'parts' => [['text' => $instruction]],
        ],
        'contents' => [[
            'role' => 'user',
            'parts' => $parts,
        ]],
        'generationConfig' => [
            'responseMimeType' => 'application/json',
            'responseSchema' => $schema,
        ],
    ];
    $response = geminiVisualRequest($model, $body, $apiKey);
    if ($response === null) return null;

    $text = generateContentOutputText($response);
    $result = json_decode($text, true);
    if (!is_array($result)) return null;
    if (($result['identification'] ?? '') === '') $result['identification'] = null;
    return $result;
}

function geminiVisualRequest(string $model, array $body, string $apiKey): ?array
{
    $model = preg_replace('#^models/#', '', trim($model));
    if (!is_string($model) || !preg_match('/^[A-Za-z0-9._-]+$/', $model)) {
        error_log('[Gemini visual] GEMINI_MODEL no es válido.');
        return null;
    }
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent';
    $handle = curl_init($url);
    curl_setopt_array($handle, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 90,
        CURLOPT_CAINFO => __DIR__ . '/cacert.pem',
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'x-goog-api-key: ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
    $raw = curl_exec($handle);
    $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $curlError = curl_error($handle);
    curl_close($handle);

    if (!is_string($raw) || $status < 200 || $status >= 300) {
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        $message = is_array($decoded) ? (string)($decoded['error']['message'] ?? '') : '';
        error_log('[Gemini visual] HTTP ' . $status . ($curlError !== '' ? '; ' . $curlError : '') . ($message !== '' ? '; ' . $message : ''));
        return null;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

function generateContentOutputText(array $response): string
{
    $text = '';
    $candidates = $response['candidates'] ?? [];
    if (is_array($candidates)) {
        foreach ($candidates as $candidate) {
            $parts = is_array($candidate) ? ($candidate['content']['parts'] ?? []) : [];
            if (!is_array($parts)) continue;
            $candidateText = implode('', array_map(
                static fn($part) => is_array($part) ? (string)($part['text'] ?? '') : '',
                $parts
            ));
            if (trim($candidateText) !== '') {
                $text = trim($candidateText);
                break;
            }
        }
    }
    return $text;
}
