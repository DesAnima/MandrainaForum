<?php
// avatar.php?user_id={id}
// El foro ya no guarda fotos de perfil (ni ningún otro dato personal),
// así que este endpoint siempre devuelve el avatar genérico. Se deja
// como archivo aparte para no tener que tocar panel.php ni el resto
// del feed, que ya arman la URL avatar.php?user_id=... .

header('Content-Type: image/svg+xml');
header('Cache-Control: public, max-age=86400');
echo <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
    <circle cx="50" cy="50" r="50" fill="#000000"/>
    <circle cx="50" cy="38" r="18" fill="#ffd900"/>
    <path d="M50 60c-20 0-32 12-32 26v14h64V86c0-14-12-26-32-26z" fill="#ffd900"/>
</svg>
SVG;
