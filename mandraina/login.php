<?php
// login.php
session_start();
require_once __DIR__ . '/models/Usuario.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.html');
    exit;
}

$nickname = trim($_POST['nickname'] ?? '');
$password = $_POST['password'] ?? '';

if ($nickname === '' || $password === '') {
    $_SESSION['error'] = "Nickname y contraseña requeridos.";
    header('Location: index.html');
    exit;
}

$usuarioModel = new Usuario();
$user = $usuarioModel->autenticar($nickname, $password);

if (!$user) {
    $_SESSION['error'] = "Credenciales inválidas.";
    header('Location: index.html');
    exit;
}

// Login exitoso
session_regenerate_id(true);
$_SESSION['user_id'] = $user['id'];
$_SESSION['nickname'] = $user['nickname'];

header('Location: panel.php');
exit;
