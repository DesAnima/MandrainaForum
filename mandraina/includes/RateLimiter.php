<?php
// includes/RateLimiter.php
// Límite de intentos por ventana fija usando INCR + EXPIRE en Redis.
// Si Redis no está disponible deja pasar todo (fail-open).

require_once __DIR__ . '/../config/redis.php';

class RateLimiter
{
    /** IP del cliente. Solo confía en X-Forwarded-For si TRUST_PROXY=1. */
    public static function ip(): string
    {
        if (mandraina_env('TRUST_PROXY') === '1' && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $primera = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
            if (filter_var($primera, FILTER_VALIDATE_IP)) {
                return $primera;
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? 'desconocida';
    }

    /**
     * Registra un intento y dice si todavía está dentro del límite.
     *
     * @param string $accion  "login", "register", "post"...
     * @param string $id      a quién se limita (IP, id de usuario...)
     * @param int    $max     intentos permitidos por ventana
     * @param int    $ventana duración de la ventana en segundos
     */
    public static function permitir(string $accion, string $id, int $max, int $ventana): bool
    {
        $redis = getRedis();
        if ($redis === null) {
            return true;
        }

        $clave = "rl:{$accion}:{$id}";
        try {
            $cuenta = (int) $redis->incr($clave);
            if ($cuenta === 1) {
                $redis->expire($clave, $ventana);
            }
            return $cuenta <= $max;
        } catch (Throwable $e) {
            error_log('[ratelimit] ' . $e->getMessage());
            return true;
        }
    }
}
