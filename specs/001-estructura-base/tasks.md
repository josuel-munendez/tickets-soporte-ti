# Tasks — Fase 1: Estructura base, configuración, enrutamiento, API Key y excepciones

> Spec: `spec.md` | Plan: `plan.md`
> Marcar `[x]` solo al terminar cada tarea. Una fase = 1 commit convencional (AGENTS.md §9.3).

## Tareas

- [x] T1. Crear `config/config.example.php` con `db`, `api_key` (placeholder `tu_...`) y `zonа_horaria`.
- [x] T2. Crear `src/Support/Config.php` (`cargar`, `obtener` anidada, `apiKey`) con `strict_types`.
- [x] T3. Crear `src/Support/Database.php`: PDO estricto, DSN utf8mb4, `SET time_zone = '-05:00'`, `transaction(callable)`.
- [x] T4. Crear `src/Exceptions/ApiException.php` (abstracta: estado, code, fields).
- [x] T5. Crear `ValidacionException` (422), `NoEncontradoException` (404), `ConflictoException` (409 parametrizado).
- [x] T6. Crear `src/Http/Request.php` (metodo, ruta, query, header, `json()` con 422 ante JSON malformado).
- [x] T7. Crear `src/Http/Response.php` (`json()`, `error()` formato único, `enviar()` con cabeceras + nosniff).
- [x] T8. Crear `src/Http/Router.php` (agregar, middlewares, resolver con 404/405 + `Allow`).
- [x] T9. Crear `src/Http/Middlewares/ApiKeyMiddleware.php` con `hash_equals` y fail-closed.
- [x] T10. Crear `composer.json` (PSR-4 `App\` => `src/`, PHP >=8.1, herramientas dev).
- [x] T11. Crear `public/index.php`: display_errors=0, timezone, autoload, config, rutas, try/catch global con log a `storage/logs/`.
- [x] T12. Crear `config/config.php` local (copia de valores reales locales, NO se versiona) para poder probar.
- [x] T13. Ejecutar `bash scripts/guards.sh` y corregir cualquier fallo.
- [x] T14. Pruebas manuales con `php -S localhost:8000 -t public`:
  - `curl -i http://localhost:8000/api/ping` sin key → 401 `NO_AUTORIZADO`.
  - `curl -i -H "X-API-Key: incorrecta" .../api/ping` → 401.
  - `curl -i -H "X-API-Key: <clave válida>" .../api/ping` → 200 `{"status":"ok"}`.
  - `curl -i -H "X-API-Key: <clave válida>" .../api/noexiste` → 404 `NO_ENCONTRADO`.
  - `curl -i -X POST -H "X-API-Key: <clave válida>" .../api/ping` → 405 `METODO_NO_PERMITIDO` + `Allow: GET`.
  - Renombrar temporalmente `config/config.php` → cualquier endpoint → 500 `ERROR_INTERNO` sin trazas.
- [x] T15. Actualizar `docs/ai-log.md` con lo pedido y generado en esta fase.
