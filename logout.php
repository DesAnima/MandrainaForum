<?php
require_once __DIR__ . '/includes/session.php';
mandraina_session_start();
$_SESSION = [];
session_destroy();
header('Location: index.html');
exit;
