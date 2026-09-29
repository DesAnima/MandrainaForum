<?php
// controllers/PublicacionController.php
require_once __DIR__ . '/../models/Publicacion.php';
require_once __DIR__ . '/../includes/FiltroContenido.php';

class PublicacionController
{
    private Publicacion $model;

    public function __construct()
    {
        $this->model = new Publicacion();
    }

    private function jsonResponse(int $status, array $data): void
    {
        http_response_code($status);
        echo json_encode($data);
        exit;
    }

    // Agrega "contenido_sensible" (true/false) para que el panel decida si oculta el contenido.
    private function marcarSensible(array $publicacion): array
    {
        $publicacion['contenido_sensible'] = FiltroContenido::esVulgar(
            ($publicacion['titulo'] ?? '') . ' ' . ($publicacion['contenido'] ?? '')
        );
        return $publicacion;
    }

    private function requireAuth(): int
    {
        if (empty($_SESSION['user_id'])) {
            $this->jsonResponse(401, ['error' => 'No autenticado. Iniciá sesión para continuar.']);
        }
        return (int) $_SESSION['user_id'];
    }

    public function index(): void
    {
        $this->jsonResponse(200, array_map([$this, 'marcarSensible'], $this->model->getAll()));
    }

    public function show(int $id): void
    {
        $publicacion = $this->model->getById($id);

        if (!$publicacion) {
            $this->jsonResponse(404, ['error' => 'Publicación no encontrada', 'id' => $id]);
        }

        $this->jsonResponse(200, $this->marcarSensible($publicacion));
    }

    public function store(array $body): void
    {
        $userId = $this->requireAuth();

        $titulo = trim($body['titulo'] ?? '');
        $contenido = trim($body['contenido'] ?? '');

        if ($titulo === '' || $contenido === '') {
            $this->jsonResponse(400, ['error' => 'Los campos "titulo" y "contenido" son obligatorios.']);
        }

        $nueva = $this->model->create($userId, $titulo, $contenido);
        $this->jsonResponse(201, $this->marcarSensible($nueva));
    }

    public function update(int $id, array $body): void
    {
        $userId = $this->requireAuth();
        $publicacion = $this->model->getById($id);

        if (!$publicacion) {
            $this->jsonResponse(404, ['error' => 'Publicación no encontrada', 'id' => $id]);
        }

        if ((int) $publicacion['user_id'] !== $userId) {
            $this->jsonResponse(403, ['error' => 'No podés editar una publicación que no te pertenece.']);
        }

        $titulo = trim($body['titulo'] ?? '');
        $contenido = trim($body['contenido'] ?? '');

        if ($titulo === '' || $contenido === '') {
            $this->jsonResponse(400, ['error' => 'Los campos "titulo" y "contenido" son obligatorios.']);
        }

        $actualizada = $this->model->update($id, $titulo, $contenido);
        $this->jsonResponse(200, $this->marcarSensible($actualizada));
    }

    public function destroy(int $id): void
    {
        $userId = $this->requireAuth();
        $publicacion = $this->model->getById($id);

        if (!$publicacion) {
            $this->jsonResponse(404, ['error' => 'Publicación no encontrada', 'id' => $id]);
        }

        if ((int) $publicacion['user_id'] !== $userId) {
            $this->jsonResponse(403, ['error' => 'No podés eliminar una publicación que no te pertenece.']);
        }

        $this->model->delete($id);
        http_response_code(204);
        exit;
    }
}
