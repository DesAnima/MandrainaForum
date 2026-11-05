<?php
// api/v1/duplicadas.php  (solo administrador)
//   GET    /api/v1/duplicadas.php   cuenta las publicaciones duplicadas (no borra)
//   DELETE /api/v1/duplicadas.php   elimina las copias; se conserva la más antigua
//
// Duplicada = mismo usuario, mismo título y contenido, creada hasta 10 minutos
// después de la original (ver Publicacion::buscarDuplicadas).

require_once __DIR__ . '/../../includes/session.php';
mandraina_session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../../controllers/PublicacionController.php';

set_exception_handler(function (Throwable $e) {
    error_log('[api] ' . get_class($e) . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')');
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor.', 'detalle' => $e->getMessage()]);
    exit;
});

$controller = new PublicacionController();

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        $controller->duplicadas();
        break;

    case 'DELETE':
        $controller->eliminarDuplicadas();
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido.']);
}
