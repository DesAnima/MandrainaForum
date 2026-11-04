# Use official PHP + Apache image
FROM php:8.2-apache

# Dependencias del sistema y extensiones de PHP:
#  - pdo_pgsql: PostgreSQL
#  - sockets:   requerida por php-amqplib (RabbitMQ)
# Redis se usa con Predis (cliente en PHP puro, vía Composer), así que no
# hace falta compilar ninguna extensión de Redis.
RUN apt-get update && \
    apt-get install -y libpq-dev libmagic-dev unzip git && \
    docker-php-ext-install pdo pdo_pgsql fileinfo sockets && \
    rm -rf /var/lib/apt/lists/*

# Traer Composer desde la imagen oficial
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Habilitar mod_rewrite y permitir que los .htaccess funcionen
# (necesario para las rutas tipo /api/v1/publicaciones/{id})
RUN a2enmod rewrite && \
    sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

WORKDIR /var/www/html

# Instalar dependencias de PHP primero (aprovecha la cache de Docker).
# Ya no lleva "|| true": ahora la app NECESITA vendor/ (Predis, amqplib),
# y si falla la instalación el build tiene que cortarse y avisar.
COPY composer.json ./
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copiar el resto del código
COPY . /var/www/html

RUN chown -R www-data:www-data /var/www/html

# Exponer puerto 80 (Render lo gestiona)
EXPOSE 80

# Arranque: docker/start.sh levanta Apache y, si RUN_WORKER=1 y AMQP_URL
# está definida, también el consumidor de RabbitMQ dentro del mismo
# contenedor. Sirve para Render gratis, que no ofrece workers gratuitos.
# En docker-compose el worker sigue siendo un servicio aparte (su propio
# "command") y RUN_WORKER queda sin definir.
RUN sed -i 's/\r$//' docker/start.sh
CMD ["sh", "docker/start.sh"]
