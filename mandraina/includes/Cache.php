<?php
// includes/Cache.php
// Caché clave/valor sobre Redis. Todo es "fail-open": si Redis no está,
// obtener() devuelve null (como un miss) y guardar()/borrar() no hacen nada.

require_once __DIR__ . '/../config/redis.php';

class Cache
{
    /** @return mixed null si no existe (o si Redis no está disponible). */
    public static function obtener(string $clave): mixed
    {
        $redis = getRedis();
        if ($redis === null) {
            return null;
        }
        try {
            $crudo = $redis->get('cache:' . $clave);
            return $crudo === null ? null : json_decode($crudo, true);
        } catch (Throwable $e) {
            error_log('[cache] get: ' . $e->getMessage());
            return null;
        }
    }

    public static function guardar(string $clave, mixed $valor, int $ttlSegundos): void
    {
        $redis = getRedis();
        if ($redis === null) {
            return;
        }
        try {
            $redis->setex('cache:' . $clave, $ttlSegundos, json_encode($valor));
        } catch (Throwable $e) {
            error_log('[cache] set: ' . $e->getMessage());
        }
    }

    public static function borrar(string ...$claves): void
    {
        $redis = getRedis();
        if ($redis === null || $claves === []) {
            return;
        }
        try {
            $redis->del(array_map(fn($c) => 'cache:' . $c, $claves));
        } catch (Throwable $e) {
            error_log('[cache] del: ' . $e->getMessage());
        }
    }
}
