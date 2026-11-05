<?php
// models/Notificacion.php
// Avisos para el autor de una publicación cuando alguien le responde.
require_once __DIR__ . '/../config/db.php';

class Notificacion
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = getPDO();
        self::crearTabla($this->pdo);
    }

    // Depende de usuarios, publicaciones y respuestas: se borra sola si se
    // borra cualquiera de ellas (por ejemplo, si la moderación elimina la
    // respuesta, el aviso desaparece con ella).
    public static function crearTabla(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS notificaciones (
                id SERIAL PRIMARY KEY,
                user_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
                actor_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
                publicacion_id INTEGER NOT NULL REFERENCES publicaciones(id) ON DELETE CASCADE,
                respuesta_id INTEGER NOT NULL REFERENCES respuestas(id) ON DELETE CASCADE,
                leida BOOLEAN NOT NULL DEFAULT FALSE,
                fecha_creacion TIMESTAMP NOT NULL DEFAULT NOW()
            )
        ");
        $pdo->exec("
            CREATE INDEX IF NOT EXISTS idx_notificaciones_usuario
            ON notificaciones (user_id, leida, fecha_creacion DESC)
        ");
    }

    public function crear(int $destinatarioId, int $actorId, int $publicacionId, int $respuestaId): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO notificaciones (user_id, actor_id, publicacion_id, respuesta_id)
            VALUES (:uid, :actor, :pid, :rid)
        ");
        $stmt->execute([
            ':uid' => $destinatarioId,
            ':actor' => $actorId,
            ':pid' => $publicacionId,
            ':rid' => $respuestaId,
        ]);
    }

    /** Las más recientes primero, con quién respondió y a qué publicación. */
    public function listar(int $userId, int $limite = 20): array
    {
        $limite = max(1, min(50, $limite));
        $stmt = $this->pdo->prepare("
            SELECT n.id, n.publicacion_id, n.respuesta_id, n.actor_id, n.leida,
                   n.fecha_creacion,
                   u.nickname AS actor_nickname,
                   p.titulo AS publicacion_titulo,
                   LEFT(r.contenido, 100) AS respuesta_extracto
            FROM notificaciones n
            JOIN usuarios u ON u.id = n.actor_id
            JOIN publicaciones p ON p.id = n.publicacion_id
            JOIN respuestas r ON r.id = n.respuesta_id
            WHERE n.user_id = :uid
            ORDER BY n.fecha_creacion DESC, n.id DESC
            LIMIT {$limite}
        ");
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    }

    public function contarNoLeidas(int $userId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM notificaciones WHERE user_id = :uid AND leida = FALSE");
        $stmt->execute([':uid' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    /** Marca como leída una notificación (o todas, con $id null). Solo las propias. */
    public function marcarLeidas(int $userId, ?int $id = null): void
    {
        if ($id === null) {
            $stmt = $this->pdo->prepare("UPDATE notificaciones SET leida = TRUE WHERE user_id = :uid AND leida = FALSE");
            $stmt->execute([':uid' => $userId]);
            return;
        }
        $stmt = $this->pdo->prepare("UPDATE notificaciones SET leida = TRUE WHERE user_id = :uid AND id = :id");
        $stmt->execute([':uid' => $userId, ':id' => $id]);
    }
}
