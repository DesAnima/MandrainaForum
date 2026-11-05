<?php
// includes/Admin.php
// Administrador único del foro.
//
// El admin es el usuario cuyo nickname coincide con la variable de entorno
// ADMIN_NICKNAME (por defecto "DesAnima1"). Como el nickname es único (y no se
// puede cambiar), hay un solo administrador.
//
// Se evalúa en cada request contra el nickname guardado en la sesión, así que
// quitar o cambiar ADMIN_NICKNAME revoca el permiso de inmediato.

require_once __DIR__ . '/../config/env.php';

class Admin
{
    public const NICKNAME_POR_DEFECTO = 'DesAnima1';

    /** Nickname configurado como administrador, o null si no hay. */
    public static function nickname(): ?string
    {
        $nick = trim((string) mandraina_env('ADMIN_NICKNAME', self::NICKNAME_POR_DEFECTO));
        return $nick === '' ? null : $nick;
    }

    /** ¿El usuario de la sesión actual es el administrador? */
    public static function esAdmin(): bool
    {
        $admin = self::nickname();
        $actual = $_SESSION['nickname'] ?? null;

        return $admin !== null
            && !empty($_SESSION['user_id'])
            && is_string($actual)
            && hash_equals($admin, $actual);
    }

    /**
     * ¿Este nickname está reservado para el admin? Se compara sin distinguir
     * mayúsculas, para que nadie se registre como "ADMIN" o "Admin" y
     * confunda al resto.
     */
    public static function estaReservado(string $nickname): bool
    {
        $admin = self::nickname();
        return $admin !== null && strcasecmp($admin, trim($nickname)) === 0;
    }
}
