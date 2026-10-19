#!/bin/sh
# Entrypoint del contenedor de la app: genera config/config.php a partir de las
# variables de entorno (el archivo real NUNCA se versiona, §4.6). Así los secretos
# viven en .env / variables de entorno y no en Git.
set -e

CONFIG_FILE="/var/www/html/config/config.php"

cat > "$CONFIG_FILE" <<PHP
<?php
/**
 * Generado automáticamente por docker/entrypoint.sh a partir de variables de
 * entorno. NO editar a mano dentro del contenedor: ajusta .env y recrea.
 */
declare(strict_types=1);

return [
    'db' => [
        'host' => '${DB_HOST}',
        'puerto' => (int) '${DB_PUERTO}',
        'nombre' => '${DB_NOMBRE}',
        'usuario' => '${DB_USUARIO}',
        'contrasena' => '${DB_CONTRASENA}',
        'charset' => 'utf8mb4',
    ],
    'api_key' => '${API_KEY}',
    'zona_horaria' => 'America/Bogota',
];
PHP

chown www-data:www-data "$CONFIG_FILE"

# Espera a que MySQL acepte conexiones (máx. ~60 s).
echo "Esperando a MySQL en ${DB_HOST}:${DB_PUERTO} ..."
i=0
until php -r "exit(@fsockopen('${DB_HOST}', (int)'${DB_PUERTO}') ? 0 : 1);" 2>/dev/null; do
    i=$((i + 1))
    [ "$i" -ge 60 ] && echo "MySQL no respondió a tiempo." && exit 1
    sleep 1
done
echo "MySQL disponible."

exec apache2-foreground
