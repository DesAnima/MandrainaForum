<?php
// models/Publicacion.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/Respuesta.php';
require_once __DIR__ . '/Reaccion.php';
require_once __DIR__ . '/../includes/Cache.php';

class Publicacion
{
    // Si el mismo usuario envía exactamente el mismo título y contenido dentro
    // de esta ventana, no se crea otra publicación: se devuelve la que ya existe.
    public const VENTANA_ANTIDUPLICADO = 60; // segundos

    // Al limpiar duplicados existentes: una publicación es copia de otra del
    // mismo usuario con igual título y contenido creada hasta este tiempo después.
    public const VENTANA_DUPLICADAS = 600; // segundos (10 minutos)

    // Tope de publicaciones por página en el listado (paginación por cursor).
    public const LIMITE_MAX = 100;

    // Tags generales: cuántos se muestran en la barra, cuántos devuelve "ver
    // todos" como máximo y cuántos puede fijar el administrador.
    public const LIMITE_TAGS_GENERALES = 15;
    public const LIMITE_TAGS_TODOS = 500;
    public const MAX_FIJADOS = 10;

    // Clave en Redis que recuerda que los índices ya se verificaron, así no
    // se repite el CREATE INDEX IF NOT EXISTS en cada request.
    private const CLAVE_INDICES = 'db:indices:v1';

