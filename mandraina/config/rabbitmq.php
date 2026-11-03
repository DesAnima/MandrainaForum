<?php
// config/rabbitmq.php
// Conexión a RabbitMQ (php-amqplib) a partir de AMQP_URL.
//   amqp://usuario:clave@host:5672/vhost    (sin TLS)
//   amqps://usuario:clave@host/vhost        (con TLS, p. ej. CloudAMQP)

require_once __DIR__ . '/env.php';

use PhpAmqpLib\Connection\AMQPSSLConnection;
use PhpAmqpLib\Connection\AMQPStreamConnection;

/**
 * @throws RuntimeException si AMQP_URL falta o es inválida.
 *         (Las excepciones de conexión de amqplib se propagan tal cual.)
 */
function getAmqpConnection(float $connTimeout = 3.0, float $rwTimeout = 3.0, int $heartbeat = 0): AMQPStreamConnection
{
    $url = mandraina_env('AMQP_URL');
    if (!$url) {
        throw new RuntimeException('AMQP_URL no está definida.');
    }

    $p = parse_url($url);
    if ($p === false || empty($p['host'])) {
        throw new RuntimeException('AMQP_URL inválida.');
    }

    $tls = ($p['scheme'] ?? 'amqp') === 'amqps';
    $host = $p['host'];
    $port = $p['port'] ?? ($tls ? 5671 : 5672);
    $user = isset($p['user']) ? urldecode($p['user']) : 'guest';
    $pass = isset($p['pass']) ? urldecode($p['pass']) : 'guest';
    $path = $p['path'] ?? '';
    $vhost = ($path === '' || $path === '/') ? '/' : urldecode(ltrim($path, '/'));

    if ($tls) {
        return new AMQPSSLConnection($host, $port, $user, $pass, $vhost, [], [
            'connection_timeout' => $connTimeout,
            'read_write_timeout' => $rwTimeout,
            'heartbeat' => $heartbeat,
        ]);
    }

    return new AMQPStreamConnection(
        $host, $port, $user, $pass, $vhost,
        false, 'AMQPLAIN', null, 'en_US',
        $connTimeout, $rwTimeout, null, false, $heartbeat
    );
}
