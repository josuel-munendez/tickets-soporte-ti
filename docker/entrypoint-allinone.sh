#!/bin/sh
# =====================================================================
# Entrypoint "todo en uno": arranca MariaDB DENTRO del contenedor, aplica el
# esquema + datos semilla y luego levanta Apache en el puerto de la plataforma.
#
# Pensado para plataformas de un solo contenedor sin MySQL gestionado
# (p. ej. el plan Free de Render). Como no hay disco persistente, el seed se
# re-aplica en CADA arranque, así la app siempre despierta con datos de demo.
#
# El esquema (database/schema.sql) ya incluye CREATE DATABASE / USE, y el seed
# (database/seed.sql) limpia e inserta, por lo que ambos son re-ejecutables.
# =====================================================================
set -e

CONFIG_FILE="/var/www/html/config/config.php"
DB_NOMBRE="${DB_NOMBRE:-soporte_ti}"
# La app se conecta por TCP (127.0.0.1). En Debian/Ubuntu el usuario 'root' de
# MariaDB usa autenticación unix_socket y RECHAZA el acceso por TCP, así que se
# crea un usuario dedicado con contraseña para la aplicación.
DB_USUARIO="${DB_USUARIO:-soporte}"
DB_CONTRASENA="${DB_CONTRASENA:-soporte}"
API_KEY="${API_KEY:-}"

# --- 1. Arrancar MariaDB (servidor embebido) ---------------------------------
echo "Iniciando MariaDB local..."
mkdir -p /run/mysqld
chown -R mysql:mysql /run/mysqld /var/lib/mysql

# Arranca el daemon en segundo plano y espera a que acepte conexiones.
mysqld_safe --datadir=/var/lib/mysql --skip-networking=0 >/var/log/mysqld-allinone.log 2>&1 &

i=0
until mysqladmin ping --silent 2>/dev/null; do
    i=$((i + 1))
    if [ "$i" -ge 60 ]; then
        echo "MariaDB no respondió a tiempo. Log:"
        tail -n 40 /var/log/mysqld-allinone.log
        exit 1
    fi
    sleep 1
done
echo "MariaDB disponible."

# --- 2. Crear el usuario de la app y aplicar esquema + datos semilla ---------
#   • El CLI 'mysql -u root' usa el socket unix (sí permite root); la app usa TCP,
#     por eso se crea 'soporte'@'%' con contraseña.
#   • El esquema y el seed son idempotentes: se re-aplican en cada arranque.
echo "Configurando usuario y aplicando esquema y datos semilla..."
mysql -u root <<SQL
CREATE USER IF NOT EXISTS '${DB_USUARIO}'@'%' IDENTIFIED BY '${DB_CONTRASENA}';
CREATE USER IF NOT EXISTS '${DB_USUARIO}'@'localhost' IDENTIFIED BY '${DB_CONTRASENA}';
FLUSH PRIVILEGES;
SQL
mysql -u root < /var/www/html/database/schema.sql
mysql -u root < /var/www/html/database/seed.sql
# Los privilegios se conceden DESPUÉS del schema (que crea la base de datos).
mysql -u root <<SQL
GRANT ALL PRIVILEGES ON \`${DB_NOMBRE}\`.* TO '${DB_USUARIO}'@'%';
GRANT ALL PRIVILEGES ON \`${DB_NOMBRE}\`.* TO '${DB_USUARIO}'@'localhost';
FLUSH PRIVILEGES;
SQL
echo "Base de datos lista."

# --- 3. Generar config/config.php (claves en español, como espera la app) ----
# OJO: las claves deben ser EXACTAMENTE las de config/config.example.php
# (puerto/nombre/usuario/contrasena). El archivo real nunca se versiona (§4.6).
cat > "$CONFIG_FILE" <<PHP
<?php
/**
 * Generado automáticamente por docker/entrypoint-allinone.sh. NO editar a mano
 * dentro del contenedor: ajusta las variables de entorno y recrea el servicio.
 */
declare(strict_types=1);

return [
    'db' => [
        'host' => '127.0.0.1',
        'puerto' => 3306,
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

# --- 3b. Asegurar el directorio de logs interno ------------------------------
# El Front Controller escribe la traza en storage/logs/ (§4.4). El Dockerfile hace
# chown -R www-data, pero si storage/logs es un volumen montado, el montaje pisa
# esos permisos ⇒ PHP no puede escribir y se pierde el error real. Se corrige aquí.
LOG_DIR="/var/www/html/storage/logs"
mkdir -p "$LOG_DIR"
chown -R www-data:www-data /var/www/html/storage
chmod 775 "$LOG_DIR"

# --- 4. Adaptar Apache al puerto que exige la plataforma ($PORT) -------------
if [ -n "${PORT:-}" ] && [ "${PORT}" != "80" ]; then
    sed -ri "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
    sed -ri "s/:80>/:${PORT}>/" /etc/apache2/sites-available/000-default.conf
    echo "Apache escuchando en el puerto ${PORT}."
fi

echo "Iniciando Apache..."
exec apache2-foreground
