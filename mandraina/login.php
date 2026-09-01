<?php
// login.php
session_start();
require_once __DIR__ . '/config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.html');
    exit;
}

$pdo = getPDO();

$email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
$password = $_POST['password'] ?? '';

if (!$email || !$password) {
    $_SESSION['error'] = "Email y contraseña requeridos.";
    header('Location: index.html');
    exit;
}

$stmt = $pdo->prepare("SELECT id, password, nickname FROM usuarios WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    $_SESSION['error'] = "Credenciales inválidas.";
    header('Location: index.html');
    exit;
}

// Login exitoso
session_regenerate_id(true);
$_SESSION['user_id'] = $user['id'];
$_SESSION['email'] = $email;
$_SESSION['nickname'] = $user['nickname'];

header('Location: panel.php');
exit;
