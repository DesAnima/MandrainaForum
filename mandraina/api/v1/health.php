<?php
// api/v1/health.php   (GET /api/v1/health)
// Estado de las dependencias. Útil para verificar el despliegue y como
// health check de Render / un balanceador. Responde 200 si PostgreSQL
// funciona (es la única dependencia obligatoria) y 503 si no.
// Redis y RabbitMQ son opcionales: se informan pero no tumban el estado.

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/redis.php';
require_once __DIR__ . '/../../config/rabbitmq.php';

$estado = ['postgres' => 'error', 'redis' => 'no configurado', 'rabbitmq' => 'no configurado'];

try {
    getPDO()->query('SELECT 1');
    $estado['postgres'] = 'ok';
} catch (Throwable $e) {
    error_log('[health] postgres: ' . $e->getMessage());
}

if (mandraina_env('REDIS_URL')) {
    $estado['redis'] = getRedis() !== null ? 'ok' : 'error';
}

if (mandraina_env('AMQP_URL')) {
    try {
        $conn = getAmqpConnection(2.0, 2.0);
        $conn->close();
        $estado['rabbitmq'] = 'ok';
    } catch (Throwable $e) {
        error_log('[health] rabbitmq: ' . $e->getMessage());
        $estado['rabbitmq'] = 'error';
    }
}

$ok = $estado['postgres'] === 'ok';
http_response_code($ok ? 200 : 503);
echo json_encode(['status' => $ok ? 'ok' : 'error'] + $estado);
