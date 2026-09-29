<?php
// includes/auth_check.php
// Incluir al principio de cualquier página que requiera sesión iniciada.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
    header('Location: index.html');
    exit;
}
