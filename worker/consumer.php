<?php
// worker/consumer.php
// Consumidor de RabbitMQ. Se corre por separado de Apache:
//
//     php worker/consumer.php
//
// (en docker-compose es el servicio "worker"). Por cada evento de
// publicaciones y respuestas (creada / editada / eliminada) hace dos tareas secundarias
// que no tienen por qué demorar la respuesta de la API:
//
//   1. Moderación: si el texto tiene lenguaje vulgar (FiltroContenido), deja
//      un registro en la tabla `moderacion_log` para revisión.
//   2. Recalienta la caché del feed en Redis, así el próximo GET ya sale de
//      Redis en vez de ir a PostgreSQL.
//
// Resiliencia: ante un error el mensaje se reintenta UNA vez; si vuelve a
// fallar va a la cola mandraina.publicaciones.dlq (visible en el panel de
// RabbitMQ). Si el error es de base de datos el proceso termina para que el
// supervisor (docker / Render) lo reinicie con una conexión nueva.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo se puede ejecutar desde la línea de comandos.');
}

require_once __DIR__ . '/../includes/Cola.php';
require_once __DIR__ . '/../includes/Feed.php';
require_once __DIR__ . '/../models/Publicacion.php';
require_once __DIR__ . '/../models/Respuesta.php';

use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;

function logw(string $mensaje): void
{
    fwrite(STDOUT, '[' . date('Y-m-d H:i:s') . '] ' . $mensaje . PHP_EOL);
}

function asegurarTablaModeracion(PDO $pdo): void
{
    // Sin FK a propósito: el registro de moderación se conserva aunque
    // la publicación se elimine después.
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS moderacion_log (
            id SERIAL PRIMARY KEY,
            publicacion_id INTEGER NOT NULL,
            user_id INTEGER,
            evento TEXT NOT NULL,
            motivo TEXT NOT NULL,
            creado_en TIMESTAMP NOT NULL DEFAULT NOW()
        )
    ");
}

function procesarEvento(array $evento, Publicacion $modelo, Respuesta $respuestas, PDO $pdo): void
{
    $tipo = (string) ($evento['evento'] ?? '');
    $id = (int) ($evento['publicacion_id'] ?? 0);

    if (in_array($tipo, ['publicacion.creada', 'publicacion.editada'], true) && $id > 0) {
        $publicacion = $modelo->getById($id);
        if ($publicacion && FiltroContenido::esVulgar($publicacion['titulo'] . ' ' . $publicacion['contenido'])) {
            $stmt = $pdo->prepare("
                INSERT INTO moderacion_log (publicacion_id, user_id, evento, motivo)
                VALUES (:pid, :uid, :evento, :motivo)
            ");
            $stmt->execute([
                ':pid' => $id,
                ':uid' => (int) $publicacion['user_id'],
                ':evento' => $tipo,
                ':motivo' => 'lenguaje_vulgar',
            ]);
            logw("Moderación: publicación #{$id} marcada por lenguaje vulgar ({$tipo}).");
        }
    }

    if ($tipo === 'respuesta.creada' && $id > 0) {
        $rid = (int) ($evento['respuesta_id'] ?? 0);
        $respuesta = $rid > 0 ? $respuestas->getById($rid) : null;
        if ($respuesta && FiltroContenido::esVulgar($respuesta['contenido'])) {
            // El registro queda asociado a la publicación a la que se respondió.
            $stmt = $pdo->prepare("
                INSERT INTO moderacion_log (publicacion_id, user_id, evento, motivo)
                VALUES (:pid, :uid, :evento, :motivo)
            ");
            $stmt->execute([
                ':pid' => $id,
                ':uid' => (int) $respuesta['user_id'],
                ':evento' => $tipo,
                ':motivo' => 'lenguaje_vulgar_en_respuesta',
            ]);
            logw("Moderación: respuesta #{$rid} (publicación #{$id}) marcada por lenguaje vulgar.");
        }
    }

    Feed::precalentar($modelo);
    logw("Procesado {$tipo} #{$id}; caché del feed recalentada.");
}

logw('Worker iniciado. Conectando a RabbitMQ...');

while (true) {
    try {
        $pdo = getPDO();
        asegurarTablaModeracion($pdo);
        $modelo = new Publicacion();
        $respuestas = new Respuesta();

        // read_write_timeout (65) > timeout de wait() (30): el wait() corta
        // primero y el bucle sigue, en vez de matar la conexión por inactividad.
        $conn = getAmqpConnection(5.0, 65.0, 0);
        $ch = $conn->channel();
        Cola::declararTopologia($ch);
        $ch->basic_qos(null, 1, null); // un mensaje a la vez

        $callback = function (AMQPMessage $msg) use ($modelo, $respuestas, $pdo): void {
            $evento = json_decode($msg->getBody(), true);

            if (!is_array($evento)) {
                logw('Mensaje inválido (no es JSON); va a la DLQ.');
                $msg->nack(false); // sin reintento -> dead-letter
                return;
            }

            try {
                procesarEvento($evento, $modelo, $respuestas, $pdo);
                $msg->ack();
            } catch (Throwable $e) {
                logw('ERROR procesando evento: ' . get_class($e) . ': ' . $e->getMessage());
                $yaReintentado = (bool) ($msg->delivery_info['redelivered'] ?? false);

                // Primer fallo: vuelve a la cola. Segundo fallo: a la DLQ.
                $msg->nack(!$yaReintentado);

                if ($e instanceof PDOException) {
                    logw('Error de base de datos: el worker se reinicia para abrir una conexión nueva.');
                    exit(1);
                }
                sleep(2);
            }
        };

        $ch->basic_consume(Cola::QUEUE, '', false, false, false, false, $callback);
        logw('Esperando eventos en la cola "' . Cola::QUEUE . '"...');

        while ($ch->is_consuming()) {
            try {
                $ch->wait(null, false, 30);
            } catch (AMQPTimeoutException $e) {
                // Sin mensajes en 30 s: normal, seguimos esperando.
            }
        }
    } catch (Throwable $e) {
        logw('Conexión perdida o no disponible: ' . $e->getMessage() . ' — reintentando en 5 s.');
    }

    sleep(5);
}
