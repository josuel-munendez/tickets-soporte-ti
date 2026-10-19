# Plan — Fase 1: Estructura base, configuración, enrutamiento, API Key y excepciones

> **Estado:** Propuesta (pendiente de aprobación)
> **Spec:** `specs/001-estructura-base/spec.md` | **Tareas:** `specs/001-estructura-base/tasks.md`

## 1. Archivos nuevos

```text
composer.json                          # PSR-4 "App\" => src/ (ver decisión D1)
config/config.example.php              # Plantilla versionada, sin secretos reales
src/Support/Config.php                 # Carga y consulta de configuración
src/Support/Database.php               # PDO estricto + transaction()
src/Exceptions/ApiException.php        # Base abstracta: estado HTTP + code + fields
src/Exceptions/ValidacionException.php # 422 VALIDACION (fields: {campo: mensaje})
src/Exceptions/NoEncontradoException.php # 404 NO_ENCONTRADO
src/Exceptions/ConflictoException.php  # 409 TRANSICION_INVALIDA | TECNICO_AL_TOPE
src/Http/Request.php                   # Envoltorio de $_SERVER/$_GET/headers/body
src/Http/Response.php                  # Value object + factory json()/error() + enviar()
src/Http/Router.php                    # Registro de rutas, dispatch, 404/405, middlewares
src/Http/Middlewares/ApiKeyMiddleware.php # Valida X-API-Key con hash_equals
public/index.php                       # Front Controller: config, rutas, try/catch global
```

No se tocan `database/*`, ni crean Controladores/Servicios/Repositorios (fases 3-5).

## 2. Contrato interno de cada pieza

### `config/config.example.php`
Array plano retornado con las claves mínimas del MVP (sin SLA/carga: eso es dominio, va en `Domain`
en fases posteriores, no en configuración):

```php
return [
    'db' => [
        'host' => '127.0.0.1',
        'puerto' => 3306,
        'nombre' => 'soporte_ti',
        'usuario' => 'root',
        'contrasena' => '',        // placeholder en el ejemplo
        'charset' => 'utf8mb4',
    ],
    'api_key' => 'tu_clave_secreta_aqui',  // valor placeholder (pasa el guard de secretos)
    'zona_horaria' => 'America/Bogota',
];
```

### `src/Support/Config.php` (singleton mínimo, cargable en tests)
- `Config::cargar(string $rutaArchivo): void` — `require` del archivo; si no existe o no retorna `array`,
  lanza `RuntimeException` (la captura el handler global → 500 sin trazas).
- `Config::obtener(string $clave, mixed $defecto = null): mixed` — claves anidadas con punto (`db.host`).
- `Config::apiKey(): string` — atajo tipado para el middleware.

### `src/Support/Database.php` (capa Support: único lugar con PDO junto a Repositories)
- `__construct(array $dbConfig)` — recibe `Config::obtener('db')` (inyección, sin globals).
- `conexion(): PDO` — lazy; DSN `mysql:host=...;port=...;dbname=...;charset=utf8mb4`,
  atributos estrictos (§4.2), y de inmediato `SET time_zone = '-05:00'` (§2).
- `transaction(callable $fn): mixed` — `BEGIN`; `$resultado = $fn($pdo)`; `COMMIT`; ante `Throwable`:
  `ROLLBACK` y relanza. Es el único camino transaccional del proyecto (lo usarán las fases 4-5).

### `src/Exceptions/*`
- `ApiException` (abstracta): `__construct(string $mensaje, int $estado, string $codigo, array $fields = [])`
  con getters `estado()`, `codigo()`, `fields()`.
- `ValidacionException`: 422, `VALIDACION`, `fields` = `{campo: mensaje}`.
- `NoEncontradoException`: 404, `NO_ENCONTRADO`.
- `ConflictoException`: 409, código parametrizado (`TRANSICION_INVALIDA`, `TECNICO_AL_TOPE`).

### `src/Http/Request.php`
Construido desde superglobales: `metodo()`, `ruta()` (path sin query), `parametro(string $clave, mixed $defecto)`
(nombre elegido en fase 3 para no chocar con el patrón `->query(` del guard de capas),
`header(string $nombre): ?string`, `json(): array` (body JSON; si está malformado lanza
`ValidacionException` 422). No contiene lógica de negocio.

### `src/Http/Response.php`
Value object inmutable `(int $estado, array $datos)`:
- `Response::json(array $datos, int $estado = 200): self`
- `Response::error(string $codigo, string $mensaje, int $estado, array $fields = []): self`
  → arma `{"error":{code,message,fields}}` (formato único §5).
