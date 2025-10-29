<?php
// Leer la URL de conexión desde la variable de entorno
$databaseUrl = getenv('DATABASE_URL');
//xd
if ($databaseUrl) {
    // Ejemplo: postgresql://user:pass@host:port/dbname?sslmode=require
    $parts = parse_url($databaseUrl);
    $user = $parts['user'] ?? '';
    $password = $parts['pass'] ?? '';
    $host = $parts['host'] ?? '';
    $port = $parts['port'] ?? '5432';
    $dbname = ltrim($parts['path'] ?? '', '/');
    // Tomar el sslmode desde la query si existe
    parse_str($parts['query'] ?? '', $query);
    $sslmode = $query['sslmode'] ?? 'require';
} else {
    // Variables separadas como alternativa
    $host = getenv('DB_HOST') ?: 'localhost';
    $dbname = getenv('DB_NAME') ?: 'neondb';
    $user = getenv('DB_USER') ?: 'neondb_owner';
    $password = getenv('DB_PASS') ?: '';
    $port = getenv('DB_PORT') ?: '5432';
    $sslmode = getenv('DB_SSLMODE') ?: 'require';
}

$dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode={$sslmode}";

try {
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    die("Error de conexión DB: " . $e->getMessage());
}
?>

