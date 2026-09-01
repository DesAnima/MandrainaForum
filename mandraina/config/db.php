<?php
// config/db.php
// Conexión centralizada a la base de datos usando variables de entorno.
// No hardcodear credenciales acá (ver .env.example).

function getPDO(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $databaseUrl = getenv('DATABASE_URL');

    if ($databaseUrl) {
        $parts = parse_url($databaseUrl);
        $user = $parts['user'] ?? '';
        $password = $parts['pass'] ?? '';
        $host = $parts['host'] ?? '';
        $port = $parts['port'] ?? '5432';
        $dbname = ltrim($parts['path'] ?? '', '/');
        parse_str($parts['query'] ?? '', $query);
        $sslmode = $query['sslmode'] ?? 'require';
    } else {
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
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        // No exponemos el mensaje real de PDO al cliente, lo relanzamos
        // para que lo capture el middleware central de errores.
        throw new RuntimeException('Error de conexión a la base de datos.');
    }

    return $pdo;
}
