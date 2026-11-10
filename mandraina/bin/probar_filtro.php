<?php
// bin/probar_filtro.php
// Diagnóstico de la censura. Se corre desde la línea de comandos:
//
//     php bin/probar_filtro.php "texto a probar"
//     docker compose exec web php bin/probar_filtro.php "puto"
//
// Dice si el texto se censuraría, qué palabras detecta, si el filtro tiene
// algún error y si el código desplegado en este contenedor/servidor ya trae
// la censura en la API (útil cuando se editó el código pero no se reconstruyó
// la imagen de Docker o no se volvió a desplegar).

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo se puede ejecutar desde la línea de comandos.');
}

require_once __DIR__ . '/../includes/FiltroContenido.php';

$texto = $argv[1] ?? 'puto';

echo 'PHP ' . PHP_VERSION . ' / PCRE ' . PCRE_VERSION . PHP_EOL;
echo 'Texto probado: ' . $texto . PHP_EOL;

$encontradas = FiltroContenido::encontrar($texto);
echo 'Insultos detectados: ' . count($encontradas)
    . ($encontradas ? ' (' . implode(', ', $encontradas) . ')' : '') . PHP_EOL;
echo 'Resultado: ' . ($encontradas ? 'SE CENSURA' : 'NO se censura') . PHP_EOL;

if (preg_last_error() !== PREG_NO_ERROR) {
    echo 'ERROR de expresión regular: ' . preg_last_error_msg() . PHP_EOL;
}

echo PHP_EOL . 'Código desplegado en ' . dirname(__DIR__) . ':' . PHP_EOL;

$base = dirname(__DIR__);
$controles = [
    'includes/Moderacion.php' => null,
    'controllers/PublicacionController.php' => 'Moderacion::intentarRegistrar',
    'controllers/RespuestaController.php' => 'Moderacion::intentarRegistrar',
];

foreach ($controles as $archivo => $marca) {
    $ruta = $base . '/' . $archivo;
    if (!is_file($ruta)) {
        echo "  [FALTA]   {$archivo}" . PHP_EOL;
        continue;
    }
    if ($marca !== null && strpos((string) file_get_contents($ruta), $marca) === false) {
        echo "  [VIEJO]   {$archivo} (no tiene la censura: falta reconstruir/redesplegar)" . PHP_EOL;
        continue;
    }
    echo "  [OK]      {$archivo}" . PHP_EOL;
}
