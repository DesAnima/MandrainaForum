<?php
// models/Publicacion.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/Respuesta.php';
require_once __DIR__ . '/Reaccion.php';

class Publicacion
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = getPDO();
        $this->ensureTable();
    }

    private function ensureTable(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS publicaciones (
                id SERIAL PRIMARY KEY,
                user_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
                titulo TEXT NOT NULL,
                contenido TEXT NOT NULL,
                fecha_creacion TIMESTAMP NOT NULL DEFAULT NOW(),
                fecha_actualizacion TIMESTAMP
            )
        ");
        // El listado cuenta las respuestas de cada publicación.
        Respuesta::crearTabla($this->pdo);
        // ...y también las reacciones (likes / dislikes).
        Reaccion::crearTabla($this->pdo);
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS publicacion_tags (
                publicacion_id INTEGER NOT NULL REFERENCES publicaciones(id) ON DELETE CASCADE,
                tag TEXT NOT NULL,
                PRIMARY KEY (publicacion_id, tag)
            )
        ");
    }

    // Convierte la columna auxiliar tags_csv en la lista "tags".
    private function armarFila(array $fila): array
    {
        $csv = (string) ($fila['tags_csv'] ?? '');
        unset($fila['tags_csv']);
        $fila['tags'] = $csv === '' ? [] : explode(',', $csv);
        return $fila;
    }

    private function guardarTags(int $publicacionId, array $tags): void
    {
        if (!$tags) {
            return;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO publicacion_tags (publicacion_id, tag)
            VALUES (:id, :tag)
            ON CONFLICT DO NOTHING
        ");
        foreach ($tags as $tag) {
            $stmt->execute([':id' => $publicacionId, ':tag' => $tag]);
        }
    }

    /**
     * Tags en los que participa un usuario (publicó o respondió en una
     * publicación con ese tag), con el total de publicaciones de cada tag.
     */
    public function tagsDeParticipacion(int $userId): array
    {
        $sql = "SELECT t.tag, COUNT(*)::int AS total
                FROM publicacion_tags t
                WHERE t.tag IN (
                    SELECT pt.tag
                    FROM publicacion_tags pt
                    WHERE pt.publicacion_id IN (
                        SELECT p.id FROM publicaciones p WHERE p.user_id = :uid_posts
                        UNION
                        SELECT r.publicacion_id FROM respuestas r WHERE r.user_id = :uid_resp
                    )
                )
                GROUP BY t.tag
                ORDER BY total DESC, t.tag ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':uid_posts' => $userId, ':uid_resp' => $userId]);
        return $stmt->fetchAll();
    }

    public function getAll(): array
    {
        $sql = "SELECT p.id, p.user_id, p.titulo, p.contenido,
                       p.fecha_creacion, p.fecha_actualizacion,
                       u.nickname,
                       (SELECT COUNT(*) FROM respuestas r
                        WHERE r.publicacion_id = p.id)::int AS total_respuestas,
                       (SELECT COUNT(*) FROM reacciones x
                        WHERE x.publicacion_id = p.id AND x.tipo = 'like')::int AS likes,
                       (SELECT COUNT(*) FROM reacciones x
                        WHERE x.publicacion_id = p.id AND x.tipo = 'dislike')::int AS dislikes,
                       COALESCE((SELECT string_agg(pt.tag, ',' ORDER BY pt.tag)
                                 FROM publicacion_tags pt
                                 WHERE pt.publicacion_id = p.id), '') AS tags_csv
                FROM publicaciones p
                JOIN usuarios u ON u.id = p.user_id
                ORDER BY p.fecha_creacion DESC";
        $stmt = $this->pdo->query($sql);
        return array_map([$this, 'armarFila'], $stmt->fetchAll());
    }

    public function getById(int $id): ?array
    {
        $sql = "SELECT p.id, p.user_id, p.titulo, p.contenido,
                       p.fecha_creacion, p.fecha_actualizacion,
                       u.nickname,
                       (SELECT COUNT(*) FROM respuestas r
                        WHERE r.publicacion_id = p.id)::int AS total_respuestas,
                       (SELECT COUNT(*) FROM reacciones x
                        WHERE x.publicacion_id = p.id AND x.tipo = 'like')::int AS likes,
                       (SELECT COUNT(*) FROM reacciones x
                        WHERE x.publicacion_id = p.id AND x.tipo = 'dislike')::int AS dislikes,
                       COALESCE((SELECT string_agg(pt.tag, ',' ORDER BY pt.tag)
                                 FROM publicacion_tags pt
                                 WHERE pt.publicacion_id = p.id), '') AS tags_csv
                FROM publicaciones p
                JOIN usuarios u ON u.id = p.user_id
                WHERE p.id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->armarFila($row) : null;
    }

    // Se inserta la publicación y después sus tags, sin transacción explícita
    // (la conexión de Neon pasa por un pooler y esto es lo que ya funcionaba).
    // Si fallan los tags se borra la publicación para no dejarla a medias.
    public function create(int $userId, string $titulo, string $contenido, array $tags = []): array
    {
        $sql = "INSERT INTO publicaciones (user_id, titulo, contenido)
                VALUES (:user_id, :titulo, :contenido)
                RETURNING id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':titulo' => $titulo,
            ':contenido' => $contenido,
        ]);
        $newId = (int) $stmt->fetchColumn();

        if ($newId <= 0) {
            throw new RuntimeException('El INSERT de la publicación no devolvió un id.');
        }

        try {
            $this->guardarTags($newId, $tags);
        } catch (Throwable $e) {
            $this->delete($newId);
            throw $e;
        }

        $nueva = $this->getById($newId);
        if ($nueva === null) {
            throw new RuntimeException("La publicación #{$newId} se insertó pero no se pudo leer de nuevo.");
        }

        return $nueva;
    }

    // Con $tags en null los tags quedan como están; con una lista (aunque
    // sea vacía) se reemplazan por completo.
    public function update(int $id, string $titulo, string $contenido, ?array $tags = null): ?array
    {
        $sql = "UPDATE publicaciones
                SET titulo = :titulo,
                    contenido = :contenido,
                    fecha_actualizacion = NOW()
                WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':titulo' => $titulo,
            ':contenido' => $contenido,
            ':id' => $id,
        ]);

        if ($tags !== null) {
            $del = $this->pdo->prepare("DELETE FROM publicacion_tags WHERE publicacion_id = :id");
            $del->execute([':id' => $id]);
            $this->guardarTags($id, $tags);
        }

        return $this->getById($id);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM publicaciones WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
