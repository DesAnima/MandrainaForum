<?php
// api/v1/respuestas.php
// Endpoint REST de respuestas a publicaciones:
//   GET    /api/v1/respuestas.php?publicacion_id={id}   respuestas de una publicación
//   POST   /api/v1/respuestas.php                       body: {publicacion_id, contenido}
//   DELETE /api/v1/respuestas.php?id={id}               elimina una respuesta propia

require_once __DIR__ . '/../../includes/session.php';
mandraina_session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../controllers/RespuestaController.php';

// Cualquier excepción no controlada termina como un 500 en JSON.
set_exception_handler(function (Throwable $e) {
    error_log('[api] ' . get_class($e) . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')');
    http_response_code(500);
    echo json_encode([
        'error' => 'Error interno del servidor.',
        'detalle' => $e->getMessage(),
    ]);
    exit;
});

try {
    $controller = new RespuestaController();
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            $publicacionId = isset($_GET['publicacion_id']) ? (int) $_GET['publicacion_id'] : 0;
            if ($publicacionId <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Falta el parámetro "publicacion_id" en la URL.']);
                exit;
            }
            $controller->index($publicacionId);
            break;

        case 'POST':
            $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $controller->store(is_array($body) ? $body : []);
            break;

        case 'DELETE':
            $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Falta el parámetro "id" en la URL.']);
                exit;
            }
            $controller->destroy($id);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Método no permitido.']);
    }
} catch (Throwable $e) {
    error_log('[api] ' . get_class($e) . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')');
    http_response_code(500);
    echo json_encode([
        'error' => 'Error interno del servidor.',
        'detalle' => $e->getMessage(),
    ]);
}
