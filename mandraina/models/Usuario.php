<?php
// models/Usuario.php
// Registro 100% anónimo: no se guarda email, bio ni foto. Solo nickname
// y el hash de una contraseña generada al azar por el sistema.
//
// Los registros pendientes viven en REDIS (con TTL nativo de 10 minutos):
// vencen solos y la contraseña en texto plano nunca toca PostgreSQL. Si
// Redis no está configurado/disponible se usa la tabla `registros_pendientes`
// de PostgreSQL como respaldo, con el comportamiento de siempre.
//
// Flujo:
//   1. crearPendiente()  -> genera token + password random, guarda un
//      registro TEMPORAL (todavía no es un usuario real).
//   2. reclamar($token)  -> si el token es válido y no expiró, recién
//      ahí se crea la fila definitiva en `usuarios` y se devuelve la
//      contraseña en texto plano UNA sola vez para mostrarla. El
//      registro pendiente se borra inmediatamente (uso único).
//   3. Todo registro pendiente vencido se purga solo en cada request.

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/redis.php';
require_once __DIR__ . '/../includes/Admin.php';

class Usuario
{
    private PDO $pdo;
    private ?\Predis\Client $redis;

    // Tiempo de vida del QR / registro pendiente, en segundos.
    public const TTL_SEGUNDOS = 600; // 10 minutos

    // Alfabeto sin caracteres ambiguos (0/O, 1/l/I) para que sea fácil
    // de transcribir a mano desde la pantalla del celular.
    private const ALFABETO_PASSWORD = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';

    public function __construct()
    {
        $this->redis = getRedis();
        $this->pdo = getPDO();
        $this->ensureTables();
    }

    private function ensureTables(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS usuarios (
                id SERIAL PRIMARY KEY,
                nickname TEXT UNIQUE NOT NULL,
                password TEXT NOT NULL,
                profile_pic BYTEA,
                fecha_creacion TIMESTAMP NOT NULL DEFAULT NOW()
            )
        ");
        // Por si la tabla ya existía sin esta columna (proyectos creados
        // antes de esta versión).
        $this->pdo->exec("ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS profile_pic BYTEA");

        // Con Redis esta tabla no se usa; solo se crea para el modo de respaldo.
        if ($this->redis === null) {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS registros_pendientes (
                    id SERIAL PRIMARY KEY,
                    token TEXT UNIQUE NOT NULL,
                    nickname TEXT NOT NULL,
                    password_hash TEXT NOT NULL,
                    password_plain TEXT NOT NULL,
                    creado_en TIMESTAMP NOT NULL DEFAULT NOW(),
                    expira_en TIMESTAMP NOT NULL
                )
            ");
        }
    }

    // ---- Registros pendientes en Redis (claves con prefijo "mandraina:") ----

    private function claveToken(string $token): string
    {
        return 'reg:token:' . $token;
    }

    private function claveNick(string $nickname): string
    {
        return 'reg:nick:' . $nickname;
    }

    private function tokenConFormatoValido(string $token): bool
    {
        return (bool) preg_match('/^[a-f0-9]{32}$/', $token);
    }

    private function crearPendienteRedis(string $nickname): array
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM usuarios WHERE nickname = :n");
        $stmt->execute([':n' => $nickname]);
        if ($stmt->fetch()) {
            throw new InvalidArgumentException('Ese nickname ya está en uso o tiene un registro en curso.');
        }

        $token = bin2hex(random_bytes(16));

        // SET ... NX EX: reserva el nickname de forma ATÓMICA durante el TTL.
        // Si dos personas lo piden a la vez, solo una lo consigue.
        $reservado = $this->redis->set($this->claveNick($nickname), $token, 'EX', self::TTL_SEGUNDOS, 'NX');
        if ((string) $reservado !== 'OK') {
            throw new InvalidArgumentException('Ese nickname ya está en uso o tiene un registro en curso.');
        }

        try {
            $passwordPlano = $this->generarPassword();
            $this->redis->setex($this->claveToken($token), self::TTL_SEGUNDOS, json_encode([
                'nickname' => $nickname,
                'password_hash' => password_hash($passwordPlano, PASSWORD_DEFAULT),
                'password_plain' => $passwordPlano,
            ]));
        } catch (Throwable $e) {
            $this->redis->del([$this->claveNick($nickname)]); // no dejar el nickname trabado
            throw $e;
        }

