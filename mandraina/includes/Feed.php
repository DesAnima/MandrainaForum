<?php
// includes/Feed.php
// Listado de publicaciones (sin las que tienen lenguaje indebido), paginado por
// cursor. Solo la primera página sin filtro (la que pide todo el que entra al
// panel) se cachea en Redis; el resto va directo a PostgreSQL, que con los
// índices responde rápido. La API invalida la caché al escribir y el worker la
// vuelve a calentar cuando procesa el evento, así el siguiente GET ya la
// encuentra lista.

require_once __DIR__ . '/Cache.php';
require_once __DIR__ . '/FiltroContenido.php';
require_once __DIR__ . '/TiempoReal.php';

class Feed
{
    public const CLAVE = 'feed:v5:inicio'; // v5: se ocultan publicaciones con lenguaje indebido
    public const PAGINA = 10; // publicaciones por página
    public const TTL = 60; // segundos; red de seguridad por si falla la invalidación

    /** true si el título o el contenido tienen lenguaje indebido. */
    public static function esIndebida(array $publicacion): bool
    {
        return FiltroContenido::esVulgar(
            ($publicacion['titulo'] ?? '') . ' ' . ($publicacion['contenido'] ?? '')
        );
    }

    /**
     * Arma una página lista para la API.
     *
     * @return array{publicaciones: array, hay_mas: bool, siguiente: ?int}
     *         "siguiente" es el id a mandar como ?antes= para pedir la página que sigue.
     */
    public static function construir(Publicacion $model, int $limite, ?int $antes = null, ?string $tag = null): array
    {
        [$filas, $hayMas] = $model->getPagina($limite, $antes, $tag);

        // Red de seguridad: hasta que el worker la elimine, una publicación con
        // lenguaje indebido no se muestra. El cursor se calcula con las filas
        // sin filtrar para que la paginación no se salte ni repita nada.
        $siguiente = $filas ? (int) end($filas)['id'] : null;
        $publicaciones = array_values(array_filter($filas, fn (array $p): bool => !self::esIndebida($p)));

        return [
            'publicaciones' => $publicaciones,
            'hay_mas' => $hayMas,
            'siguiente' => $siguiente,
        ];
    }

    /**
     * @param callable():Publicacion $obtenerModelo  se invoca solo en un miss,
     *        así un HIT no abre ni conexión a PostgreSQL.
     * @param bool $hit  queda en true si salió de Redis.
     */
    public static function pagina(callable $obtenerModelo, int $limite, ?int $antes, ?string $tag, ?bool &$hit = null): array
    {
        $cacheable = $antes === null && $tag === null && $limite === self::PAGINA;

        if ($cacheable) {
            $cacheado = Cache::obtener(self::CLAVE);
            if (is_array($cacheado) && isset($cacheado['publicaciones'])) {
                $hit = true;
                return $cacheado;
            }
        }

        $hit = false;
        $pagina = self::construir($obtenerModelo(), $limite, $antes, $tag);
        if ($cacheable) {
            Cache::guardar(self::CLAVE, $pagina, self::TTL);
        }
        return $pagina;
    }

    // Se llama después de cada escritura (ya confirmada en la base): descarta
    // la caché y avisa a los paneles abiertos de que hay novedades.
    public static function invalidar(): void
    {
        Cache::borrar(self::CLAVE);
        TiempoReal::avisar();
    }

    public static function precalentar(Publicacion $model): void
    {
        Cache::guardar(self::CLAVE, self::construir($model, self::PAGINA), self::TTL);
    }
}
