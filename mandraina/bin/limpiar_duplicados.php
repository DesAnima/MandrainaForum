<?php
// bin/limpiar_duplicados.php
// Elimina las publicaciones duplicadas que ya existen (mismo usuario, mismo
// título y contenido, creadas hasta 10 minutos después de la original) y
// conserva la más antigua de cada grupo.
//
//   php bin/limpiar_duplicados.php --simular   solo muestra qué se borraría
//   php bin/limpiar_duplicados.php             borra de verdad
//
// OJO: al borrar una copia también se borran sus respuestas y reacciones.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../models/Publicacion.php';
require_once __DIR__ . '/../includes/Feed.php';

$simular = in_array('--simular', $argv, true);
$modelo = new Publicacion();

$duplicadas = $simular ? $modelo->buscarDuplicadas() : $modelo->eliminarDuplicadas();

if (!$duplicadas) {
    echo "No hay publicaciones duplicadas.\n";
    exit(0);
}

foreach ($duplicadas as $d) {
    echo sprintf("#%d (copia de #%d, %d respuestas)\n", $d['id'], $d['original_id'], $d['respuestas']);
}

if ($simular) {
    echo count($duplicadas) . " duplicadas encontradas (simulacro: no se borró nada).\n";
} else {
    Feed::invalidar();
    echo count($duplicadas) . " duplicadas eliminadas.\n";
}
