#!/bin/sh
# docker/start.sh
# Arranque del contenedor: Apache siempre y, opcionalmente, el consumidor
# de RabbitMQ en segundo plano (RUN_WORKER=1 y AMQP_URL definida).
# Si el worker termina (por ejemplo por un error de base de datos), se
# vuelve a lanzar solo a los 5 segundos.

# Cuenta del administrador: si está ADMIN_PASSWORD se crea (nickname: ADMIN_NICKNAME o DesAnima1)
# (o se deja como está si ya existe). No corta el arranque si falla.
if [ -n "${ADMIN_PASSWORD:-}" ]; then
  runuser -u www-data -- php /var/www/html/bin/crear_admin.php || echo "[start] no se pudo crear/verificar el admin"
fi

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