        return ['token' => $token, 'expires_in' => self::TTL_SEGUNDOS];
    }

    private function reclamarRedis(string $token): ?array
    {
        if (!$this->tokenConFormatoValido($token)) {
            return null;
        }

        // GETDEL: lee y borra en una sola operación atómica. Si dos
        // requests reclaman el mismo token a la vez, solo una lo recibe.
        $crudo = $this->redis->getdel($this->claveToken($token));
        if ($crudo === null) {
            return null;
        }

        $pendiente = json_decode($crudo, true);
        $this->redis->del([$this->claveNick($pendiente['nickname'])]);

        try {
            $insert = $this->pdo->prepare("
                INSERT INTO usuarios (nickname, password) VALUES (:nickname, :password)
            ");
            $insert->execute([
                ':nickname' => $pendiente['nickname'],
                ':password' => $pendiente['password_hash'],
            ]);
        } catch (PDOException $e) {
            error_log('[claim] Falló el INSERT en usuarios: ' . $e->getMessage());
            return null;
        }

        return [
            'nickname' => $pendiente['nickname'],
            'password' => $pendiente['password_plain'],
        ];
    }

    /** Borra cualquier registro pendiente vencido. Se llama en cada operación. */
    private function purgarExpirados(): void
    {
        if ($this->redis !== null) {
            return; // Redis expira las claves solo
        }
        $this->pdo->exec("DELETE FROM registros_pendientes WHERE expira_en < NOW()");
    }

    private function nicknameValido(string $nickname): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_]{3,20}$/', $nickname);
    }

    private function nicknameDisponible(string $nickname): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM usuarios WHERE nickname = :n");
        $stmt->execute([':n' => $nickname]);
        if ($stmt->fetch()) {
            return false;
        }

        $stmt = $this->pdo->prepare("SELECT 1 FROM registros_pendientes WHERE nickname = :n");
        $stmt->execute([':n' => $nickname]);
        return !$stmt->fetch();
    }

    private function generarPassword(int $length = 10): string
    {
        $alfabeto = self::ALFABETO_PASSWORD;
        $max = strlen($alfabeto) - 1;
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $alfabeto[random_int(0, $max)];
        }
        return $password;
    }

    /**
     * Paso 1: el usuario eligió un nickname. Creamos un registro pendiente
     * (NO una cuenta todavía) y devolvemos el token para armar el QR.
     *
     * @return array{token:string, expires_in:int}
     * @throws InvalidArgumentException si el nickname es inválido o ya está en uso.
     */
    public function crearPendiente(string $nickname): array
    {
        $nickname = trim($nickname);
        $this->purgarExpirados();

        if (!$this->nicknameValido($nickname)) {
            throw new InvalidArgumentException('El nickname debe tener entre 3 y 20 caracteres (letras, números o "_").');
        }

        // El nickname del administrador no se puede registrar por el flujo
        // público: la cuenta se crea con bin/crear_admin.php.
        if (Admin::estaReservado($nickname)) {
            throw new InvalidArgumentException('Ese nickname ya está en uso o tiene un registro en curso.');
        }

        if ($this->redis !== null) {
            return $this->crearPendienteRedis($nickname);
        }

        if (!$this->nicknameDisponible($nickname)) {
            throw new InvalidArgumentException('Ese nickname ya está en uso o tiene un registro en curso.');
        }

        $token = bin2hex(random_bytes(16));
        $passwordPlano = $this->generarPassword();
        $passwordHash = password_hash($passwordPlano, PASSWORD_DEFAULT);

        // La expiración se calcula dentro de Postgres (NOW() + intervalo) y
        // se compara siempre contra NOW() de Postgres, así no depende de la
        // zona horaria de PHP ni de la sesión.
        $stmt = $this->pdo->prepare("
            INSERT INTO registros_pendientes (token, nickname, password_hash, password_plain, expira_en)
            VALUES (:token, :nickname, :hash, :plain, NOW() + (CAST(:ttl AS integer) * INTERVAL '1 second'))
        ");
        $stmt->execute([
            ':token' => $token,
            ':nickname' => $nickname,
            ':hash' => $passwordHash,
            ':plain' => $passwordPlano,
            ':ttl' => self::TTL_SEGUNDOS,
        ]);

        return ['token' => $token, 'expires_in' => self::TTL_SEGUNDOS];
    }

    /**
     * Chequeo de solo lectura: ¿el token existe y todavía no expiró?
     * No borra ni consume nada. Se usa para la pantalla de confirmación
     * antes de reclamar de verdad (así un simple GET / prefetch de un
     * navegador o antivirus no gasta el token de un solo uso).
     */
    public function pendienteValido(string $token): bool
    {
        if ($this->redis !== null) {
            return $this->tokenConFormatoValido($token)
                && (bool) $this->redis->exists($this->claveToken($token));
        }

        $this->purgarExpirados();
        $stmt = $this->pdo->prepare("
            SELECT 1 FROM registros_pendientes WHERE token = :token AND expira_en >= NOW()
        ");
        $stmt->execute([':token' => $token]);
        $ok = (bool) $stmt->fetch();

        if (!$ok) {
            // Diagnóstico temporal: ver si la fila existe pero con otra
            // fecha, o si directamente no existe. Se puede borrar después.
            $debug = $this->pdo->prepare("
                SELECT expira_en, NOW() AS ahora FROM registros_pendientes WHERE token = :token
            ");
            $debug->execute([':token' => $token]);
            $fila = $debug->fetch();
            if ($fila) {
                error_log("[claim-debug] token={$token} expira_en={$fila['expira_en']} ahora={$fila['ahora']} (existe pero no pasa el filtro)");
            } else {
                error_log("[claim-debug] token={$token} no existe ninguna fila en registros_pendientes");
            }
        }

        return $ok;
    }

    /**
     * Paso 2: se escaneó el QR. Si el token es válido y no expiró, se crea
     * la cuenta definitiva y se devuelve la contraseña en texto plano para
     * mostrarla UNA sola vez. El registro pendiente se elimina siempre que
     * se haya podido leer, sea cual sea el resultado (uso único).
     *
     * @return array{nickname:string, password:string}|null null si el token no existe o ya expiró.
     */
    public function reclamar(string $token): ?array
    {
        if ($this->redis !== null) {
            return $this->reclamarRedis($token);
        }

        $this->purgarExpirados();

        $stmt = $this->pdo->prepare("
            SELECT nickname, password_hash, password_plain
            FROM registros_pendientes
            WHERE token = :token AND expira_en >= NOW()
        ");
        $stmt->execute([':token' => $token]);
        $pendiente = $stmt->fetch();

        if (!$pendiente) {
            // Diagnóstico temporal: ver si la fila existe pero con otra
            // fecha, o si directamente no existe. Se puede borrar después.
            $debug = $this->pdo->prepare("
                SELECT expira_en, NOW() AS ahora FROM registros_pendientes WHERE token = :token
            ");
            $debug->execute([':token' => $token]);
            $fila = $debug->fetch();
            if ($fila) {
                error_log("[claim-debug][POST] token={$token} expira_en={$fila['expira_en']} ahora={$fila['ahora']} (existe pero no pasa el filtro)");
            } else {
                error_log("[claim-debug][POST] token={$token} no existe ninguna fila en registros_pendientes al reclamar");
            }
            return null;
        }

        // Uso único: se borra apenas se lee, haya éxito o no.
        $del = $this->pdo->prepare("DELETE FROM registros_pendientes WHERE token = :token");
        $del->execute([':token' => $token]);

        try {
            $insert = $this->pdo->prepare("
                INSERT INTO usuarios (nickname, password) VALUES (:nickname, :password)
            ");
            $insert->execute([
                ':nickname' => $pendiente['nickname'],
                ':password' => $pendiente['password_hash'],
            ]);
        } catch (PDOException $e) {
            // Diagnóstico temporal.
            error_log('[claim-debug][POST] Falló el INSERT en usuarios: ' . $e->getMessage());
            // Carrera muy poco probable: el nickname se tomó justo antes.
            return null;
        }

        return [
            'nickname' => $pendiente['nickname'],
            'password' => $pendiente['password_plain'],
        ];
    }

    /** Guarda (o reemplaza) la foto de perfil de un usuario. */
    public function actualizarFoto(int $userId, string $binario): void
    {
        $stmt = $this->pdo->prepare("UPDATE usuarios SET profile_pic = :foto WHERE id = :id");
        $stmt->bindValue(':foto', $binario, PDO::PARAM_LOB);
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();
    }

    /** Quita la foto de perfil (vuelve al avatar por defecto). */
    public function eliminarFoto(int $userId): void
    {
        $stmt = $this->pdo->prepare("UPDATE usuarios SET profile_pic = NULL WHERE id = :id");
        $stmt->execute([':id' => $userId]);
    }

    /** Login: nickname + password. Devuelve el usuario o null. */
    public function autenticar(string $nickname, string $password): ?array
    {
        $stmt = $this->pdo->prepare("SELECT id, nickname, password FROM usuarios WHERE nickname = :n");
        $stmt->execute([':n' => $nickname]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            return null;
        }

        return ['id' => $user['id'], 'nickname' => $user['nickname']];
    }
}
