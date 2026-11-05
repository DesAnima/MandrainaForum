<?php
// includes/Tags.php
// Normalización de los tags de las publicaciones.

require_once __DIR__ . '/Texto.php';

class Tags
{
    public const MAX_POR_PUBLICACION = 5;
    public const LARGO_MAX = 20; // caracteres por tag

    private const SIN_TILDES = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u',
    ];

    /**
     * Acepta un texto ("musica, #juegos") o una lista y devuelve los tags
     * normalizados: en minúscula, sin "#", sin tildes, solo letras, números,
     * "_" y "-", y sin repetidos. No recorta ni valida longitudes: eso lo
     * hace el controlador para poder responder con un error claro.
     *
     * @return string[]
     */
    public static function parsear(mixed $valor): array
    {
        if (is_string($valor)) {
            $valor = preg_split('/[\s,;]+/u', $valor, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        } elseif (!is_array($valor)) {
            return [];
        }

        $tags = [];
        foreach ($valor as $item) {
            if (!is_string($item)) {
                continue;
            }

            $tag = Texto::limpiar($item, true);
            $tag = mb_strtolower(ltrim($tag, '#'));
            $tag = strtr($tag, self::SIN_TILDES);
            $tag = preg_replace('/[^\p{L}\p{N}_-]/u', '', $tag) ?? '';

            if ($tag !== '') {
                $tags[$tag] = true;
            }
        }

        // Las claves numéricas ("2024") se vuelven enteros en un array de PHP.
        return array_map('strval', array_keys($tags));
    }
}
