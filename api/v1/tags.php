<?php
// api/v1/tags.php
// GET /api/v1/tags.php
// Tags en los que participa el usuario con sesión iniciada (publicó o
// respondió en alguna publicación con ese tag), con el total de
// publicaciones que tiene cada tag.

require_once __DIR__ . '/../../includes/session.php';
mandraina_session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../controllers/PublicacionController.php';

set_exception_handler(function (Throwable $e) {
    error_log('[api] ' . get_class($e) . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')');
    http_response_code(500);
    echo json_encode([
        'error' => 'Error interno del servidor.',
        'detalle' => $e->getMessage(),
    ]);
    exit;
});

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.']);
    exit;
}

(new PublicacionController())->misTags();
