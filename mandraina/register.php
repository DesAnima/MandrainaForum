<?php
// register.php
// Paso 1 del registro anónimo: recibe solo un nickname (por fetch/JSON
// desde register.html) y crea un registro PENDIENTE. Todavía no crea
// ninguna cuenta ni contraseña visible: eso pasa en claim.php cuando
// se escanea el QR.

require_once __DIR__ . '/models/Usuario.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$nickname = trim($body['nickname'] ?? $_POST['nickname'] ?? '');

if ($nickname === '') {
    http_response_code(400);
    echo json_encode(['error' => 'El nickname es obligatorio.']);
    exit;
}

try {
    $usuario = new Usuario();
    $pendiente = $usuario->crearPendiente($nickname);

    // Armamos la URL absoluta que va codificada en el QR.
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $basePath = rtrim(str_replace('register.php', '', $_SERVER['PHP_SELF']), '/');
    $claimUrl = "{$scheme}://{$host}{$basePath}/claim.php?token=" . urlencode($pendiente['token']);

    echo json_encode([
        'token' => $pendiente['token'],
        'claim_url' => $claimUrl,
        'expires_in' => $pendiente['expires_in'],
    ]);
} catch (InvalidArgumentException $e) {
    http_response_code(409);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    // Se escribe en el log de Apache/PHP (visible con `docker logs`).
    error_log('[register.php] ' . get_class($e) . ': ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno al generar el registro.']);
}
