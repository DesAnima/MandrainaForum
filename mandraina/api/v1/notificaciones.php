<?php
// api/v1/notificaciones.php
//   GET  /api/v1/notificaciones.php   -> {"no_leidas": n, "items": [...]}
//        Avisos de respuestas a las publicaciones del usuario de la sesión.
//   POST /api/v1/notificaciones.php   body: {} o {"id": 5}
//        Marca como leídas todas (sin "id") o solo la indicada.

require_once __DIR__ . '/../../includes/session.php';
mandraina_session_start();
$userId = (int) ($_SESSION['user_id'] ?? 0);
session_write_close(); // se consulta seguido: se suelta la sesión enseguida

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

set_exception_handler(function (Throwable $e) {
    error_log('[api] ' . get_class($e) . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')');
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor.']);
    exit;
});

if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado. Iniciá sesión para continuar.']);
    exit;
}

require_once __DIR__ . '/../../models/Publicacion.php'; // asegura publicaciones y respuestas
require_once __DIR__ . '/../../models/Notificacion.php';

new Publicacion();
$model = new Notificacion();

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        echo json_encode([
            'no_leidas' => $model->contarNoLeidas($userId),
            'items' => $model->listar($userId),
        ]);
        break;

    case 'POST':
        $body = json_decode(file_get_contents('php://input'), true);
        $id = is_array($body) && isset($body['id']) ? (int) $body['id'] : null;
        $model->marcarLeidas($userId, $id !== null && $id > 0 ? $id : null);
        echo json_encode(['ok' => true]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido.']);
}
