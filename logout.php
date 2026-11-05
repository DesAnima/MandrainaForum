<?php
require_once __DIR__ . '/includes/session.php';
mandraina_session_start();
require_once __DIR__ . '/includes/Presencia.php';
Presencia::quitar((int) ($_SESSION['user_id'] ?? 0));
$_SESSION = [];
session_destroy();
header('Location: index.html');
exit;
