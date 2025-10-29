# Use official PHP + Apache image
FROM php:8.2-apache

# Instalar dependencias para PostgreSQL y otras utilidades
RUN apt-get update && \
    apt-get install -y libpq-dev unzip && \
    docker-php-ext-install pdo pdo_pgsql

# Copiar código al directorio de trabajo de Apache
WORKDIR /var/www/html
COPY . /var/www/html

# Ajustar permisos si necesitás
RUN chown -R www-data:www-data /var/www/html

# Exponer puerto 80 (Render lo gestiona)
EXPOSE 80

# Comando por defecto (Apache ya viene configurado)
CMD ["apache2-foreground"]
