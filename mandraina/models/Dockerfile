# Use official PHP + Apache image
FROM php:8.2-apache

# Instalar dependencias para PostgreSQL y otras utilidades
RUN apt-get update && \
    apt-get install -y libpq-dev libmagic-dev unzip git && \
    docker-php-ext-install pdo pdo_pgsql fileinfo

# Traer Composer (para instalar phpdotenv) desde la imagen oficial
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Habilitar mod_rewrite y permitir que los .htaccess funcionen
# (necesario para las rutas tipo /api/v1/publicaciones/{id})
RUN a2enmod rewrite && \
    sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

WORKDIR /var/www/html

# Instalar dependencias de PHP primero (aprovecha la cache de Docker)
COPY composer.json ./
RUN composer install --no-dev --optimize-autoloader --no-interaction || true

# Copiar el resto del código
COPY . /var/www/html

# Ajustar permisos si necesitás
RUN chown -R www-data:www-data /var/www/html

# Exponer puerto 80 (Render lo gestiona)
EXPOSE 80

# Comando por defecto (Apache ya viene configurado)
CMD ["apache2-foreground"]
