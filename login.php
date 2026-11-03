<?php
// login.php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/RateLimiter.php';
require_once __DIR__ . '/models/Usuario.php';
mandraina_session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.html');
    exit;
}

$nickname = trim($_POST['nickname'] ?? '');
$password = $_POST['password'] ?? '';

if ($nickname === '' || $password === '') {
    header('Location: index.html?e=vacio');
    exit;
}

// Freno a la fuerza bruta: máximo 10 intentos cada 5 minutos por IP y
// 5 por nickname (así no se puede probar claves contra una sola cuenta
// rotando de IP). Se cuenta cada intento, acierte o no.
if (
    !RateLimiter::permitir('login-ip', RateLimiter::ip(), 10, 300)
    || !RateLimiter::permitir('login-nick', strtolower(substr($nickname, 0, 64)), 5, 300)
) {
    header('Location: index.html?e=limite');
    exit;
}

$usuarioModel = new Usuario();
$user = $usuarioModel->autenticar($nickname, $password);

if (!$user) {
    header('Location: index.html?e=credenciales');
    exit;
}

// Login exitoso
session_regenerate_id(true);
$_SESSION['user_id'] = $user['id'];
$_SESSION['nickname'] = $user['nickname'];

header('Location: panel.php');
exit;
