<?php
// api/v1/reacciones.php
//   GET  /api/v1/reacciones.php   reacciones del usuario de la sesión
//   POST /api/v1/reacciones.php   body: {publicacion_id, tipo: "like"|"dislike"}
//
// Una sola reacción por usuario y publicación (like o dislike, nunca las dos):
// se puede cambiar de una a otra. No se puede reaccionar a la publicación propia.

require_once __DIR__ . '/../../includes/session.php';
mandraina_session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../../controllers/ReaccionController.php';

set_exception_handler(function (Throwable $e) {
    error_log('[api] ' . get_class($e) . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')');
    http_response_code(500);
    echo json_encode([
        'error' => 'Error interno del servidor.',
        'detalle' => $e->getMessage(),
    ]);
    exit;
});

$controller = new ReaccionController();

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        $controller->mias();
        break;

    case 'POST':
        $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $controller->store(is_array($body) ? $body : []);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido.']);
}
