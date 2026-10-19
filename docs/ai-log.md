# Bitácora de uso de IA (`docs/ai-log.md`)

> AGENTS.md §9.6: registro de qué se pidió, qué se generó y qué se modificó en cada intervención.

## 2026-10-18 — Fase 1: Estructura base, configuración, enrutamiento, API Key y excepciones

**Qué se pidió (usuario):**
Implementar la Fase 1 del plan de fases: configuración externa, PDO estricto con zona horaria de
Colombia, excepciones HTTP, Router/Request/Response, middleware de API Key y Front Controller con
formato único de error. Sin endpoints de negocio. Aprobados en el modo plan: código propio
`METODO_NO_PERMITIDO` para 405, y permiso explícito para que el agente haga los commits con GitFlow.

**Qué se generó (agente):**
- Specs de la fase: `specs/001-estructura-base/{spec,plan,tasks}.md` (SDD, AGENTS.md §9).
- `config/config.example.php` (plantilla sin secretos; `api_key` con placeholder que pasa el guard).
- `src/Support/Config.php` (carga segura, claves anidadas con punto, `apiKey()`).
- `src/Support/Database.php` (PDO estricto, DSN utf8mb4, `SET time_zone = '-05:00'`, `transaction(callable)`).
- `src/Exceptions/{ApiException,ValidacionException,NoEncontradoException,ConflictoException}.php`.
- `src/Http/{Request,Response,Router}..php` y `src/Http/Middlewares/ApiKeyMiddleware.php`
  (`hash_equals`, política fail-closed si la clave configurada está vacía).
- `public/index.php` (Front Controller: `display_errors=0`, autoload, rutas, try/catch global,
  log de trazas en `storage/logs/`, 500 genérico sin fuga de información).
- `composer.json` ampliado con autoload PSR-4 `App\` => `src/`.

**Qué se modificó (agente):**
- `composer.json`: se agregó `autoload.psr-4`, `require.php >=8.1`, nombre y descripción del paquete.
- Limpieza de debris creado por comandos de prueba del usuario: archivos vacíos en la raíz
  (`.env.example`, `config.example.php`, `config.php`) y carpetas vacías `sql/`, `src/Config/`
  (el proyecto usa `database/` y `src/Support/`, según AGENTS.md §3 y §8).

**Decisiones técnicas destacadas (para sustentar):**
1. `METODO_NO_PERMITIDO` (405) como código propio del formato único: §5 exige formato único
   "incluso 405" pero no asignaba código; reusar `NO_ENCONTRADO` habría sido semánticamente falso.
2. API key "fail-closed": clave vacía en configuración ⇒ nadie autentica. Una config rota nunca
   deja la API abierta.
3. Autoload con fallback PSR-4 propio en `index.php` por si `vendor/` no existe (Composer ya instalado
   en el entorno; el fallback solo cubre el caso borde).

**Verificación:** `bash scripts/guards.sh` → TODO OK. Pruebas manuales con `php -S localhost:8080`:
401 sin clave, 401 con clave inválida, 200 en `/api/ping` con clave válida, 404 en ruta inexistente,
405 + `Allow: GET` en método incorrecto, 500 genérico con `config/config.php` ausente (traza completa
en `storage/logs/error_2026-10-18.log`, ninguna fuga en la respuesta).
