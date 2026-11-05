<?php
// api/v1/tags.php
// GET /api/v1/tags.php
// Tags en los que participa el usuario con sesión iniciada (publicó o
// respondió en alguna publicación con ese tag), con el total de
// publicaciones que tiene cada tag.
//
// GET /api/v1/tags.php?alcance=general
// Tags fijados por el administrador y, después, los más usados por todos
// (hasta 15): {"tags": [{tag, total, fijado}], "total_tags": N}.
//
// GET /api/v1/tags.php?alcance=todos
// Todos los tags, con el mismo formato.
//
// PUT    /api/v1/tags.php          body {"tag": "musica"}  fija un tag (solo admin)
// DELETE /api/v1/tags.php?tag=musica                       lo deja de fijar (solo admin)

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

$controller = new PublicacionController();

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        $alcance = $_GET['alcance'] ?? '';
        if ($alcance === 'general' || $alcance === 'todos') {
            $controller->tagsGenerales($alcance === 'todos');
        } else {
            $controller->misTags();
        }
        break;

    case 'PUT':
        $body = json_decode(file_get_contents('php://input'), true);
        $controller->fijarTag(is_array($body) ? $body : []);
        break;

    case 'DELETE':
        $controller->desfijarTag($_GET['tag'] ?? '');
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido.']);
}
