<?php
// includes/auth_check.php
// Incluir al principio de cualquier página que requiera sesión iniciada.
require_once __DIR__ . '/session.php';
mandraina_session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: index.html');
    exit;
}
