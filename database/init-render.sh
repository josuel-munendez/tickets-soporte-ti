#!/usr/bin/env bash
# Inicializa la base de datos en un proveedor gestionado (Render, Railway...),
# donde la base YA existe y NO se tiene permiso de CREATE DATABASE/USE.
#
# Uso:
#   DB_URL="mysql://usuario:pass@host:puerto/nombre" bash database/init-render.sh
#
# Toma database/schema.sql y database/seed.sql, elimina las sentencias
# CREATE DATABASE / USE (que solo aplican en local) y las aplica en orden.
set -euo pipefail

: "${DB_URL:?Define DB_URL, p. ej. mysql://user:pass@host:3306/soporte_ti}"

echo "Cargando esquema y datos semilla desde DB_URL ..."

filtrar() {
    # Quita CREATE DATABASE ... (hasta el ';') y cualquier línea USE ...;
    sed -E '/^[[:space:]]*CREATE DATABASE/,/;/d; /^[[:space:]]*USE[[:space:]].*;/d' "$1"
}

for archivo in database/schema.sql database/seed.sql; do
    echo "  -> ${archivo}"
    filtrar "$archivo" | php -r '
        $url = getenv("DB_URL");
        $p = parse_url($url);
        $dsn = sprintf("mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4",
            $p["host"], $p["port"] ?? 3306, ltrim($p["path"] ?? "", "/"));
        $pdo = new PDO($dsn, urldecode($p["user"] ?? ""), urldecode($p["pass"] ?? ""), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $sql = stream_get_contents(STDIN);
        $pdo->exec($sql);
        echo "     ok\n";
    '
done

echo "Listo. La base de datos quedó con el esquema y los datos de prueba."
