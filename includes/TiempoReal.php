<?php
// includes/TiempoReal.php
// "Versión" del contenido del foro, para que el panel sepa cuándo recargar.
//
// Cada vez que cambia algo (publicación, respuesta, borrado, moderación) se
// guarda en Redis un valor nuevo e irrepetible. El panel pregunta cada pocos
// segundos por ese valor (api/v1/novedades.php, una consulta mínima) y sólo
// cuando cambia vuelve a pedir el feed. Si Redis no está disponible,
// novedades.php calcula una huella equivalente desde PostgreSQL.

require_once __DIR__ . '/../config/redis.php';

class TiempoReal
{
    private const CLAVE = 'rt:version';

    /** Marca que el contenido cambió. Si no hay Redis no hace nada. */
    public static function avisar(): void
    {
        $redis = getRedis();
        if ($redis === null) {
            return;
        }

        try {
            // uniqid y no un contador: si Redis se reinicia y pierde la clave,
            // un contador volvería a valores ya vistos y los clientes se
            // perderían un cambio.
            $redis->set(self::CLAVE, uniqid('', true));
        } catch (Throwable $e) {
            error_log('[tiemporeal] avisar: ' . $e->getMessage());
        }
    }

    /** Versión actual, o null si Redis no está disponible. */
    public static function version(): ?string
    {
        $redis = getRedis();
        if ($redis === null) {
            return null;
        }

        try {
            $valor = $redis->get(self::CLAVE);
            return $valor === null ? '0' : (string) $valor;
        } catch (Throwable $e) {
            error_log('[tiemporeal] version: ' . $e->getMessage());
            return null;
        }
    }
}
