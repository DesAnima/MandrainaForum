<?php
// Leer conexión desde variables de entorno (Render/Neon)
$databaseUrl = getenv('postgresql://neondb_owner:npg_FVSjnq2Z3QfG@ep-bitter-silence-agogkiof-pooler.c-2.eu-central-1.aws.neon.tech/neondb?sslmode=require&channel_binding=require');

if ($databaseUrl) {
    // Ejemplo de DATABASE_URL: postgres://user:pass@host:port/dbname
    $parts = parse_url($databaseUrl);
    $user = $parts['user'] ?? '';
    $password = $parts['pass'] ?? '';
    $host = $parts['host'] ?? '';
    $port = $parts['port'] ?? '5432';
    $dbname = ltrim($parts['path'] ?? '', '/');
    $sslmode = 'require';
} else {
    // Variables separadas (por si querés usar DB_HOST, DB_USER, etc.)
    $host = getenv('DB_HOST');
    $dbname = getenv('DB_NAME');
    $user = getenv('DB_USER');
    $password = getenv('DB_PASS');
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
