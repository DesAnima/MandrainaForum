<?php
$dsn = 'pgsql:host=ep-something.us-east-2.aws.neon.tech;port=5432;dbname=nombre_db;sslmode=require';
$user = 'usuario';
$password = 'password';

try {
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("Error de conexión a la base de datos.");
}