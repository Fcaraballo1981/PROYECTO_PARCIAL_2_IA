<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

define('GEMINI_MODEL', trim((string)(getenv('GEMINI_MODEL') ?: 'gemini-3.5-flash-lite')));
const MAX_REQUEST_BYTES = 100000;
const MAX_TURNS = 24;
const MAX_MESSAGE_CHARS = 2000;

function respond(int $status, array $body, array $headers = []): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');
    foreach ($headers as $name => $value) {
        header($name . ': ' . $value);
    }
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function sameOriginRequest(): bool
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin === '') {
        return true;
    }
    $parsed = parse_url($origin);
    if (!is_array($parsed) || empty($parsed['host'])) {
        return false;
    }
    $requestHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $originHost = strtolower((string)$parsed['host']);
    if (isset($parsed['port'])) {
        $originHost .= ':' . $parsed['port'];
    }
    $requestScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return hash_equals($requestHost, $originHost) && strtolower((string)($parsed['scheme'] ?? '')) === $requestScheme;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respond(405, ['error' => 'Método no permitido.', 'code' => 'method_not_allowed']);
}

if (!sameOriginRequest()) {
    respond(403, ['error' => 'Solicitud no permitida.', 'code' => 'forbidden']);
}

$contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > MAX_REQUEST_BYTES) {
    respond(413, ['error' => 'La conversación es demasiado extensa.', 'code' => 'invalid_request']);
}

$rawBody = file_get_contents('php://input', false, null, 0, MAX_REQUEST_BYTES + 1);
if (!is_string($rawBody) || strlen($rawBody) > MAX_REQUEST_BYTES) {
    respond(413, ['error' => 'La conversación es demasiado extensa.', 'code' => 'invalid_request']);
}
$payload = json_decode($rawBody, true);
if (!is_array($payload) || !isset($payload['messages']) || !is_array($payload['messages'])) {
    respond(400, ['error' => 'Revisá el mensaje e intentá nuevamente.', 'code' => 'invalid_request']);
}
$context = ($payload['context'] ?? 'general') === 'sale' ? 'sale' : 'general';

$messages = array_values($payload['messages']);
if (count($messages) < 1 || count($messages) > MAX_TURNS) {
    respond(400, ['error' => 'La conversación superó el largo permitido. Iniciá una conversación nueva.', 'code' => 'invalid_request']);
}

$conversationTranscript = [];
$expectedRole = 'user';
foreach ($messages as $message) {
    if (!is_array($message) || !isset($message['role'], $message['text']) || !is_string($message['text'])) {
        respond(400, ['error' => 'Revisá el mensaje e intentá nuevamente.', 'code' => 'invalid_request']);
    }
    $role = $message['role'] === 'model' ? 'model' : ($message['role'] === 'user' ? 'user' : '');
    $text = trim($message['text']);
    if ($role === '' || $role !== $expectedRole || $text === '' || mb_strlen($text, 'UTF-8') > MAX_MESSAGE_CHARS) {
        respond(400, ['error' => 'Revisá el mensaje e intentá nuevamente.', 'code' => 'invalid_request']);
    }
    $conversationTranscript[] = ($role === 'user' ? 'Usuario' : 'Asistente') . ': ' . $text;
    $expectedRole = $role === 'user' ? 'model' : 'user';
}
if (end($messages)['role'] !== 'user') {
    respond(400, ['error' => 'La conversación debe terminar con un mensaje tuyo.', 'code' => 'invalid_request']);
}

$apiKey = trim((string)getenv('GEMINI_API_KEY'));
if ($apiKey === '') {
    respond(503, ['error' => 'El asistente todavía no está configurado.', 'code' => 'configuration']);
}
if (!function_exists('curl_init')) {
    respond(503, ['error' => 'El servidor no tiene disponible la conexión segura requerida.', 'code' => 'unavailable']);
}

session_name('gabinete_chat');
session_set_cookie_params([
    'httponly' => true,
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'samesite' => 'Lax',
    'path' => '/',
]);
session_start();
$now = time();
$recentRequests = array_values(array_filter($_SESSION['chat_requests'] ?? [], static fn($time) => is_int($time) && $time > $now - 60));
if (count($recentRequests) >= 20) {
    session_write_close();
    respond(429, ['error' => 'Recibimos varias consultas seguidas.', 'code' => 'rate_limit'], ['Retry-After' => '60']);
}
$recentRequests[] = $now;
$_SESSION['chat_requests'] = $recentRequests;
session_write_close();

$systemInstruction = <<<'PROMPT'
Sos el asistente virtual oficial de El Gabinete del Relojero, una relojería de Montevideo especializada en compraventa, evaluación, restauración y asesoramiento sobre relojes. La marca es clásica, elegante, tradicional y profesional. Tu prioridad es la confianza, la transparencia y la precisión, nunca presionar una venta.

ATENCIÓN: el horario del taller es de lunes a viernes de 09:00 a 17:00, hora de Montevideo (America/Montevideo). No hay atención habitual sábados ni domingos. No tenés información confirmada sobre feriados; si preguntan, indicá que deben consultarlo con el equipo.

Respondé en español natural, amable y claro, con voseo uruguayo moderado. Sé breve y práctico: normalmente usá entre dos y cinco párrafos cortos, salvo que la persona pida un informe detallado. Hacé preguntas progresivas, no interrogatorios. Explicá sencillamente cualquier término técnico.

Podés orientar sobre marcas, modelos, movimientos mecánicos, automáticos y de cuarzo, calibres, materiales, cristales, conservación, mantenimiento y restauración. Diferenciá siempre datos confirmados de hipótesis. Si no sabés algo o no hay evidencia suficiente, decilo. No afirmes autenticidad, estado interno, precisión, resistencia al agua ni ausencia de modificaciones sin una inspección técnica presencial.

