<?php
// controllers/ReaccionController.php
require_once __DIR__ . '/../models/Publicacion.php';
require_once __DIR__ . '/../models/Reaccion.php';
require_once __DIR__ . '/../includes/Feed.php';
require_once __DIR__ . '/../includes/RateLimiter.php';

class ReaccionController
{
    // Máximo de reacciones por usuario en la ventana indicada.
    private const REACCIONES_MAX = 30;
    private const REACCIONES_VENTANA = 60; // segundos

    private ?Publicacion $publicaciones = null;
    private ?Reaccion $model = null;

    private function publicaciones(): Publicacion
    {
        return $this->publicaciones ??= new Publicacion();
    }

    private function model(): Reaccion
    {
        return $this->model ??= new Reaccion();
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

    // GET: reacciones del usuario de la sesión. Va aparte del feed porque el
    // feed se cachea en Redis y es igual para todos.
    public function mias(): void
    {
        $userId = $this->requireAuth();
        $this->jsonResponse(200, ['reacciones' => (object) $this->model()->deUsuario($userId)]);
    }

    // POST {publicacion_id, tipo: "like"|"dislike"}
    public function store(array $body): void
    {
        $userId = $this->requireAuth();

        if (!RateLimiter::permitir('reaccion', (string) $userId, self::REACCIONES_MAX, self::REACCIONES_VENTANA)) {
            header('Retry-After: ' . self::REACCIONES_VENTANA);
            $this->jsonResponse(429, ['error' => 'Estás reaccionando demasiado rápido. Esperá un minuto.']);
        }

        $publicacionId = (int) ($body['publicacion_id'] ?? 0);
        $tipo = $body['tipo'] ?? null;

        if ($publicacionId <= 0 || !Reaccion::tipoValido($tipo)) {
            $this->jsonResponse(400, ['error' => 'Se requiere "publicacion_id" y "tipo" ("like" o "dislike").']);
        }

        $publicacion = $this->publicaciones()->getById($publicacionId);
        if (!$publicacion) {
            $this->jsonResponse(404, ['error' => 'Publicación no encontrada', 'id' => $publicacionId]);
        }

        if ((int) $publicacion['user_id'] === $userId) {
            $this->jsonResponse(403, ['error' => 'No podés reaccionar a tu propia publicación.']);
        }

        if (!$this->model()->crear($publicacionId, $userId, $tipo)) {
            $this->jsonResponse(409, ['error' => 'Ya reaccionaste a esta publicación.']);
        }

        // Cambian los contadores del feed: se descarta la caché y se avisa a
        // los demás paneles abiertos.
        Feed::invalidar();

        $actual = $this->publicaciones()->getById($publicacionId);
        $this->jsonResponse(201, [
            'publicacion_id' => $publicacionId,
            'tipo' => $tipo,
            'likes' => (int) ($actual['likes'] ?? 0),
            'dislikes' => (int) ($actual['dislikes'] ?? 0),
        ]);
    }
}