- `enviar(): void` — `Content-Type: application/json; charset=utf-8`, `X-Content-Type-Options: nosniff`,
  `http_response_code()`, `echo json_encode(..., JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)`.
  Si `json_encode` falla, responde 500 `ERROR_INTERNO` genérico.

### `src/Http/Router.php`
- `agregar(string $metodo, string $patron, callable $handler): void` — patrones con segmentos `{param}`.
- `usarMiddleware(callable $middleware): void` — pila global (hoy solo `ApiKeyMiddleware`; todo es `/api/*`).
- `resolver(Request $peticion): Response` —
  1. Coincidencia de patrón → extrae parámetros y arma la cadena de middlewares → handler.
  2. Patrón coincide pero método no → `405` `METODO_NO_PERMITIDO` + cabecera `Allow` (ver decisión D3).
  3. Sin patrón → `404` `NO_ENCONTRADO`.
- Los handlers reciben `(Request $peticion, array $parametros): Response`. En esta fase solo hay una ruta
  de salud `GET /api/ping` → `{"status":"ok"}` (no es negocio; permite probar el esqueleto).

### `src/Http/Middlewares/ApiKeyMiddleware.php`
- Invocable: `__invoke(Request $peticion, callable $siguiente): Response`.
- Sin encabezado o clave distinta → `Response::error('NO_AUTORIZADO', ..., 401)`.
- Comparación con `hash_equals()` (§4.3). Si la clave configurada está vacía → nunca autentica
  (fail-closed) y `error_log` del problema de configuración (decisión D5).

### `public/index.php` (único punto de entrada web de la API)
1. `ini_set('display_errors', '0'); error_reporting(E_ALL);`
2. `date_default_timezone_set('America/Bogota')` (redundante con config, defensa en profundidad).
3. Autoload (ver D1), `Config::cargar(config/config.php)`.
4. Construye `Router`, registra `ApiKeyMiddleware` y la ruta `GET /api/ping`.
5. `try { $router->resolver($request)->enviar(); } catch (ApiException) { respuesta con su code/estado; }
   catch (Throwable) { log a storage/logs/error.log + 500 ERROR_INTERNO genérico; }`

## 3. Decisiones tomadas y alternativas descartadas

- **D1 — Autoload sin Composer.** `composer` no está instalado en este entorno. Se versiona igual
  `composer.json` (PSR-4 `App\` ⇒ `src/`, requisitos y herramientas dev) y `public/index.php` usa
  `vendor/autoload.php` si existe; si no, un autoloader PSR-4 propio de ~12 líneas. *Descartado:*
  `require` manual de cada archivo (frágil al crecer).
- **D2 — Config estática ligera.** `Config` con carga única y lectura por clave anidada. *Descartado:*
  contenedor de dependencias/DI full (sobrediseño para un MVP; la inyección manual en `index.php`
  — §10 D — basta y llega en las fases de negocio).
- **D3 — 405 con código propio `METODO_NO_PERMITIDO`.** El §5 no asigna código a 405 pero exige el
  formato único "incluso 404/405/500". *Descartado:* reusar `NO_ENCONTRADO` (semántica incorrecta).
  Efecto: el listado de códigos de `docs/04-api.md`/OpenAPI llevará este código extra (documentación viva).
- **D4 — Logging interno con `error_log()` destino `storage/logs/error.log`** (fallback al log del
  servidor si el directorio no se puede crear), con fecha y traza completa. *Descartado:* imprimir la
  traza en la respuesta (viola §4.4).
- **D5 — API key "fail-closed".** Clave vacía en configuración ⇒ nadie autentica (401 siempre).
  *Descartado:* tratar clave vacía como "sin auth" (habría abierto la API por error de config).
- **D6 — Middlewares como callables encadenables** (`Request, callable $siguiente => Response`).
  *Descartado:* interfaz con un único método (más ceremonia de la necesaria hoy; si crece, se extrae interfaz).

## 4. Riesgos y verificación

- El guard de capas exige que `new PDO`/`->exec()` solo vivan en `src/Support` → se cumple.
- El guard de secretos exige que `config.example.php` use placeholders → valores `tu_...`.
- Pruebas manuales de la fase (curl contra `php -S`): ver `tasks.md` tarea final y `docs/07-pruebas.md`
  cuando exista (fase de documentación).
