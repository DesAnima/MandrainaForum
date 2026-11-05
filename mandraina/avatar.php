<?php
// avatar.php?id={id}
// Sirve la foto de perfil (columna BYTEA "profile_pic") de un usuario.
// Pensada para avatares "de personaje" elegidos por el usuario, nunca
// para fotos reales tomadas en el registro (que sigue siendo anónimo).
// Si el usuario no tiene foto (o el archivo no es una imagen válida),
// devuelve un avatar genérico.

ob_start(); // descarta cualquier texto suelto que emitan los require, para no corromper la imagen
require_once __DIR__ . '/includes/session.php';
mandraina_session_start();
require_once __DIR__ . '/config/db.php';

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}
session_write_close(); // no bloquear la sesión mientras se cargan varias imágenes a la vez

function avatarPorDefecto(): void
{
    ob_end_clean();
    header('Content-Type: image/svg+xml');
    header('Cache-Control: private, max-age=300');
    echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
        . '<rect width="64" height="64" fill="#000000" rx="32"/>'
        . '<circle cx="32" cy="24" r="12" fill="#ffd900"/>'
        . '<path d="M8 64c0-13 10-22 24-22s24 9 24 22z" fill="#ffd900"/>'
        . '</svg>';
    exit;
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    avatarPorDefecto();
}

try {
    $stmt = getPDO()->prepare('SELECT profile_pic FROM usuarios WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $foto = $stmt->fetchColumn();
} catch (Throwable $e) {
    error_log('avatar.php: ' . $e->getMessage());
    avatarPorDefecto();
}

// PDO devuelve las columnas BYTEA de PostgreSQL como un stream, no como string.
if (is_resource($foto)) {
    $foto = stream_get_contents($foto);
}

if (!is_string($foto) || $foto === '') {
    avatarPorDefecto();
}

// Solo se sirven formatos de imagen conocidos (esto también descarta SVG u otros archivos).
$info = @getimagesizefromstring($foto);
$mime = $info['mime'] ?? '';
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
    avatarPorDefecto();
}

ob_end_clean();
header('Content-Type: ' . $mime);
header('Content-Length: ' . strlen($foto));
header('Cache-Control: private, max-age=300');
header('X-Content-Type-Options: nosniff');
echo $foto;
