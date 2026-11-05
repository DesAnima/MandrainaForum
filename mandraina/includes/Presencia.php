<?php
// includes/Presencia.php
// "Mandrainos activos": usuarios con el panel abierto y visible ahora mismo.
//
// Cada sondeo de novedades.php (cada ~3 s por pestaña abierta, y solo con la
// pestaña visible) marca al usuario como visto. Se cuenta como activo a quien
// se vio en los últimos VENTANA segundos; varias pestañas del mismo usuario
// cuentan una sola vez.
//
// Con Redis es un conjunto ordenado (score = última vez visto). Sin Redis cae
// a una tabla de PostgreSQL.

require_once __DIR__ . '/../config/redis.php';
require_once __DIR__ . '/../config/db.php';

class Presencia
{
    public const VENTANA = 20; // segundos
    private const CLAVE = 'presencia';

    /** Marca al usuario como activo y devuelve cuántos hay. null si no se pudo contar. */
    public static function registrar(int $userId): ?int
    {
        if ($userId <= 0) {
            return null;
        }

        try {
            $redis = getRedis();
            return $redis !== null
                ? self::conRedis($redis, $userId)
                : self::conPostgres($userId);
        } catch (Throwable $e) {
            error_log('[presencia] registrar: ' . $e->getMessage());
            return null;
        }
    }

    /** Saca al usuario del conteo (al cerrar sesión). */
    public static function quitar(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        try {
            $redis = getRedis();
            if ($redis !== null) {
                $redis->zrem(self::CLAVE, (string) $userId);
            } else {
                $stmt = getPDO()->prepare('DELETE FROM presencia WHERE user_id = :id');
                $stmt->execute([':id' => $userId]);
            }
        } catch (Throwable $e) {
            // Si falla, el usuario sale del conteo solo al pasar la ventana.
        }
    }

    private static function conRedis(\Predis\Client $redis, int $userId): int
    {
        $ahora = time();
        $res = $redis->pipeline(function ($pipe) use ($userId, $ahora) {
            $pipe->zremrangebyscore(self::CLAVE, '-inf', $ahora - self::VENTANA); // fuera los inactivos
            $pipe->zadd(self::CLAVE, [(string) $userId => $ahora]);
            $pipe->expire(self::CLAVE, self::VENTANA * 6);                         // limpieza si nadie entra
            $pipe->zcard(self::CLAVE);
        });
        return (int) end($res);
    }

    private static function conPostgres(int $userId): int
    {
        $pdo = getPDO();
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS presencia (
                user_id INTEGER PRIMARY KEY REFERENCES usuarios(id) ON DELETE CASCADE,
                visto TIMESTAMP NOT NULL DEFAULT NOW()
            )
        ");

        // Solo escribe si pasaron más de 10 s desde la última marca.
        $stmt = $pdo->prepare("
            INSERT INTO presencia (user_id, visto) VALUES (:id, NOW())
            ON CONFLICT (user_id) DO UPDATE SET visto = NOW()
            WHERE presencia.visto < NOW() - INTERVAL '10 seconds'
        ");
        $stmt->execute([':id' => $userId]);

        $stmt = $pdo->prepare("
            SELECT COUNT(*)::int FROM presencia
            WHERE visto >= NOW() - (CAST(:v AS integer) * INTERVAL '1 second')
        ");
        $stmt->execute([':v' => self::VENTANA]);
        return (int) $stmt->fetchColumn();
    }
}
