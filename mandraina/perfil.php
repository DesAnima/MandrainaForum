<?php
// perfil.php
// Página autenticada para elegir un avatar "de personaje". No pide ni
// guarda ningún dato personal: solo una imagen opcional asociada al
// nickname, igual que cualquier avatar de foro.

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/models/Usuario.php';

const TAMANIO_MAXIMO = 3 * 1024 * 1024; // 3 MB
const MIME_PERMITIDOS = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

$usuarioModel = new Usuario();
$mensaje = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['quitar_foto'])) {
        $usuarioModel->eliminarFoto((int) $_SESSION['user_id']);
        $mensaje = 'Se quitó tu avatar. Volviste al ícono por defecto.';
    } elseif (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $archivo = $_FILES['avatar'];

        if ($archivo['size'] > TAMANIO_MAXIMO) {
            $error = 'La imagen no puede pesar más de 3 MB.';
        } else {
            $contenido = file_get_contents($archivo['tmp_name']);
            $info = @getimagesizefromstring($contenido);
            $mime = $info['mime'] ?? '';

            if (!in_array($mime, MIME_PERMITIDOS, true)) {
                $error = 'Formato no soportado. Usá JPG, PNG, GIF o WEBP.';
            } else {
                $usuarioModel->actualizarFoto((int) $_SESSION['user_id'], $contenido);
                $mensaje = '¡Avatar actualizado!';
            }
        }
    } elseif (isset($_FILES['avatar'])) {
        $error = 'No se pudo subir el archivo. Probá de nuevo.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Mi perfil - Mandraina Forum</title>
    <link rel="stylesheet" href="styles.css" />
    <link rel="stylesheet" href="panel.css" />
</head>
<body class="panel-body">
    <header class="panel-header">
        <h1>Mandraina Forum</h1>
        <div class="panel-header-user">
            <span>Hola, <strong><?= htmlspecialchars($_SESSION['nickname'] ?? '') ?></strong></span>
            <a href="panel.php" class="btn btn-register btn-small">Volver al foro</a>
        </div>
    </header>

    <main class="panel-main">
        <section class="post-form-card" style="text-align:center;">
            <h2>Tu avatar</h2>
            <p style="color:#fff; font-size:0.85rem;">
                Usá la imagen de un personaje o algo que te represente — evitá fotos reales tuyas,
                el resto del foro sigue siendo completamente anónimo.
            </p>

            <img class="avatar avatar-preview" src="avatar.php?id=<?= (int) $_SESSION['user_id'] ?>&r=<?= time() ?>" alt="Tu avatar actual" />

            <?php if ($mensaje): ?>
                <p style="color:#7CFC00;"><?= htmlspecialchars($mensaje) ?></p>
            <?php endif; ?>
            <?php if ($error): ?>
                <p style="color:#ff5c5c;"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" style="margin-top:1rem;">
                <input type="file" name="avatar" accept="image/png, image/jpeg, image/gif, image/webp" class="input-box" />
                <button type="submit" class="btn btn-login">Guardar avatar</button>
            </form>

            <form method="POST" style="margin-top:0.5rem;">
                <button type="submit" name="quitar_foto" value="1" class="btn btn-register">Quitar avatar</button>
            </form>
        </section>
    </main>
</body>
</html>
