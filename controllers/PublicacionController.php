<?php
// controllers/PublicacionController.php
require_once __DIR__ . '/../models/Publicacion.php';
require_once __DIR__ . '/../includes/Feed.php';
require_once __DIR__ . '/../includes/Cola.php';
require_once __DIR__ . '/../includes/RateLimiter.php';
require_once __DIR__ . '/../includes/Texto.php';
require_once __DIR__ . '/../includes/Tags.php';
require_once __DIR__ . '/../includes/FiltroContenido.php';
require_once __DIR__ . '/../includes/Admin.php';

class PublicacionController
{
    // Máximo de publicaciones nuevas por usuario en la ventana indicada.
    private const POSTS_MAX = 10;
    private const POSTS_VENTANA = 60; // segundos

    // Límites de longitud en caracteres. Deben coincidir con los maxlength
    // del formulario de panel.php.
    private const TITULO_MAX = 100;
    private const CONTENIDO_MAX = 2000;

    private ?Publicacion $model = null;

    // El modelo se crea recién cuando hace falta: así un GET del feed que
    // sale de Redis (cache HIT) no abre ninguna conexión a PostgreSQL.
    private function model(): Publicacion
    {
        return $this->model ??= new Publicacion();
    }

    private function jsonResponse(int $status, array $data): void
    {
        http_response_code($status);
        echo json_encode($data);
        exit;
    }

    // Devuelve [titulo, contenido] ya normalizados o responde 400.
    private function validarCampos(array $body): array
    {
        $titulo = Texto::limpiar($body['titulo'] ?? '', true);
        $contenido = Texto::limpiar($body['contenido'] ?? '');

        if ($titulo === '' || $contenido === '') {
            $this->jsonResponse(400, ['error' => 'Los campos "titulo" y "contenido" son obligatorios.']);
        }

        if (mb_strlen($titulo) > self::TITULO_MAX) {
            $this->jsonResponse(400, ['error' => 'El título no puede superar los ' . self::TITULO_MAX . ' caracteres.']);
        }

        if (mb_strlen($contenido) > self::CONTENIDO_MAX) {
            $this->jsonResponse(400, ['error' => 'El contenido no puede superar los ' . self::CONTENIDO_MAX . ' caracteres.']);
        }

        return [$titulo, $contenido];
    }

    // Devuelve los tags normalizados o responde 400.
    private function validarTags(mixed $valor): array
    {
        $tags = Tags::parsear($valor);

        if (count($tags) > Tags::MAX_POR_PUBLICACION) {
            $this->jsonResponse(400, ['error' => 'Podés usar hasta ' . Tags::MAX_POR_PUBLICACION . ' tags por publicación.']);
        }

        foreach ($tags as $tag) {
            if (mb_strlen($tag) > Tags::LARGO_MAX) {
                $this->jsonResponse(400, ['error' => 'Cada tag puede tener hasta ' . Tags::LARGO_MAX . ' caracteres.']);
            }

            if (FiltroContenido::esVulgar($tag)) {
                $this->jsonResponse(400, ['error' => 'El tag "' . $tag . '" no está permitido.']);
            }
        }

        return $tags;
    }

    private function requireAuth(): int
    {
        if (empty($_SESSION['user_id'])) {
            $this->jsonResponse(401, ['error' => 'No autenticado. Iniciá sesión para continuar.']);
        }
        return (int) $_SESSION['user_id'];
    }

    // Candado corto en Redis (por usuario) para que dos envíos simultáneos no
    // se pisen. Si otro envío lo tiene, se espera hasta ~3 s a que termine.
    // Devuelve la clave si lo tomó, o null (sin Redis, o no se pudo: no bloquea).
    private function tomarCandado(string $nombre, int $userId): ?string
    {
        $redis = getRedis();
        if ($redis === null) {
            return null;
        }

        $clave = "lock:{$nombre}:{$userId}";
        try {
            for ($i = 0; $i < 10; $i++) {
                if ((string) $redis->set($clave, '1', 'EX', 10, 'NX') === 'OK') {
                    return $clave;
                }
                usleep(300000);
            }
        } catch (Throwable $e) {
            error_log('[candado] ' . $e->getMessage());
        }
        return null;
    }

    private function soltarCandado(?string $clave): void
    {
        if ($clave === null) {
            return;
        }
        try {
            getRedis()?->del([$clave]);
        } catch (Throwable $e) {
            // expira solo a los 10 s
        }
    }

    private function requireAdmin(): int
    {
        $userId = $this->requireAuth();
        if (!Admin::esAdmin()) {
            $this->jsonResponse(403, ['error' => 'Solo el administrador puede hacer esto.']);
        }
        return $userId;
    }

    // GET /api/v1/duplicadas.php: cuántas copias hay (no borra nada).
    public function duplicadas(): void
    {
        $this->requireAdmin();
        $dups = $this->model()->buscarDuplicadas();
        $this->jsonResponse(200, [
            'cantidad' => count($dups),
            'respuestas_afectadas' => array_sum(array_column($dups, 'respuestas')),
            'duplicadas' => $dups,
        ]);
    }

    // DELETE /api/v1/duplicadas.php: elimina las copias y conserva la más antigua de cada grupo.
    public function eliminarDuplicadas(): void
    {
        $userId = $this->requireAdmin();
        $dups = $this->model()->eliminarDuplicadas();

        if ($dups) {
            Feed::invalidar();
            error_log("[admin] {$_SESSION['nickname']} (#{$userId}) eliminó " . count($dups)
                . ' publicaciones duplicadas (#' . implode(', #', array_column($dups, 'id')) . ')');
        }

        $this->jsonResponse(200, [
            'eliminadas' => count($dups),
            'respuestas_afectadas' => array_sum(array_column($dups, 'respuestas')),
        ]);
    }

