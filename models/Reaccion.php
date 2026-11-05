<?php
// models/Reaccion.php
// Likes (mandarina) y dislikes (tomate) de las publicaciones.
//
// La clave primaria (publicacion_id, user_id) garantiza a nivel de base de
// datos que cada usuario tiene UNA sola reacción por publicación: like o
// dislike, nunca las dos a la vez. Puede cambiarla de una a otra.

require_once __DIR__ . '/../config/db.php';

class Reaccion
{
    public const LIKE = 'like';
    public const DISLIKE = 'dislike';

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = getPDO();

        // En régimen normal basta una consulta barata para saber que la tabla
        // existe. Solo si falta se crean todas las tablas (en orden, porque
        // reacciones depende de publicaciones y usuarios).
        $existe = $this->pdo->query("SELECT to_regclass('reacciones') IS NOT NULL")->fetchColumn();
        if (!$existe) {
            require_once __DIR__ . '/Publicacion.php'; // su constructor crea todo, reacciones incluida
            new Publicacion();
        }
    }

    /** user_id del autor de una publicación, o null si no existe. */
    public function autorDePublicacion(int $publicacionId): ?int
    {
        $stmt = $this->pdo->prepare("SELECT user_id FROM publicaciones WHERE id = :id");
        $stmt->execute([':id' => $publicacionId]);
        $valor = $stmt->fetchColumn();
        return $valor === false ? null : (int) $valor;
    }

    /** @return array{likes:int, dislikes:int} */
    public function conteos(int $publicacionId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FILTER (WHERE tipo = 'like')::int AS likes,
                   COUNT(*) FILTER (WHERE tipo = 'dislike')::int AS dislikes
            FROM reacciones WHERE publicacion_id = :pid
        ");
        $stmt->execute([':pid' => $publicacionId]);
        $fila = $stmt->fetch();
        return ['likes' => (int) $fila['likes'], 'dislikes' => (int) $fila['dislikes']];
    }

    // También se llama desde Publicacion: el listado cuenta las reacciones,
    // así que la tabla tiene que existir siempre.
    public static function crearTabla(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS reacciones (
                publicacion_id INTEGER NOT NULL REFERENCES publicaciones(id) ON DELETE CASCADE,
                user_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
                tipo TEXT NOT NULL CHECK (tipo IN ('like', 'dislike')),
                fecha_creacion TIMESTAMP NOT NULL DEFAULT NOW(),
                PRIMARY KEY (publicacion_id, user_id)
            )
        ");
    }

    public static function tipoValido(mixed $tipo): bool
    {
        return $tipo === self::LIKE || $tipo === self::DISLIKE;
    }

    /**
     * Registra la reacción o, si el usuario ya tenía la contraria, la cambia
     * (una sola fila por usuario y publicación, así nunca hay like y dislike
     * a la vez). Devuelve false si ya tenía exactamente esa reacción.
     */
    public function guardar(int $publicacionId, int $userId, string $tipo): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO reacciones (publicacion_id, user_id, tipo)
            VALUES (:pid, :uid, :tipo)
            ON CONFLICT (publicacion_id, user_id) DO UPDATE
                SET tipo = EXCLUDED.tipo, fecha_creacion = NOW()
                WHERE reacciones.tipo <> EXCLUDED.tipo
        ");
        $stmt->execute([':pid' => $publicacionId, ':uid' => $userId, ':tipo' => $tipo]);
        return $stmt->rowCount() > 0;
    }

    /** @return array<int,string> publicacion_id => 'like'|'dislike' de un usuario. */
    public function deUsuario(int $userId): array
    {
        $stmt = $this->pdo->prepare("SELECT publicacion_id, tipo FROM reacciones WHERE user_id = :uid");
        $stmt->execute([':uid' => $userId]);

        $mapa = [];
        foreach ($stmt->fetchAll() as $fila) {
            $mapa[(int) $fila['publicacion_id']] = $fila['tipo'];
        }
        return $mapa;
    }
}
