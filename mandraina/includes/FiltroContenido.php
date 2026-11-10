<?php
// includes/FiltroContenido.php
//
// Detecta lenguaje vulgar en un texto. El worker elimina las publicaciones y
// respuestas que lo contengan, y mientras tanto los listados las ocultan.
//
// Para agregar o quitar palabras, editá la lista PALABRAS (sin tildes).

class FiltroContenido
{
    // Se escriben en minúscula y sin tildes (la ñ se mantiene).
    // No hace falta agregar plurales ("putas", "cabrones"), ni versiones
    // con letras repetidas ("puuuuta"): el filtro las detecta solo.
    private const PALABRAS = [
        // Español
        'puta', 'puto', 'putada', 'puteada', 'putear', 'puteo',
        'hijoputa', 'hijueputa', 'hdp', 'lpqtp', 'lpm', 'ctm',
        'mierda', 'carajo', 'cagada', 'cagar', 'cagon',
        'boludo', 'boluda', 'boludez', 'pelotudo', 'pelotuda', 'pelotudez',
        'forro', 'forra', 'pendejo', 'pendeja', 'orto',
        'culo', 'culiado', 'culiao', 'verga', 'pija', 'chupapija', 'poronga',
        'conchudo', 'conchuda', 'conchatumadre', 'concha de tu madre', 'concha de la lora',
        'garcha', 'garchar', 'garche', 'ortiva', 'ortiba',
        'pajero', 'gilipollas', 'gilipolla', 'cabron', 'cabrona', 'coño', 'joder', 'jodete',
        'malparido', 'malparida', 'maricon', 'marica', 'trolo',
        // Inglés
        'fuck', 'fucker', 'fucking', 'fucked', 'motherfucker',
        'shit', 'bullshit', 'bitch', 'asshole', 'ass', 'cunt',
        'dick', 'dickhead', 'bastard', 'whore', 'slut', 'pussy',
        'wtf', 'stfu',
    ];

    // Sustituciones típicas para esquivar filtros: "put4", "m13rda", "c@rajo"
    private const LEET = [
        '@' => 'a', '4' => 'a', '3' => 'e', '1' => 'i',
        '0' => 'o', '5' => 's', '$' => 's', '7' => 't',
    ];

    private const SIN_TILDES = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u',
        'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u',
        'Ñ' => 'ñ',
    ];

    private static ?string $patron = null;

    public static function esVulgar(string $texto): bool
    {
        if (trim($texto) === '') {
            return false;
        }

        return preg_match(self::patron(), self::normalizar($texto)) === 1;
    }

    /** Cantidad de insultos del texto; cada aparición cuenta, aunque se repita. */
    public static function contarInsultos(string $texto): int
    {
        if (trim($texto) === '') {
            return 0;
        }

        return (int) preg_match_all(self::patron(), self::normalizar($texto));
    }

    private static function normalizar(string $texto): string
    {
        $texto = strtr($texto, self::SIN_TILDES);
        return strtr($texto, self::LEET);
    }

    /**
     * Arma una sola expresión regular con todas las palabras.
     *  - \b evita falsos positivos: "disputa", "computadora" o "pijama" NO se marcan.
     *  - Cada letra acepta repeticiones: "puuuta" también se detecta.
     *  - Se acepta plural al final: "putas", "cabrones".
     */
    private static function patron(): string
    {
        if (self::$patron !== null) {
            return self::$patron;
        }

        $partes = [];
        foreach (self::PALABRAS as $palabra) {
            $regex = '';
            foreach (preg_split('//u', $palabra, -1, PREG_SPLIT_NO_EMPTY) as $letra) {
                $regex .= $letra === ' ' ? '\s+' : preg_quote($letra, '/') . '+';
            }
            $partes[] = $regex;
        }

        self::$patron = '/\b(?:' . implode('|', $partes) . ')(?:e?s+)?\b/iu';
        return self::$patron;
    }
}
