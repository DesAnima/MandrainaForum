#!/bin/sh
# docker/start.sh
# Arranque del contenedor: Apache siempre y, opcionalmente, el consumidor
# de RabbitMQ en segundo plano (RUN_WORKER=1 y AMQP_URL definida).
# Si el worker termina (por ejemplo por un error de base de datos), se
# vuelve a lanzar solo a los 5 segundos.

if [ "${RUN_WORKER:-0}" = "1" ] && [ -n "${AMQP_URL:-}" ]; then
  (
    while true; do
      runuser -u www-data -- php /var/www/html/worker/consumer.php
      echo "[start] el worker terminó; se reinicia en 5 s"
      sleep 5
    done
  ) &
  echo "[start] worker de RabbitMQ lanzado en segundo plano"
fi

exec apache2-foreground
