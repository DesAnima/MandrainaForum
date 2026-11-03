<?php
// includes/session.php
// Inicia la sesión de PHP guardándola en Redis (si está disponible).
// Sin Redis, cae a las sesiones en archivos de PHP como antes.
//
// Por qué: en Render (y en cualquier despliegue con varias instancias o
// con disco efímero) las sesiones en archivos se pierden en cada deploy
// o no se comparten entre instancias. En Redis sobreviven a ambos.

require_once __DIR__ . '/../config/redis.php';

class RedisSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    public function __construct(private \Predis\Client $redis, private int $ttl)
    {
    }

    private function clave(string $id): string
    {
        return 'sess:' . $id;
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        try {
            $datos = $this->redis->get($this->clave($id));
            return $datos === null ? '' : (string) $datos;
        } catch (Throwable $e) {
            error_log('[session] read: ' . $e->getMessage());
            return '';
        }
    }

    public function write(string $id, string $data): bool
    {
        try {
            $this->redis->setex($this->clave($id), $this->ttl, $data);
            return true;
        } catch (Throwable $e) {
            error_log('[session] write: ' . $e->getMessage());
            return false;
        }
    }

    public function destroy(string $id): bool
    {
        try {
            $this->redis->del([$this->clave($id)]);
        } catch (Throwable $e) {
            error_log('[session] destroy: ' . $e->getMessage());
        }
        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        return 0; // Redis expira las claves solo (TTL), no hay nada que barrer.
    }

    // --- lazy_write: si la sesión no cambió, solo se renueva el TTL ---

    public function validateId(string $id): bool
    {
        try {
            return (bool) $this->redis->exists($this->clave($id));
        } catch (Throwable $e) {
            return false;
        }
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        try {
            $this->redis->expire($this->clave($id), $this->ttl);
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
}

function mandraina_session_start(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    $ttl = (int) mandraina_env('SESSION_TTL', '7200');

    $redis = getRedis();
    if ($redis !== null) {
        session_set_save_handler(new RedisSessionHandler($redis, $ttl), true);
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    // Rechaza IDs de sesión que el servidor no generó (evita fijación de sesión).
    ini_set('session.use_strict_mode', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}
