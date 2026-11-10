<?php
// controllers/RespuestaController.php
require_once __DIR__ . '/../models/Publicacion.php';
require_once __DIR__ . '/../models/Respuesta.php';
require_once __DIR__ . '/../models/Notificacion.php';
require_once __DIR__ . '/../includes/Feed.php';
require_once __DIR__ . '/../includes/Cola.php';
require_once __DIR__ . '/../includes/RateLimiter.php';
require_once __DIR__ . '/../includes/Texto.php';
require_once __DIR__ . '/../includes/FiltroContenido.php';
require_once __DIR__ . '/../includes/Moderacion.php';
require_once __DIR__ . '/../includes/Admin.php';

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

    // Avisa al autor de la publicación de que le respondieron. No se avisa
    // cuando alguien responde en su propia publicación, y un fallo acá nunca
    // tiene que impedir que la respuesta se guarde.
    private function avisarAlAutor(array $publicacion, int $autorRespuestaId, int $respuestaId): void
    {
        $destinatarioId = (int) $publicacion['user_id'];
        if ($destinatarioId === $autorRespuestaId) {
            return;
        }

        try {
            (new Notificacion())->crear($destinatarioId, $autorRespuestaId, (int) $publicacion['id'], $respuestaId);
        } catch (Throwable $e) {
            error_log('[notificaciones] ' . $e->getMessage());
        }
    }

    public function index(int $publicacionId): void
    {
        if (!$this->publicaciones()->getById($publicacionId)) {
            $this->jsonResponse(404, ['error' => 'Publicación no encontrada', 'id' => $publicacionId]);
        }

        $respuestas = $this->model()->getByPublicacion($publicacionId);
        // Hasta que el worker la elimine, una respuesta con lenguaje indebido no se muestra.
        $visibles = array_values(array_filter(
            $respuestas,
            fn (array $r): bool => !FiltroContenido::esVulgar($r['contenido'] ?? '')
        ));
        $this->jsonResponse(200, $visibles);
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

        $publicacion = $this->publicaciones()->getById($publicacionId);
        if (!$publicacion) {
            $this->jsonResponse(404, ['error' => 'Publicación no encontrada', 'id' => $publicacionId]);
        }

        // Censura: una respuesta con lenguaje indebido directamente no se guarda.
        $insultos = FiltroContenido::contarInsultos($contenido);
        if ($insultos > 0) {
            Moderacion::intentarRegistrar($publicacionId, $userId, 'respuesta.creada', "respuesta_rechazada_por_lenguaje_indebido ({$insultos})");
            $this->jsonResponse(422, ['error' => Moderacion::MENSAJE_RESPUESTA]);
        }

        $nueva = $this->model()->create($publicacionId, $userId, $contenido);
        $this->avisarAlAutor($publicacion, $userId, (int) $nueva['id']);
        $this->notificarCambio('respuesta.creada', $publicacionId, (int) $nueva['id'], $userId);
        $this->jsonResponse(201, $nueva);
    }

    public function destroy(int $id): void
    {
        $userId = $this->requireAuth();
        $respuesta = $this->model()->getById($id);

        if (!$respuesta) {
            $this->jsonResponse(404, ['error' => 'Respuesta no encontrada', 'id' => $id]);
        }

        // La autora puede borrar la suya; el administrador, cualquiera.
        $esAutor = (int) $respuesta['user_id'] === $userId;
        if (!$esAutor && !Admin::esAdmin()) {
            $this->jsonResponse(403, ['error' => 'No podés eliminar una respuesta que no te pertenece.']);
        }

        $this->model()->delete($id);
        if (!$esAutor) {
            error_log("[admin] {$_SESSION['nickname']} (#{$userId}) eliminó la respuesta #{$id} de {$respuesta['nickname']} (#{$respuesta['user_id']})");
        }
        $this->notificarCambio('respuesta.eliminada', (int) $respuesta['publicacion_id'], $id, $userId);
        http_response_code(204);
        exit;
    }
}
