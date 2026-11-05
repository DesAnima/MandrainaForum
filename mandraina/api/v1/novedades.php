<?php
// api/v1/novedades.php
// GET /api/v1/novedades.php  ->  {"version": "..."}
//
// Valor opaco que cambia cada vez que se modifica el contenido del foro. El
// panel lo consulta cada pocos segundos y sólo recarga el feed cuando cambia
// (ver includes/TiempoReal.php). Es una consulta barata: un GET a Redis.
// También devuelve "activos": cuántos usuarios tienen el panel abierto ahora
// (ver includes/Presencia.php).

require_once __DIR__ . '/../../includes/session.php';
mandraina_session_start();
$autenticado = !empty($_SESSION['user_id']);
$userId = (int) ($_SESSION['user_id'] ?? 0);
// Se llama cada pocos segundos por pestaña: se suelta la sesión enseguida.
session_write_close();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

set_exception_handler(function (Throwable $e) {
    error_log('[api] ' . get_class($e) . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')');
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor.']);
    exit;
});

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.']);
    exit;
}

if (!$autenticado) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado.']);
    exit;
}

require_once __DIR__ . '/../../includes/TiempoReal.php';

$version = TiempoReal::version();

if ($version === null) {
    // Sin Redis: huella calculada desde PostgreSQL. Cambia si se crea,
    // edita o borra una publicación o una respuesta, o si alguien reacciona.
    require_once __DIR__ . '/../../models/Publicacion.php';
    new Publicacion(); // asegura que existan las tablas
    $fila = getPDO()->query("
        SELECT
            (SELECT COUNT(*) FROM publicaciones),
            (SELECT COALESCE(MAX(id), 0) FROM publicaciones),
            (SELECT COALESCE(MAX(fecha_actualizacion)::text, '') FROM publicaciones),
            (SELECT COUNT(*) FROM respuestas),
            (SELECT COALESCE(MAX(id), 0) FROM respuestas),
            (SELECT COUNT(*) FROM reacciones)
    ")->fetch(PDO::FETCH_NUM);

    $version = 'db-' . md5(implode('|', $fila));
}

// Mandrainos activos: este mismo sondeo sirve de "latido" del usuario.
require_once __DIR__ . '/../../includes/Presencia.php';
$activos = Presencia::registrar($userId);

echo json_encode(['version' => $version, 'activos' => $activos]);
