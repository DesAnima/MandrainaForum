<?php
// includes/Moderacion.php
// Registro de lo que se censura (tabla `moderacion_log`). Lo usan la API, que
// censura en el momento, y el worker, que sigue revisando como respaldo.

require_once __DIR__ . '/../config/db.php';

class Moderacion
{
    // Mensajes que ve el usuario (el panel los muestra en una alerta).
    public const MENSAJE_PUBLICACION = 'No se puede utilizar lenguaje inapropiado.';
    public const MENSAJE_EDICION = 'No se puede utilizar lenguaje inapropiado. Tu publicación fue eliminada.';
    public const MENSAJE_RESPUESTA = 'No se puede utilizar lenguaje inapropiado.';

    public static function asegurarTabla(PDO $pdo): void
    {
        // Sin FK a propósito: el registro se conserva aunque la publicación
        // se elimine (o nunca haya llegado a guardarse: en ese caso el id es 0).
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS moderacion_log (
                id SERIAL PRIMARY KEY,
                publicacion_id INTEGER NOT NULL,
                user_id INTEGER,
                evento TEXT NOT NULL,
                motivo TEXT NOT NULL,
                creado_en TIMESTAMP NOT NULL DEFAULT NOW()
            )
        ");
    }

    /** @throws PDOException */
    public static function registrar(PDO $pdo, int $publicacionId, int $userId, string $evento, string $motivo): void
    {
        $stmt = $pdo->prepare("
            INSERT INTO moderacion_log (publicacion_id, user_id, evento, motivo)
            VALUES (:pid, :uid, :evento, :motivo)
        ");
        $stmt->execute([
            ':pid' => $publicacionId,
            ':uid' => $userId,
            ':evento' => $evento,
            ':motivo' => $motivo,
        ]);
    }

    /** Igual que registrar(), pero un fallo del registro nunca corta la respuesta de la API. */
    public static function intentarRegistrar(int $publicacionId, int $userId, string $evento, string $motivo): void
    {
        // También al log del servidor (docker compose logs web): sirve para ver si la censura actúa.
        error_log("[censura] {$evento} usuario #{$userId}: {$motivo}");

        try {
            $pdo = getPDO();
            self::asegurarTabla($pdo);
            self::registrar($pdo, $publicacionId, $userId, $evento, $motivo);
        } catch (Throwable $e) {
            error_log('[moderacion] ' . $e->getMessage());
        }
    }
}
