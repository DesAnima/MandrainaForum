<?php
 
function getPDO(): PDO
{
    static $pdo = null;
 
    if ($pdo !== null) {
        return $pdo;
    }
 
    // Configuración local de Neon
    $configFile = __DIR__ . '/database.local.php';
 
    if (!file_exists($configFile)) {
        throw new RuntimeException(
            'No existe config/database.local.php'
        );
    }
 
    $config = require $configFile;
 
    // Diagnóstico: cada error dice exactamente qué está mal en database.local.php
    if (!is_array($config)) {
        throw new RuntimeException(
            'config/database.local.php no devuelve un array. Tiene que empezar con "return [" y terminar con "];"'
        );
    }
 
    if (!array_key_exists('url', $config)) {
        throw new RuntimeException(
            "config/database.local.php no tiene la clave 'url'. Claves encontradas: "
            . (implode(', ', array_keys($config)) ?: '(ninguna)')
        );
    }
 
    $databaseUrl = trim((string) $config['url']);
 
    if ($databaseUrl === '') {
        throw new RuntimeException(
            "La clave 'url' de config/database.local.php está vacía"
        );
    }
 
    $parts = parse_url($databaseUrl);
 
    if ($parts === false || empty($parts['host']) || empty($parts['user'])) {
        throw new RuntimeException(
            'La URL de Neon no tiene un formato válido. Debe ser: postgresql://usuario:clave@host/base?sslmode=require'
        );
    }
 
    $user = rawurldecode($parts['user'] ?? '');
    $password = rawurldecode($parts['pass'] ?? '');
    $host = $parts['host'] ?? '';
    $port = $parts['port'] ?? '5432';
    $dbname = ltrim($parts['path'] ?? '', '/');
 
    parse_str($parts['query'] ?? '', $query);
    $sslmode = $query['sslmode'] ?? 'require';
    // Primera parte del host, ej: ep-xxxx-pooler
    $endpointId = explode('.', $host)[0];
 
    // OJO: NO usar "options=endpoint=..." en el DSN. El host "-pooler" de Neon (PgBouncer)
    // rechaza el parámetro "options" y tira "unsupported options startup parameter".
    $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode={$sslmode}";
 
    // El libpq de XAMPP es viejo y no envía SNI, así que Neon no sabe a qué base conectarte.
    // Solución oficial de Neon: mandar el endpoint dentro de la contraseña ("endpoint=<id>;<password>").
    $pdo = new PDO(
        $dsn,
        $user,
        "endpoint={$endpointId};{$password}",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
 
    return $pdo;
}