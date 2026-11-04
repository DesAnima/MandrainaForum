<?php
// controllers/RespuestaController.php
require_once __DIR__ . '/../models/Publicacion.php';
require_once __DIR__ . '/../models/Respuesta.php';
require_once __DIR__ . '/../includes/Feed.php';
require_once __DIR__ . '/../includes/Cola.php';
require_once __DIR__ . '/../includes/RateLimiter.php';
require_once __DIR__ . '/../includes/Texto.php';
require_once __DIR__ . '/../includes/FiltroContenido.php';

class RespuestaController
{
    // Máximo de respuestas nuevas por usuario en la ventana indicada.
    private const RESPUESTAS_MAX = 20;
    private const RESPUESTAS_VENTANA = 60; // segundos

    // Longitud máxima en caracteres. Debe coincidir con el maxlength de panel.php.
    private const CONTENIDO_MAX = 500;

    private ?Publicacion $publicaciones = null;
    private ?Respuesta $model = null;

    private function publicaciones(): Publicacion
    {
        return $this->publicaciones ??= new Publicacion();
    }

    private function model(): Respuesta
    {
        return $this->model ??= new Respuesta();
    }

    private function jsonResponse(int $status, array $data): void
    {
        http_response_code($status);
        echo json_encode($data);
        exit;
    }

    private function requireAuth(): int
    {
        if (empty($_SESSION['user_id'])) {
            $this->jsonResponse(401, ['error' => 'No autenticado. Iniciá sesión para continuar.']);
        }
        return (int) $_SESSION['user_id'];
    }

    /** Agrega "contenido_sensible" (true/false) a una respuesta. */
    private function marcar(array $respuesta): array
    {
        $respuesta['contenido_sensible'] = FiltroContenido::esVulgar($respuesta['contenido'] ?? '');
        return $respuesta;
    }

    // Tras cualquier escritura: se descarta el feed cacheado (el listado
    // incluye el total de respuestas) y se avisa por RabbitMQ para que el
    // worker haga las tareas secundarias.
    private function notificarCambio(string $evento, int $publicacionId, int $respuestaId, int $userId): void
    {
        Feed::invalidar();
        Cola::publicar($evento, [
            'publicacion_id' => $publicacionId,
            'respuesta_id' => $respuestaId,
            'user_id' => $userId,
        ]);
    }

    public function index(int $publicacionId): void
    {
        if (!$this->publicaciones()->getById($publicacionId)) {
            $this->jsonResponse(404, ['error' => 'Publicación no encontrada', 'id' => $publicacionId]);
        }

        $respuestas = $this->model()->getByPublicacion($publicacionId);
        $this->jsonResponse(200, array_map([$this, 'marcar'], $respuestas));
    }

    public function store(array $body): void
    {
        $userId = $this->requireAuth();

        if (!RateLimiter::permitir('respuesta', (string) $userId, self::RESPUESTAS_MAX, self::RESPUESTAS_VENTANA)) {
            header('Retry-After: ' . self::RESPUESTAS_VENTANA);
            $this->jsonResponse(429, ['error' => 'Estás respondiendo demasiado rápido. Esperá un minuto.']);
        }

        $publicacionId = (int) ($body['publicacion_id'] ?? 0);
        $contenido = Texto::limpiar($body['contenido'] ?? '');

        if ($publicacionId <= 0 || $contenido === '') {
            $this->jsonResponse(400, ['error' => 'Los campos "publicacion_id" y "contenido" son obligatorios.']);
        }

        if (mb_strlen($contenido) > self::CONTENIDO_MAX) {
            $this->jsonResponse(400, ['error' => 'La respuesta no puede superar los ' . self::CONTENIDO_MAX . ' caracteres.']);
        }

        if (!$this->publicaciones()->getById($publicacionId)) {
            $this->jsonResponse(404, ['error' => 'Publicación no encontrada', 'id' => $publicacionId]);
        }

        $nueva = $this->model()->create($publicacionId, $userId, $contenido);
        $this->notificarCambio('respuesta.creada', $publicacionId, (int) $nueva['id'], $userId);
        $this->jsonResponse(201, $this->marcar($nueva));
    }

    public function destroy(int $id): void
    {
        $userId = $this->requireAuth();
        $respuesta = $this->model()->getById($id);

        if (!$respuesta) {
            $this->jsonResponse(404, ['error' => 'Respuesta no encontrada', 'id' => $id]);
        }

        if ((int) $respuesta['user_id'] !== $userId) {
            $this->jsonResponse(403, ['error' => 'No podés eliminar una respuesta que no te pertenece.']);
        }

        $this->model()->delete($id);
        $this->notificarCambio('respuesta.eliminada', (int) $respuesta['publicacion_id'], $id, $userId);
        http_response_code(204);
        exit;
    }
}
