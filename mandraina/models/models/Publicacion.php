<?php
// models/Publicacion.php
require_once __DIR__ . '/../config/db.php';

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
    }

    public function getAll(): array
    {
        $sql = "SELECT p.id, p.user_id, p.titulo, p.contenido,
                       p.fecha_creacion, p.fecha_actualizacion,
                       u.nickname
                FROM publicaciones p
                JOIN usuarios u ON u.id = p.user_id
                ORDER BY p.fecha_creacion DESC";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }

    public function getById(int $id): ?array
    {
        $sql = "SELECT p.id, p.user_id, p.titulo, p.contenido,
                       p.fecha_creacion, p.fecha_actualizacion,
                       u.nickname
                FROM publicaciones p
                JOIN usuarios u ON u.id = p.user_id
                WHERE p.id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(int $userId, string $titulo, string $contenido): array
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
        return $this->getById($newId);
    }

    public function update(int $id, string $titulo, string $contenido): ?array
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
        return $this->getById($id);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM publicaciones WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