    // Consulta base de una publicación con su autor, conteos y tags. Los
    // conteos se calculan solo para las filas que salen (ya limitadas), con un
    // único acceso a reacciones por publicación.
    private const SELECT_PUBLICACION = "
        SELECT p.id, p.user_id, p.titulo, p.contenido,
               p.fecha_creacion, p.fecha_actualizacion,
               u.nickname,
               (SELECT COUNT(*) FROM respuestas r
                WHERE r.publicacion_id = p.id)::int AS total_respuestas,
               rx.likes, rx.dislikes,
               COALESCE((SELECT string_agg(pt.tag, ',' ORDER BY pt.tag)
                         FROM publicacion_tags pt
                         WHERE pt.publicacion_id = p.id), '') AS tags_csv
        FROM publicaciones p
        JOIN usuarios u ON u.id = p.user_id
        CROSS JOIN LATERAL (
            SELECT COUNT(*) FILTER (WHERE x.tipo = 'like')::int AS likes,
                   COUNT(*) FILTER (WHERE x.tipo = 'dislike')::int AS dislikes
            FROM reacciones x
            WHERE x.publicacion_id = p.id
        ) rx";

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
        // Tags que el administrador dejó fijados arriba en "Tags generales".
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS tags_fijados (
                tag TEXT PRIMARY KEY,
                fecha_creacion TIMESTAMP NOT NULL DEFAULT NOW()
            )
        ");
        $this->asegurarIndices();
    }

    // Índices para las consultas calientes (ver migrations/008_indices.sql).
    // Todos son IF NOT EXISTS; con Redis la verificación se hace una sola vez.
    private function asegurarIndices(): void
    {
        if (Cache::obtener(self::CLAVE_INDICES) === true) {
            return;
        }

        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_publicaciones_user ON publicaciones (user_id, id DESC)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_respuestas_publicacion ON respuestas (publicacion_id, fecha_creacion)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_respuestas_user ON respuestas (user_id)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_reacciones_publicacion_tipo ON reacciones (publicacion_id, tipo)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_reacciones_user ON reacciones (user_id)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_publicacion_tags_tag_pub ON publicacion_tags (tag, publicacion_id)");
        // El índice simple de la migración 005 queda redundante con el anterior.
        $this->pdo->exec("DROP INDEX IF EXISTS idx_publicacion_tags_tag");

        Cache::guardar(self::CLAVE_INDICES, true, 86400);
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

    /**
     * Tags generales: primero los que fijó el administrador (en el orden en que
     * los fijó, aunque ya no tengan publicaciones) y después los más usados por
     * todos. Devuelve como máximo $limite y el total de tags que existen, para
     * saber si hay más que mostrar ("ver todos los tags").
     *
     * @return array{tags: array<int, array{tag:string,total:int,fijado:bool}>, total_tags: int}
     */
    public function tagsGenerales(int $limite = self::LIMITE_TAGS_GENERALES): array
    {
        $limite = max(1, min(self::LIMITE_TAGS_TODOS, $limite));
        $sql = "WITH conteo AS (
                    SELECT tag, COUNT(*)::int AS total
                    FROM publicacion_tags
                    GROUP BY tag
                )
                SELECT COALESCE(c.tag, f.tag) AS tag,
                       COALESCE(c.total, 0) AS total,
                       (f.tag IS NOT NULL) AS fijado,
                       COUNT(*) OVER ()::int AS total_tags
                FROM conteo c
                FULL OUTER JOIN tags_fijados f ON f.tag = c.tag
                ORDER BY (f.tag IS NOT NULL) DESC,
                         f.fecha_creacion ASC,
                         COALESCE(c.total, 0) DESC,
                         COALESCE(c.tag, f.tag) ASC
                LIMIT {$limite}";

        $filas = $this->pdo->query($sql)->fetchAll();

        return [
            'tags' => array_map(fn($f) => [
                'tag' => (string) $f['tag'],
                'total' => (int) $f['total'],
                'fijado' => (bool) $f['fijado'],
            ], $filas),
            'total_tags' => $filas ? (int) $filas[0]['total_tags'] : 0,
        ];
    }

    /** ¿Hay al menos una publicación con este tag? */
    public function existeTag(string $tag): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM publicacion_tags WHERE tag = :tag LIMIT 1");
        $stmt->execute([':tag' => $tag]);
        return (bool) $stmt->fetchColumn();
    }

    public function estaFijado(string $tag): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM tags_fijados WHERE tag = :tag");
        $stmt->execute([':tag' => $tag]);
        return (bool) $stmt->fetchColumn();
    }

    public function cantidadFijados(): int
    {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM tags_fijados")->fetchColumn();
    }

    public function fijarTag(string $tag): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO tags_fijados (tag) VALUES (:tag) ON CONFLICT DO NOTHING");
        $stmt->execute([':tag' => $tag]);
    }

    /** Devuelve false si el tag no estaba fijado. */
    public function desfijarTag(string $tag): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM tags_fijados WHERE tag = :tag");
        $stmt->execute([':tag' => $tag]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Una página del listado, de la más nueva a la más vieja. Paginación por
     * cursor (keyset): "antes" es el id de la última publicación ya cargada y
     * se piden las anteriores a esa. A diferencia de OFFSET, el costo no crece
     * con la profundidad y no se repiten ni se saltan filas si alguien publica
     * mientras se baja. Se pide una fila de más solo para saber si quedan más.
     *
     * @return array{0: array, 1: bool}  [publicaciones, hay_mas]
     */
    public function getPagina(int $limite, ?int $antes = null, ?string $tag = null): array
    {
        $limite = max(1, min(self::LIMITE_MAX, $limite));
        $donde = [];
        $params = [];

        if ($antes !== null) {
            $donde[] = 'p.id < :antes';
            $params[':antes'] = $antes;
        }
        if ($tag !== null) {
            $donde[] = 'EXISTS (SELECT 1 FROM publicacion_tags f
                                WHERE f.publicacion_id = p.id AND f.tag = :tag)';
            $params[':tag'] = $tag;
        }

        $sql = self::SELECT_PUBLICACION
            . ($donde ? ' WHERE ' . implode(' AND ', $donde) : '')
            . ' ORDER BY p.id DESC LIMIT ' . ($limite + 1);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll();

        $hayMas = count($filas) > $limite;
        if ($hayMas) {
            array_pop($filas);
        }

        return [array_map([$this, 'armarFila'], $filas), $hayMas];
    }

    public function getById(int $id): ?array
    {
        $sql = self::SELECT_PUBLICACION . ' WHERE p.id = :id';
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

    /**
     * Como create(), pero sin duplicar: si el mismo usuario ya publicó
     * exactamente lo mismo en los últimos VENTANA_ANTIDUPLICADO segundos
     * (doble clic, reintento del navegador...), devuelve esa publicación.
     * El control va en el propio INSERT (una sola sentencia atómica).
     *
     * @return array{0: array, 1: bool}  [publicación, true si se creó ahora]
     */
    public function crearSinDuplicar(int $userId, string $titulo, string $contenido, array $tags = []): array
    {
        // Parámetros con nombres distintos: PDO con consultas nativas no
        // permite repetir un mismo nombre (ver tagsDeParticipacion).
        $sql = "INSERT INTO publicaciones (user_id, titulo, contenido)
                SELECT CAST(:user_id AS integer), CAST(:titulo AS text), CAST(:contenido AS text)
                WHERE NOT EXISTS (
                    SELECT 1 FROM publicaciones
                    WHERE user_id = :user_id2 AND titulo = :titulo2 AND contenido = :contenido2
                      AND fecha_creacion >= NOW() - (CAST(:ventana AS integer) * INTERVAL '1 second')
                )
                RETURNING id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId, ':titulo' => $titulo, ':contenido' => $contenido,
            ':user_id2' => $userId, ':titulo2' => $titulo, ':contenido2' => $contenido,
            ':ventana' => self::VENTANA_ANTIDUPLICADO,
        ]);
        $newId = $stmt->fetchColumn();

        if ($newId === false) {
            // Ya había una igual y reciente: se devuelve esa.
            $sel = $this->pdo->prepare("
                SELECT id FROM publicaciones
                WHERE user_id = :u AND titulo = :t AND contenido = :c
                ORDER BY id DESC LIMIT 1
            ");
            $sel->execute([':u' => $userId, ':t' => $titulo, ':c' => $contenido]);
            $existente = $this->getById((int) $sel->fetchColumn());
            if ($existente === null) {
                throw new RuntimeException('No se pudo crear ni encontrar la publicación.');
            }
            return [$existente, false];
        }

        $newId = (int) $newId;
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
        return [$nueva, true];
    }

    private static function normalizarTexto(string $texto): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $texto)));
    }

    /**
     * Publicaciones duplicadas que ya existen: mismo usuario, mismo título y
     * contenido (sin distinguir mayúsculas ni espacios de más), creadas dentro
     * de VENTANA_DUPLICADAS de la original. Se conserva siempre la más antigua.
     *
     * @return array<int, array{id:int, original_id:int, respuestas:int}>
     */
    public function buscarDuplicadas(): array
    {
        $sql = "SELECT p.id, p.user_id, p.titulo, p.contenido,
                       EXTRACT(EPOCH FROM p.fecha_creacion)::bigint AS ts,
                       (SELECT COUNT(*) FROM respuestas r
                        WHERE r.publicacion_id = p.id)::int AS respuestas
                FROM publicaciones p
                ORDER BY p.fecha_creacion ASC, p.id ASC";

        $conservadas = []; // huella -> [id, ts] de la última publicación que se conserva
        $duplicadas = [];

        foreach ($this->pdo->query($sql) as $fila) {
            $huella = md5($fila['user_id'] . '|' . self::normalizarTexto($fila['titulo'])
                . '|' . self::normalizarTexto($fila['contenido']));
            $ts = (int) $fila['ts'];

            if (isset($conservadas[$huella]) && $ts - $conservadas[$huella]['ts'] <= self::VENTANA_DUPLICADAS) {
                $duplicadas[] = [
                    'id' => (int) $fila['id'],
                    'original_id' => $conservadas[$huella]['id'],
                    'respuestas' => (int) $fila['respuestas'],
                ];
                continue;
            }

            $conservadas[$huella] = ['id' => (int) $fila['id'], 'ts' => $ts];
        }

        return $duplicadas;
    }

    /** Elimina las copias que encuentra buscarDuplicadas() y las devuelve. */
    public function eliminarDuplicadas(): array
    {
        $duplicadas = $this->buscarDuplicadas();

        foreach (array_chunk(array_column($duplicadas, 'id'), 100) as $lote) {
            $marcas = implode(',', array_fill(0, count($lote), '?'));
            $stmt = $this->pdo->prepare("DELETE FROM publicaciones WHERE id IN ({$marcas})");
            $stmt->execute($lote);
        }

        return $duplicadas;
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
