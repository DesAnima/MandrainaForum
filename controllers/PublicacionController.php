<?php
// controllers/PublicacionController.php
require_once __DIR__ . '/../models/Publicacion.php';
require_once __DIR__ . '/../includes/Feed.php';
require_once __DIR__ . '/../includes/Cola.php';
require_once __DIR__ . '/../includes/RateLimiter.php';
require_once __DIR__ . '/../includes/Texto.php';

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
        $this->jsonResponse(200, $feed);
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

        $nueva = $this->model()->create($userId, $titulo, $contenido);
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

        $actualizada = $this->model()->update($id, $titulo, $contenido);
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