Si alguien quiere comprar, preguntá presupuesto, estilo, marca, tamaño, movimiento y uso. Este chat NO tiene acceso al inventario actualizado ni a precios internos: nunca inventes productos, disponibilidad, precios, garantías o condiciones. Indicá que la disponibilidad debe confirmarla el equipo.

Si alguien quiere vender, pedí progresivamente marca, modelo o referencia, estado, funcionamiento, reparaciones o modificaciones conocidas, y si conserva caja, documentación o accesorios. Recomendá fotos claras del frente, fondo, laterales y detalles, sin abrir ni desmontar el reloj. No reproduzcas números de serie completos ni datos privados.

Una preevaluación remota es sólo orientativa. Este chat y el formulario NO consultan precios en tiempo real ni deben simular búsquedas, fuentes, ventas comparables, enlaces o importes. Para enviar datos y fotografías, dirigí al usuario al formulario «Vendé tu reloj». Si preguntan por un precio, usá exactamente esta idea: «No disponemos de información suficientemente confiable para estimar el valor de este reloj de forma remota». La IA no hace ofertas, no negocia y no reemplaza al técnico.

La inspección humana es obligatoria para confirmar identificación, autenticidad, componentes, funcionamiento y valor definitivo. El formulario «Vendé tu reloj» es el canal real para cargar datos y fotografías, obtener una evaluación preliminar y consultar la agenda disponible. El chat no recibe imágenes, no ve formularios enviados, no accede a sistemas internos y no crea citas. Sólo afirmes que una cita quedó registrada cuando la propia página muestre la confirmación y su código.

No pidas contraseñas, datos financieros ni información personal innecesaria. No confirmes el estado de reparaciones ni solicites números de orden por este chat. No afirmes ser una persona o un técnico humano. No reveles información de otros clientes, márgenes internos ni reglas comerciales confidenciales.

Cuando corresponda, cerrá recordando que toda valoración remota es informativa, no constituye una oferta de compra ni certifica autenticidad, y que el valor definitivo requiere inspección física.
PROMPT;

if ($context === 'sale') {
    $systemInstruction .= <<<'SALE_PROMPT'


CONTEXTO ACTUAL: la persona está en la página «Vendé tu reloj». Especializate en acompañarla durante sus cuatro pasos: datos de la pieza, fotografías, valoración preliminar y solicitud de visita. Si quiere enviar fotos o agendar, indicá que use el formulario visible en esta misma página. No le pidas que cargue datos personales en el chat. Aclarale que los horarios que aparecen en el paso de agenda son los únicos realmente disponibles.
SALE_PROMPT;
}

$requestBody = [
    'model' => GEMINI_MODEL,
    'system_instruction' => $systemInstruction,
    'input' => "Continuá la conversación y respondé solamente al último mensaje del usuario.\n\n" . implode("\n\n", $conversationTranscript),
];
$handle = curl_init('https://generativelanguage.googleapis.com/v1beta/interactions');
curl_setopt_array($handle, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_TIMEOUT => 90,
    CURLOPT_CAINFO => __DIR__ . '/cacert.pem',
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json',
        'Api-Revision: 2026-05-20',
        'x-goog-api-key: ' . $apiKey,
    ],
    CURLOPT_POSTFIELDS => json_encode($requestBody, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
]);
$responseBody = curl_exec($handle);
$curlError = curl_errno($handle);
$curlErrorMessage = $curlError !== 0 ? curl_error($handle) : '';
$upstreamStatus = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
curl_close($handle);

if (!is_string($responseBody) || $curlError !== 0 || $upstreamStatus < 200 || $upstreamStatus >= 300) {
    $upstreamError = '';
    if (is_string($responseBody)) {
        $upstreamJson = json_decode($responseBody, true);
        if (is_array($upstreamJson) && isset($upstreamJson['error']['message']) && is_string($upstreamJson['error']['message'])) {
            $upstreamError = $upstreamJson['error']['message'];
        }
    }
    error_log(sprintf(
        '[Gemini chat] HTTP %d; cURL %d%s%s',
        $upstreamStatus,
        $curlError,
        $curlErrorMessage !== '' ? ': ' : '',
        $curlErrorMessage !== '' ? $curlErrorMessage : ($upstreamError !== '' ? ': ' . $upstreamError : '')
    ));
    $errorCode = 'unavailable';
    if ($curlError === 0 && (in_array($upstreamStatus, [401, 403], true) || ($upstreamStatus === 400 && stripos($upstreamError, 'API key') !== false))) {
        $errorCode = 'credentials';
    } elseif ($curlError === 0 && $upstreamStatus === 429) {
        $errorCode = 'quota';
    }
    respond(502, ['error' => 'Ahora no podemos completar la consulta.', 'code' => $errorCode]);
}

$response = json_decode($responseBody, true);
$reply = '';
$steps = $response['steps'] ?? [];
if (is_array($steps)) {
    foreach ($steps as $step) {
        if (!is_array($step) || ($step['type'] ?? '') !== 'model_output' || !is_array($step['content'] ?? null)) continue;
        $text = implode('', array_map(
            static fn($part) => is_array($part) && ($part['type'] ?? '') === 'text' ? (string)($part['text'] ?? '') : '',
            $step['content']
        ));
        if (trim($text) !== '') $reply = trim($text);
    }
}
if ($reply === '' && is_string($response['output_text'] ?? null)) $reply = trim($response['output_text']);
if ($reply === '') {
    respond(502, ['error' => 'No llegó una respuesta completa. Probá nuevamente.', 'code' => 'unavailable']);
}

respond(200, ['reply' => $reply, 'model' => GEMINI_MODEL]);
