<?php
// bin/crear_admin.php
// Crea la cuenta del administrador (ADMIN_NICKNAME) con la contraseña de
// ADMIN_PASSWORD. Es idempotente: si el usuario ya existe no lo toca, salvo
// que se pase --reset, que le cambia la contraseña por la de ADMIN_PASSWORD.
//
//   php bin/crear_admin.php
//   php bin/crear_admin.php --reset
//
// Se ejecuta solo al arrancar el contenedor (docker/start.sh) cuando
// ADMIN_NICKNAME y ADMIN_PASSWORD están definidas.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../includes/Admin.php';

$nickname = Admin::nickname();
$password = (string) mandraina_env('ADMIN_PASSWORD', '');
$reset = in_array('--reset', $argv, true);

if ($nickname === null) {
    fwrite(STDERR, "[admin] No hay nickname de administrador configurado.\n");
    exit(0);
}

if (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $nickname)) {
    fwrite(STDERR, "[admin] ADMIN_NICKNAME debe tener 3 a 20 caracteres (letras, números o \"_\").\n");
    exit(1);
}

if (mb_strlen($password) < 10) {
    fwrite(STDERR, "[admin] ADMIN_PASSWORD debe tener al menos 10 caracteres.\n");
    exit(1);
}

new Usuario(); // asegura que la tabla `usuarios` exista
$pdo = getPDO();

$stmt = $pdo->prepare('SELECT id FROM usuarios WHERE nickname = :n');
$stmt->execute([':n' => $nickname]);
$existente = $stmt->fetch();

if ($existente) {
    if ($reset) {
        $upd = $pdo->prepare('UPDATE usuarios SET password = :p WHERE id = :id');
        $upd->execute([':p' => password_hash($password, PASSWORD_DEFAULT), ':id' => $existente['id']]);
        echo "[admin] Contraseña de \"{$nickname}\" actualizada.\n";
    } else {
        echo "[admin] \"{$nickname}\" ya existe; no se modifica (usá --reset para cambiar la contraseña).\n";
    }
    exit(0);
}

$ins = $pdo->prepare('INSERT INTO usuarios (nickname, password) VALUES (:n, :p)');
$ins->execute([':n' => $nickname, ':p' => password_hash($password, PASSWORD_DEFAULT)]);
echo "[admin] Cuenta de administrador \"{$nickname}\" creada.\n";
