<?php
// includes/Feed.php
// Listado de publicaciones (con la marca "contenido_sensible") cacheado en
// Redis. La API lo invalida al escribir y el worker lo vuelve a calentar
// cuando procesa el evento, así el siguiente GET ya lo encuentra listo.

require_once __DIR__ . '/Cache.php';
require_once __DIR__ . '/FiltroContenido.php';

class Feed
{
    public const CLAVE = 'feed:v1';
    public const TTL = 60; // segundos; red de seguridad por si falla la invalidación

    /** Agrega "contenido_sensible" (true/false) a una publicación. */
    public static function marcar(array $publicacion): array
    {
        $publicacion['contenido_sensible'] = FiltroContenido::esVulgar(
            ($publicacion['titulo'] ?? '') . ' ' . ($publicacion['contenido'] ?? '')
        );
        return $publicacion;
    }

    public static function construir(Publicacion $model): array
    {
        return array_map([self::class, 'marcar'], $model->getAll());
    }

    /**
     * @param callable():Publicacion $obtenerModelo  se invoca solo en un miss,
     *        así un HIT no abre ni conexión a PostgreSQL.
     * @param bool $hit  queda en true si salió de Redis.
     */
    public static function obtener(callable $obtenerModelo, ?bool &$hit = null): array
    {
        $cacheado = Cache::obtener(self::CLAVE);
        if (is_array($cacheado)) {
            $hit = true;
            return $cacheado;
        }

        $hit = false;
        $feed = self::construir($obtenerModelo());
        Cache::guardar(self::CLAVE, $feed, self::TTL);
        return $feed;
    }

    public static function invalidar(): void
    {
        Cache::borrar(self::CLAVE);
    }

    public static function precalentar(Publicacion $model): void
    {
        Cache::guardar(self::CLAVE, self::construir($model), self::TTL);
    }
}
