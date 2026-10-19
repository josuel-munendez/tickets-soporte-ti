# syntax=docker/dockerfile:1
# Imagen de la aplicación: PHP 8.1 + Apache, con el docroot en public/ (AGENTS.md §3).
FROM php:8.1-apache

# Extensiones necesarias: PDO MySQL (§2) y reescritura de URLs.
RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite headers

# El Front Controller vive en public/ (§3): Apache sirve esa carpeta como raíz.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/sites-available/*.conf \
        /etc/apache2/apache2.conf \
        /etc/apache2/conf-available/*.conf \
    && printf '%s\n' \
        '<Directory /var/www/html/public>' \
        '    AllowOverride All' \
        '    Require all granted' \
        '</Directory>' \
        > /etc/apache2/conf-available/zz-soporte-ti.conf \
    && a2enconf zz-soporte-ti

# Configuración de PHP alineada con el proyecto (zona horaria y errores ocultos, §2 y §4.4).
RUN printf '%s\n' \
    'date.timezone = America/Bogota' \
    'display_errors = 0' \
    'log_errors = 1' \
    > /usr/local/etc/php/conf.d/zz-soporte-ti.ini

WORKDIR /var/www/html

# Se copia el proyecto. vendor/ y config/config.php se excluyen con .dockerignore
# (Composer instala las dependencias de producción dentro de la imagen).
COPY . .

# Autoload de Composer (la app tiene un fallback PSR-4 si no hay vendor/, pero en
# la imagen se aprovecha Composer). Se instala solo producción para no traer PHPUnit.
RUN if [ -f composer.json ]; then \
        curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
        && composer install --no-dev --no-interaction --optimize-autoloader --no-scripts; \
    fi \
    && chown -R www-data:www-data /var/www/html

EXPOSE 80
