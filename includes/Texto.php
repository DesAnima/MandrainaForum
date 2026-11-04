<?php
// includes/Texto.php
// Normalización del texto que escriben los usuarios (publicaciones y
// respuestas). Evita contenido "vacío" pero larguísimo hecho con espacios,
// saltos de línea o caracteres invisibles.

class Texto
{
    /**
     * Quita caracteres invisibles y de control, colapsa los espacios
     * repetidos y deja como máximo dos saltos de línea seguidos.
     * Con $unaLinea (por ejemplo el título) los saltos de línea pasan a ser
     * espacios.
     */
    public static function limpiar(mixed $valor, bool $unaLinea = false): string
    {
        $texto = is_string($valor) ? $valor : '';
        $texto = str_replace(["\r\n", "\r"], "\n", $texto);
        $texto = preg_replace(
            '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F\x{00AD}\x{115F}\x{1160}\x{200B}\x{200E}\x{200F}\x{202A}-\x{202E}\x{2060}-\x{2064}\x{2800}\x{3164}\x{FEFF}\x{FFA0}]/u',
            '',
            $texto
        ) ?? '';

        if ($unaLinea) {
            $texto = preg_replace('/\s+/u', ' ', $texto) ?? '';
        } else {
            $texto = preg_replace('/\h+/u', ' ', $texto) ?? '';
            $texto = preg_replace('/ ?\n ?/', "\n", $texto) ?? '';
            $texto = preg_replace('/\n{3,}/', "\n\n", $texto) ?? '';
        }

        return trim($texto);
    }
}
