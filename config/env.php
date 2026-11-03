<?php
// config/env.php
// Lectura uniforme de variables de entorno. Busca en el entorno real del
// proceso (Docker/Render) y, si no está, en lo que cargó phpdotenv desde
// un archivo .env local.

$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
    if (class_exists(\Dotenv\Dotenv::class)) {
        \Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->safeLoad();
    }
}

function mandraina_env(string $clave, ?string $porDefecto = null): ?string
{
    $valor = getenv($clave);
    if ($valor === false || $valor === '') {
        $valor = $_ENV[$clave] ?? $_SERVER[$clave] ?? null;
    }
    return ($valor === null || $valor === '') ? $porDefecto : (string) $valor;
}