    // Tras cualquier escritura: se descarta el feed cacheado y se avisa por
    // RabbitMQ para que el worker haga las tareas secundarias (moderación,
    // recalentar la caché).
    private function notificarCambio(string $evento, int $publicacionId, int $userId): void
    {
        Feed::invalidar();
        Cola::publicar($evento, [
            'publicacion_id' => $publicacionId,
            'user_id' => $userId,
        ]);
    }

    // GET /api/v1/publicaciones?limite=10&antes={id}&tag={tag}
    // Una página del feed, de la más nueva a la más vieja:
    //   limite  cuántas publicaciones (1 a Publicacion::LIMITE_MAX, por defecto Feed::PAGINA)
    //   antes   id de la última publicación que el cliente ya tiene; devuelve las anteriores
    //   tag     solo las publicaciones con ese tag
    // Respuesta: {"publicaciones": [...], "hay_mas": bool, "siguiente": id|null}
    public function index(): void
    {
        $limite = isset($_GET['limite']) && is_string($_GET['limite'])
            ? (int) $_GET['limite']
            : Feed::PAGINA;
        $limite = max(1, min(Publicacion::LIMITE_MAX, $limite));

        $antes = isset($_GET['antes']) && is_string($_GET['antes']) && ctype_digit($_GET['antes'])
            ? (int) $_GET['antes']
            : null;

        $tag = isset($_GET['tag']) && is_string($_GET['tag'])
            ? (Tags::parsear($_GET['tag'])[0] ?? null)
            : null;

        $pagina = Feed::pagina(fn() => $this->model(), $limite, $antes, $tag, $hit);
        header('X-Cache: ' . ($hit ? 'HIT' : 'MISS'));
        $this->jsonResponse(200, $pagina);
    }

    // GET /api/v1/tags.php: tags en los que participa el usuario.
    public function misTags(): void
    {
        $userId = $this->requireAuth();
        $this->jsonResponse(200, $this->model()->tagsDeParticipacion($userId));
    }

    // GET /api/v1/tags.php?alcance=general: tags más usados por todos.
    public function tagsGenerales(): void
    {
        $this->requireAuth();
        $this->jsonResponse(200, $this->model()->tagsPopulares());
    }

    public function show(int $id): void
    {
        $publicacion = $this->model()->getById($id);

        if (!$publicacion) {
            $this->jsonResponse(404, ['error' => 'Publicación no encontrada', 'id' => $id]);
        }

        $this->jsonResponse(200, Feed::marcar($publicacion));
    }

    public function store(array $body): void
    {
        $userId = $this->requireAuth();

        if (!RateLimiter::permitir('post', (string) $userId, self::POSTS_MAX, self::POSTS_VENTANA)) {
            header('Retry-After: ' . self::POSTS_VENTANA);
            $this->jsonResponse(429, ['error' => 'Estás publicando demasiado rápido. Esperá un minuto.']);
        }

        [$titulo, $contenido] = $this->validarCampos($body);
        $tags = $this->validarTags($body['tags'] ?? []);

        // Un solo envío a la vez por usuario (Redis), y sin duplicar: si llega
        // dos veces lo mismo (doble clic, reintento), la segunda devuelve la
        // publicación ya creada en vez de crear otra.
        $candado = $this->tomarCandado('post', $userId);
        try {
            [$nueva, $creada] = $this->model()->crearSinDuplicar($userId, $titulo, $contenido, $tags);
        } finally {
            $this->soltarCandado($candado);
        }

        if (!$creada) {
            $this->jsonResponse(200, Feed::marcar($nueva)); // ya existía: no hay evento ni feed nuevo
        }

        $this->notificarCambio('publicacion.creada', (int) $nueva['id'], $userId);
        $this->jsonResponse(201, Feed::marcar($nueva));
    }

    public function update(int $id, array $body): void
    {
        $userId = $this->requireAuth();
        $publicacion = $this->model()->getById($id);

        if (!$publicacion) {
            $this->jsonResponse(404, ['error' => 'Publicación no encontrada', 'id' => $id]);
        }

        if ((int) $publicacion['user_id'] !== $userId) {
            $this->jsonResponse(403, ['error' => 'No podés editar una publicación que no te pertenece.']);
        }

        [$titulo, $contenido] = $this->validarCampos($body);
        $tags = array_key_exists('tags', $body) ? $this->validarTags($body['tags']) : null;

        $actualizada = $this->model()->update($id, $titulo, $contenido, $tags);
        $this->notificarCambio('publicacion.editada', $id, $userId);
        $this->jsonResponse(200, Feed::marcar($actualizada));
    }

    public function destroy(int $id): void
    {
        $userId = $this->requireAuth();
        $publicacion = $this->model()->getById($id);

        if (!$publicacion) {
            $this->jsonResponse(404, ['error' => 'Publicación no encontrada', 'id' => $id]);
        }

        // El autor puede borrar la suya; el administrador, cualquiera.
        $esAutor = (int) $publicacion['user_id'] === $userId;
        if (!$esAutor && !Admin::esAdmin()) {
            $this->jsonResponse(403, ['error' => 'No podés eliminar una publicación que no te pertenece.']);
        }

        $this->model()->delete($id);
        if (!$esAutor) {
            error_log("[admin] {$_SESSION['nickname']} (#{$userId}) eliminó la publicación #{$id} de {$publicacion['nickname']} (#{$publicacion['user_id']})");
        }
        $this->notificarCambio('publicacion.eliminada', $id, $userId);
        http_response_code(204);
        exit;
    }
}
