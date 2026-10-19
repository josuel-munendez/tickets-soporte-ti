# Spec — Fase 1: Estructura base, configuración, enrutamiento, API Key y excepciones

> **Estado:** Propuesta (pendiente de aprobación)
> **Reglas aplicables:** AGENTS.md §2 (stack), §3 (capas), §4 (seguridad), §5 (contrato de error único), §9 (fases), §10 (SOLID).

## 1. Contexto y objetivo

El proyecto parte de cero: solo existen `database/schema.sql`, `database/seed.sql` y los scripts de guards.
Esta fase monta el esqueleto de la aplicación (capas HTTP + soporte) de modo que las fases siguientes
(endpoints de negocio) solo tareas que enchufen controladores y servicios sobre esta base.

## 2. Alcance

**Sí:**
- Configuración externa versionada (`config.example.php`) y su carga segura.
- Conexión PDO estricta con UTF8MB4, zona horaria `America/Bogota` y helper de transacciones.
- Excepciones de dominio HTTP (`Validacion`, `NoEncontrado`, `Conflicto`) sobre una base común.
- Router, Request y Response.
- Middleware de API Key con `hash_equals`.
- Front Controller (`public/index.php`) con manejo global de errores y formato único de error JSON.

**No (fases posteriores):** endpoints de negocio (`tickets`, `usuarios`, `tecnicos`, `resumen`, `categorias`),
capas Service/Repository/Domain, esquema/seed (ya existen), SPA (`app.html`), README/Postman, Docker/PHPUnit.

## 3. Historias de usuario y criterios de aceptación

### H1 — Configuración fuera del código
Como desarrollador, quiero que las credenciales vivan fuera del código para no filtrar secretos.

- **CA-1.1** Dado que existe `config/config.example.php` versionado, cuando el desarrollador lo copia a
  `config/config.php` y completa los valores, entonces la aplicación carga la configuración sin errores.
- **CA-1.2** Dado que `config/config.php` NO existe, cuando llega cualquier petición a `/api/*`,
  entonces la respuesta es `500` con `{"error":{"code":"ERROR_INTERNO",...}}` y sin traza PHP alguna.
- **CA-1.3** Dado que `config/config.php` está en `.gitignore`, cuando se ejecutan los guards,
  entonces no hay alerta de secretos y el archivo jamás se commitea.

### H2 — Conexión a base de datos estricta
Como desarrollador, quiero una única forma de abrir PDO, con atributos estrictos, UTF8MB4 y la zona
horaria de Colombia, para que todas las fechas del sistema sean consistentes (UTC-05:00).

- **CA-2.1** Dado que `Database` construye el PDO, cuando se ejecuta cualquier consulta posterior,
  entonces se usan sentencias preparadas (`ERRMODE_EXCEPTION`, `EMULATE_PREPARES = false`,
  `FETCH_ASSOC`).
- **CA-2.2** Dado que se abre la conexión, cuando se ejecuta `SET time_zone = '-05:00'`,
  entonces `NOW()` de MySQL y `date()` de PHP devuelven la hora local de Bogotá.
- **CA-2.3** Dado que un negocio lanza una excepción dentro de `Database::transaction()`,
  entonces se hace `ROLLBACK` y la excepción se propaga; si termina bien, `COMMIT`.

### H3 — Autenticación por API Key
Como cliente de la API, debo enviar `X-API-Key`; sin clave o con clave inválida la API responde 401.

- **CA-3.1** Dado una petición a `/api/*` sin encabezado `X-API-Key`, cuando el middleware la evalúa,
  entonces responde `401` `NO_AUTORIZADO` en el formato único de error.
- **CA-3.2** Dado una petición con `X-API-Key` distinta a la configurada, cuando se compara,
  entonces responde `401` `NO_AUTORIZADO` (comparación con `hash_equals`, sin cortes por tiempo).
- **CA-3.3** Dado una petición con `X-API-Key` correcta, cuando el middleware la evalúa,
  entonces la petición continúa al handler correspondiente.
- **CA-3.4** Dado que la clave configurada está vacía, cuando llega cualquier petición,
  entonces NUNCA autentica (falla cerrado) y se registra el error de configuración en el log interno.

### H4 — Enrutamiento
Como cliente, quiero que las rutas se registren declarativamente para que agregar un endpoint no toque
el Front Controller.

- **CA-4.1** Dado una ruta registrada con parámetros (ej. `/api/tickets/{numero}`), cuando llega
  `GET /api/tickets/TK-2026-000042`, entonces el handler recibe `numero = "TK-2026-000042"`.
- **CA-4.2** Dado una ruta inexistente (ej. `/api/xxx`), cuando llega la petición,
  entonces responde `404` `NO_ENCONTRADO` en formato único.
- **CA-4.3** Dado que la ruta existe pero el método no coincide (ej. `POST /api/ping`),
  entonces responde `405` con código `METODO_NO_PERMITIDO` y encabezado `Allow`.
- **CA-4.4** Dado que existe `GET /api/ping` (salud, no es negocio), cuando se llama con la API key,
  entonces responde `200` `{"status":"ok"}`. Sirve para probar el esqueleto sin BD.

### H5 — Formato único de error y manejo global
Como cliente, quiero que TODO error (401/404/405/409/422/500) tenga la misma forma JSON, sin excepciones.

- **CA-5.1** Dado cualquier error controlado (`ApiException`), cuando el handler global lo captura,
  entonces responde con su código HTTP, `{"error":{"code","message","fields"}}` y cabeceras JSON.
- **CA-5.2** Dado un `Throwable` inesperado (ej. BD caída), cuando el handler global lo captura,
  entonces responde `500` `ERROR_INTERNO` genérico, la traza se guarda en log interno
  (`storage/logs/`) y NUNCA se expone al cliente.
- **CA-5.3** Dado que `display_errors` está en `0`, cuando ocurre un error PHP,
  entonces no hay salida HTML en la respuesta: siempre JSON.

## 4. Casos de error (resumen)

| Situación | HTTP | code |
| --- | --- | --- |
| Sin `X-API-Key` | 401 | `NO_AUTORIZADO` |
| `X-API-Key` inválida | 401 | `NO_AUTORIZADO` |
| Ruta desconocida | 404 | `NO_ENCONTRADO` |
| Método no permitido | 405 | `METODO_NO_PERMITIDO` *(extiende el listado de §5; se documenta en `docs/04-api.md`)* |
| Body JSON malformado | 422 | `VALIDACION` |
| `config/config.php` ausente o ilegible | 500 | `ERROR_INTERNO` |
| Cualquier otro `Throwable` | 500 | `ERROR_INTERNO` |

## 5. Requisitos no funcionales

- `declare(strict_types=1)` en todo PHP.
- Todo `/api/*` responde `Content-Type: application/json; charset=utf-8` y `X-Content-Type-Options: nosniff`.
- Cero SQL en esta fase (la conexión solo se construye; se usa desde Fase 2).
- Los guards de `scripts/guards.sh` deben pasar (SQL confinado a `Repositories/Support`, sin depuración, sin secretos).
