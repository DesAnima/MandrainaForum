<?php
// includes/Cola.php
// Publicación de eventos en RabbitMQ.
//
// Topología (la declaran tanto el productor como el worker, es idempotente,
// así no se pierden mensajes si la API publica antes de que arranque el worker):
//
//   exchange  mandraina.eventos (topic, durable)
//      ├─ routing key "publicacion.*" ─┐
//      └─ routing key "respuesta.*"  ──┴► cola mandraina.publicaciones
//                                          └─ si un mensaje falla ─► mandraina.publicaciones.dlq
//
// Eventos: publicacion.creada | publicacion.editada | publicacion.eliminada
//          respuesta.creada | respuesta.eliminada

require_once __DIR__ . '/../config/rabbitmq.php';

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

class Cola
{
    public const EXCHANGE = 'mandraina.eventos';
    public const QUEUE = 'mandraina.publicaciones';
    public const DLQ = 'mandraina.publicaciones.dlq';
    public const BINDING_KEYS = ['publicacion.*', 'respuesta.*'];

    public static function declararTopologia(AMQPChannel $ch): void
    {
        $ch->exchange_declare(self::EXCHANGE, 'topic', false, true, false);

        // Cola de mensajes fallidos (dead-letter): se pueden inspeccionar
        // en el panel de RabbitMQ en vez de perderse.
        $ch->queue_declare(self::DLQ, false, true, false, false);

        $ch->queue_declare(self::QUEUE, false, true, false, false, false, new AMQPTable([
            'x-dead-letter-exchange' => '',               // exchange por defecto...
            'x-dead-letter-routing-key' => self::DLQ,     // ...enruta directo a la DLQ por nombre
        ]));
        foreach (self::BINDING_KEYS as $clave) {
            $ch->queue_bind(self::QUEUE, self::EXCHANGE, $clave);
        }
    }

    /**
     * Publica un evento. Nunca lanza excepciones: si RabbitMQ no está
     * disponible se registra en el log y la API sigue funcionando
     * (el evento solo se usa para tareas secundarias).
     */
    public static function publicar(string $evento, array $datos): bool
    {
        if (!mandraina_env('AMQP_URL')) {
            return false;
        }

        try {
            $conn = getAmqpConnection(2.0, 2.0);
            $ch = $conn->channel();
            self::declararTopologia($ch);

            $cuerpo = json_encode($datos + ['evento' => $evento, 'ocurrio_en' => gmdate('c')]);
            $ch->basic_publish(
                new AMQPMessage($cuerpo, [
                    'content_type' => 'application/json',
                    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                ]),
                self::EXCHANGE,
                $evento
            );

            $ch->close();
            $conn->close();
            return true;
        } catch (Throwable $e) {
            error_log('[cola] No se pudo publicar ' . $evento . ': ' . $e->getMessage());
            return false;
        }
    }
}
