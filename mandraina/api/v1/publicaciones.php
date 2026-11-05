<?php
// api/v1/publicaciones.php
// Endpoint REST: GET/POST /api/v1/publicaciones.php
//                GET/PUT/DELETE /api/v1/publicaciones.php?id={id}

require_once __DIR__ . '/../../includes/session.php';
mandraina_session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../controllers/PublicacionController.php';

// --- Middleware centralizado de errores ---
// Cualquier excepción no controlada en el resto del script termina acá
// como un 500 en JSON, en vez de un error crudo de PHP.
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
    $controller = new PublicacionController();
    $method = $_SERVER['REQUEST_METHOD'];
    $id = isset($_GET['id']) ? (int) $_GET['id'] : null;

    switch ($method) {
        case 'GET':
            if ($id !== null) {
                $controller->show($id);
            } else {
                $controller->index();
            }
            break;

        case 'POST':
            $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $controller->store($body);
            break;

        case 'PUT':
            if ($id === null) {
                http_response_code(400);
                echo json_encode(['error' => 'Falta el parámetro "id" en la URL.']);
                exit;
            }
            $body = json_decode(file_get_contents('php://input'), true) ?? [];
            $controller->update($id, $body);
            break;

        case 'DELETE':
            if ($id === null) {
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
