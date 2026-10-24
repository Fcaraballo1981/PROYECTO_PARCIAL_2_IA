<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
requireMethod('POST');
rateLimit('purchase', 8, 3600);
releaseSession();

$catalog = [
    'GR-01' => ['name' => 'Rolex Submariner 16618', 'price' => 32250],
    'GR-02' => ['name' => 'Omega Seamaster 300M Chronograph', 'price' => 7450],
    'GR-03' => ['name' => 'Cartier Santos', 'price' => 11125],
    'GR-04' => ['name' => 'TAG Heuer Monaco', 'price' => 9125],
    'GR-05' => ['name' => 'Seiko SKX007', 'price' => 725],
    'GR-06' => ['name' => 'Seiko 5 Sports GMT SSK003', 'price' => 1050],
    'GR-07' => ['name' => 'Citizen Aqualand Promaster JP1010', 'price' => 675],
    'GR-08' => ['name' => 'Citizen Eco-Drive CB0021', 'price' => 825],
    'GR-09' => ['name' => 'Orient Bambino ER24005W', 'price' => 600],
    'GR-10' => ['name' => 'Casio F-91W', 'price' => 75],
    'GR-11' => ['name' => 'Casio G-Shock DW-5600E', 'price' => 225],
];

try {
    $data = requestJson();
    if (trim((string)($data['website'] ?? '')) !== '') jsonResponse(400, ['error' => 'No pudimos validar la solicitud.', 'code' => 'spam']);
    if (($data['consent'] ?? false) !== true) throw new InvalidArgumentException('Necesitamos tu consentimiento para guardar la solicitud.');
    $productId = trim((string)($data['product_id'] ?? ''));
    if (!isset($catalog[$productId])) throw new InvalidArgumentException('El reloj seleccionado no está disponible en el catálogo.');
    $email = filter_var(trim((string)($data['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    if (!$email) throw new InvalidArgumentException('Ingresá un correo electrónico válido.');
    $contact = trim((string)($data['preferred_contact'] ?? ''));
    if (!in_array($contact, ['email', 'telefono', 'whatsapp'], true)) throw new InvalidArgumentException('Seleccioná un medio de contacto válido.');
    $name = cleanText($data['full_name'] ?? '', 120, true);
    $phone = cleanText($data['phone'] ?? '', 40, true);
    $product = $catalog[$productId];
    $code = 'COMPRA-' . strtoupper(substr(randomToken(5), 0, 8));
    $now = nowIso();
    $stmt = database()->prepare('INSERT INTO purchase_inquiries(public_code,product_id,product_name,listed_price,full_name,email,phone,preferred_contact,consent_at,created_at) VALUES(?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute([$code, $productId, $product['name'], $product['price'], $name, $email, $phone, $contact, $now, $now]);
    jsonResponse(201, ['inquiry' => ['code' => $code, 'product' => $product['name']]]);
} catch (InvalidArgumentException $e) {
    jsonResponse(422, ['error' => $e->getMessage(), 'code' => 'validation']);
} catch (Throwable $e) {
    error_log('[Purchase inquiry] ' . $e->getMessage());
    jsonResponse(500, ['error' => 'No pudimos guardar la solicitud. Probá nuevamente.', 'code' => 'server']);
}
