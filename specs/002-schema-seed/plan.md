# Plan — Fase 2: Schema y Seed contra MySQL real

> **Spec:** `specs/002-schema-seed/spec.md`

## Entorno
- Contenedor Docker `tickets-db` (imagen `mysql:8.0`, puerto 3306, `root/root`).
- Esquema y seed ya versionados desde el commit inicial; esta fase NO los modifica:
  se cargan, se verifican y se validan contra las reglas de negocio.

## Verificaciones ejecutadas
1. Carga de `database/schema.sql` y `database/seed.sql` con el cliente `mariadb` contra MySQL 8.0.46.
2. Conteos por estado, carga activa por técnico, vencidos, inactivos, categorías y consecutivo.
3. Re-ejecución del seed (idempotencia).
4. Pruebas negativas de CHECK (CERRADO sin solución; descripción corta) ⇒ rechazadas, 0 filas persistidas.
5. Integración con PHP: `Config::cargar` + `Database::conexion` (time_zone de sesión) y
   `Database::transaction` (COMMIT y ROLLBACK con excepción simulada).

## Decisiones
- **D1 — Credenciales locales del contenedor (`root/root`) solo en `config/config.php` local**
  (ignorado por Git); `config.example.php` conserva placeholders. Nada de secretos versionados.
- **D2 — El seed usa fechas relativas a `NOW()`** en vez de fijas: la demo siempre tendrá
  vencidos y por vencer, sin depender del día en que se ejecute.
