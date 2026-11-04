<?php
// controllers/PublicacionController.php
require_once __DIR__ . '/../models/Publicacion.php';
require_once __DIR__ . '/../includes/Feed.php';
require_once __DIR__ . '/../includes/Cola.php';
require_once __DIR__ . '/../includes/RateLimiter.php';
require_once __DIR__ . '/../includes/Texto.php';
require_once __DIR__ . '/../includes/Tags.php';
require_once __DIR__ . '/../includes/FiltroContenido.php';

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

    public function index(): void
    {
        $feed = Feed::obtener(fn() => $this->model(), $hit);
        header('X-Cache: ' . ($hit ? 'HIT' : 'MISS'));

        // ?tag=musica: solo las publicaciones con ese tag. El filtro se aplica
        // sobre el feed cacheado, así que no cuesta una consulta extra.
        $filtro = isset($_GET['tag']) && is_string($_GET['tag'])
            ? (Tags::parsear($_GET['tag'])[0] ?? null)
            : null;

        if ($filtro !== null) {
            $feed = array_values(array_filter(
                $feed,
                fn($p) => in_array($filtro, $p['tags'] ?? [], true)
            ));
        }

        $this->jsonResponse(200, $feed);
    }

    // GET /api/v1/tags.php: tags en los que participa el usuario.
    public function misTags(): void
    {
        $userId = $this->requireAuth();
        $this->jsonResponse(200, $this->model()->tagsDeParticipacion($userId));
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

        $nueva = $this->model()->create($userId, $titulo, $contenido, $tags);
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

        if ((int) $publicacion['user_id'] !== $userId) {
            $this->jsonResponse(403, ['error' => 'No podés eliminar una publicación que no te pertenece.']);
        }

        $this->model()->delete($id);
        $this->notificarCambio('publicacion.eliminada', $id, $userId);
        http_response_code(204);
        exit;
    }
}
