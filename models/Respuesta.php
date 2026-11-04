<?php
// models/Respuesta.php
require_once __DIR__ . '/../config/db.php';

class Respuesta
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = getPDO();
        self::crearTabla($this->pdo);
    }

    // También se llama desde Publicacion: el listado de publicaciones cuenta
    // las respuestas, así que la tabla tiene que existir siempre.
    public static function crearTabla(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS respuestas (
                id SERIAL PRIMARY KEY,
                publicacion_id INTEGER NOT NULL REFERENCES publicaciones(id) ON DELETE CASCADE,
                user_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
                contenido TEXT NOT NULL,
                fecha_creacion TIMESTAMP NOT NULL DEFAULT NOW()
            )
        ");
    }

    public function getByPublicacion(int $publicacionId): array
    {
        $sql = "SELECT r.id, r.publicacion_id, r.user_id, r.contenido,
                       r.fecha_creacion, u.nickname
                FROM respuestas r
                JOIN usuarios u ON u.id = r.user_id
                WHERE r.publicacion_id = :pid
                ORDER BY r.fecha_creacion ASC, r.id ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':pid' => $publicacionId]);
        return $stmt->fetchAll();
    }

    public function getById(int $id): ?array
    {
        $sql = "SELECT r.id, r.publicacion_id, r.user_id, r.contenido,
                       r.fecha_creacion, u.nickname
                FROM respuestas r
                JOIN usuarios u ON u.id = r.user_id
                WHERE r.id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(int $publicacionId, int $userId, string $contenido): array
    {
        $sql = "INSERT INTO respuestas (publicacion_id, user_id, contenido)
                VALUES (:pid, :uid, :contenido)
                RETURNING id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':pid' => $publicacionId,
            ':uid' => $userId,
            ':contenido' => $contenido,
        ]);
        return $this->getById((int) $stmt->fetchColumn());
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM respuestas WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
