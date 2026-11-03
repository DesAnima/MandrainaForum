<?php
// config/redis.php
// Conexión centralizada a Redis (cliente Predis, PHP puro).
//
// getRedis() devuelve null si REDIS_URL no está definida o si Redis no
// responde. Todo el código que lo usa tiene que tolerar ese null: Redis
// acelera y protege la app (sesiones, caché, rate limiting), pero la app
// no debería caerse si Redis se cae.

require_once __DIR__ . '/env.php';

function getRedis(): ?\Predis\Client
{
    static $client = null;
    static $intentado = false;

    if ($intentado) {
        return $client;
    }
    $intentado = true;

    $url = mandraina_env('REDIS_URL');
    if (!$url) {
        return null;
    }

    if (!class_exists(\Predis\Client::class)) {
        error_log('[redis] Predis no está instalado (¿falta composer install?).');
        return null;
    }

    $p = parse_url($url);
    if ($p === false || empty($p['host'])) {
        error_log('[redis] REDIS_URL inválida.');
        return null;
    }

    $params = [
        // "rediss://" = Redis con TLS (Upstash, Redis Cloud, etc.)
        'scheme' => (($p['scheme'] ?? 'redis') === 'rediss') ? 'tls' : 'tcp',
        'host' => $p['host'],
        'port' => $p['port'] ?? 6379,
        // Timeouts cortos: si Redis no responde, preferimos seguir sin él
        // antes que dejar colgado el request.
        'timeout' => 2.0,
        'read_write_timeout' => 2.0,
    ];
    if (!empty($p['user'])) {
        $params['username'] = urldecode($p['user']);
    }
    if (isset($p['pass']) && $p['pass'] !== '') {
        $params['password'] = urldecode($p['pass']);
    }
    $db = ltrim($p['path'] ?? '', '/');
    if ($db !== '' && ctype_digit($db)) {
        $params['database'] = (int) $db;
    }

    try {
        // Todas las claves quedan con el prefijo "mandraina:" (permite
        // compartir una misma instancia de Redis con otras apps).
        $candidato = new \Predis\Client($params, ['prefix' => 'mandraina:']);
        $candidato->ping();
        $client = $candidato;
    } catch (Throwable $e) {
        error_log('[redis] No se pudo conectar: ' . $e->getMessage());
        $client = null;
    }

    return $client;
}
