#!/bin/sh
# Entrypoint del contenedor de la app: genera config/config.php a partir de las
# variables de entorno (el archivo real NUNCA se versiona, §4.6). Así los secretos
# viven en .env / variables de entorno y no en Git.
#
# Compatible con dos formas de configurar la BD:
#   1) DB_URL  -> "mysql://usuario:contrasena@host:puerto/nombre" (Render, Railway...)
#   2) Variables sueltas: DB_HOST, DB_PUERTO, DB_NOMBRE, DB_USUARIO, DB_CONTRASENA
#      (docker compose local).
# También adapta Apache al puerto que exija la plataforma ($PORT en Render) o 80.
set -e

CONFIG_FILE="/var/www/html/config/config.php"

# --- 1. Resolver la conexión a la BD -----------------------------------------
if [ -n "${DB_URL:-}" ]; then
    # Parsea mysql://user:pass@host:port/db con PHP (evita depender de otras herramientas).
    eval "$(php -r '
        $u = getenv("DB_URL");
        $p = parse_url($u);
        if ($p === false || !isset($p["host"])) { fwrite(STDERR, "DB_URL inválida\n"); exit(1); }
        $host = $p["host"];
        $port = $p["port"] ?? 3306;
        $user = isset($p["user"]) ? urldecode($p["user"]) : "";
        $pass = isset($p["pass"]) ? urldecode($p["pass"]) : "";
        $name = isset($p["path"]) ? ltrim($p["path"], "/") : "";
        printf("DB_HOST=%s\nDB_PUERTO=%s\nDB_USUARIO=%s\nDB_CONTRASENA=%s\nDB_NOMBRE=%s\n",
            escapeshellarg($host), escapeshellarg((string) $port),
            escapeshellarg($user), escapeshellarg($pass), escapeshellarg($name));
    ')"
fi

: "${DB_HOST:=127.0.0.1}"
: "${DB_PUERTO:=3306}"
: "${DB_NOMBRE:=soporte_ti}"
: "${DB_USUARIO:=root}"
: "${DB_CONTRASENA:=}"
: "${API_KEY:=}"

cat > "$CONFIG_FILE" <<PHP
<?php
/**
 * Generado automáticamente por docker/entrypoint.sh a partir de variables de
 * entorno. NO editar a mano dentro del contenedor: ajusta las variables y recrea.
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

# --- 1b. Asegurar el directorio de logs interno ------------------------------
# El Front Controller escribe la traza en storage/logs/ (§4.4). El Dockerfile hace
# chown -R www-data, pero cuando storage/logs es un volumen montado en tiempo de
# ejecución, el montaje pisa esos permisos y queda root:root ⇒ PHP no puede
# escribir y el error real se pierde. Se corrige aquí, en cada arranque.
LOG_DIR="/var/www/html/storage/logs"
mkdir -p "$LOG_DIR"
chown -R www-data:www-data /var/www/html/storage
chmod 775 "$LOG_DIR"

# --- 2. Adaptar Apache al puerto de la plataforma ----------------------------
# Render (y otros PaaS) exponen $PORT y esperan que la app escuche ahí.
if [ -n "${PORT:-}" ] && [ "${PORT}" != "80" ]; then
    sed -ri "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
    sed -ri "s/:80>/:${PORT}>/" /etc/apache2/sites-available/000-default.conf
    echo "Apache escuchando en el puerto ${PORT}."
fi

# --- 3. Esperar a la BD (máx. ~60 s) -----------------------------------------
echo "Esperando a MySQL en ${DB_HOST}:${DB_PUERTO} ..."
i=0
until php -r "exit(@fsockopen('${DB_HOST}', (int)'${DB_PUERTO}') ? 0 : 1);" 2>/dev/null; do
    i=$((i + 1))
    [ "$i" -ge 60 ] && echo "MySQL no respondió a tiempo." && exit 1
    sleep 1
done
echo "MySQL disponible."

exec apache2-foreground
