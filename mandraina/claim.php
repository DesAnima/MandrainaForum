<?php
// claim.php?token=...
// Página a la que apunta el QR.
//
// IMPORTANTE: el simple hecho de ABRIR este link (GET) NO consume el
// token ni crea la cuenta. Muchos lectores de QR, antivirus o apps de
// mensajería "pre-visitan" un link automáticamente antes de que la
// persona lo toque, y eso gastaría un token de un solo uso sin que
// nadie lo haya usado de verdad.
//
// Por eso el flujo es:
//   GET  -> solo valida que el token exista y no haya expirado, y
//           muestra un botón de confirmación.
//   POST -> recién ahí se crea la cuenta real y se muestra la
//           contraseña una única vez.

require_once __DIR__ . '/models/Usuario.php';

$usuario = new Usuario();
$token = trim($_POST['token'] ?? $_GET['token'] ?? '');
$resultado = null;
$valido = false;

if ($token !== '') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $resultado = $usuario->reclamar($token);
    } else {
        $valido = $usuario->pendienteValido($token);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Tu contraseña - Mandraina Forum</title>
    <link rel="stylesheet" href="styles.css" />
</head>
<body>
    <div class="login-wrapper">
        <div class="login-container">
            <?php if ($resultado): ?>
                <h2>¡Cuenta creada!</h2>
                <p style="color:#fff;">Nickname: <strong style="color:#ffd900;"><?= htmlspecialchars($resultado['nickname']) ?></strong></p>
                <p style="color:#fff;">Tu contraseña (anotala, no se va a volver a mostrar):</p>
                <p style="font-size:1.6rem; letter-spacing:2px; color:#ffd900; background:#000; padding:0.75rem; border-radius:8px; text-align:center; word-break:break-all;">
                    <?= htmlspecialchars($resultado['password']) ?>
                </p>
                <p style="color:#fff; font-size:0.85rem;">Esta página ya no va a mostrar esta contraseña de nuevo. Guardala antes de salir.</p>
                <button type="button" class="btn btn-login" onclick="window.location.href='index.html'">Ir al login</button>

            <?php elseif ($valido): ?>
                <h2>Confirmá tu registro</h2>
                <p style="color:#fff;">Tocá el botón para generar tu cuenta y ver tu contraseña. Se muestra una única vez, así que asegurate de poder anotarla.</p>
                <form method="POST" action="claim.php">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>" />
                    <button type="submit" class="btn btn-login">Ver mi contraseña</button>
                </form>

            <?php else: ?>
                <h2>Enlace inválido, vencido o ya usado</h2>
                <p style="color:#fff;">Este código ya fue usado o pasaron más de <?= (int) (Usuario::TTL_SEGUNDOS / 60) ?> minutos desde que se generó.</p>
                <button type="button" class="btn btn-register" onclick="window.location.href='register.html'">Generar uno nuevo</button>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
