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

## 2026-10-18 — Fase 2: Schema y Seed contra MySQL real

**Qué se pidió (usuario):** Commitear el estado de guardado de Postman (colección + entorno Local,
en YAML dentro de `postman/`, con placeholder `tu-clave-secreta`, sin secretos reales) y ejecutar
la Fase 2: cargar y verificar `schema.sql`/`seed.sql` en el MySQL real.

**Qué se generó (agente):**
- Specs de la fase: `specs/002-schema-seed/{spec,plan,tasks}.md` con los criterios verificados.
- Commit de guardado de Postman: `chore(postman): guarda estado inicial de coleccion y entorno Local`.

**Qué se verificó (agente):**
- Carga exitosa en el contenedor Docker `tickets-db` (MySQL 8.0.46, puerto 3306): 6 tablas.
- Seed: 10 usuarios (1 inactivo), 4 técnicos (1 inactivo), 4 categorías, 25 tickets
  (6 ABIERTO / 6 EN CURSO / 13 CERRADO), 59 filas de historial, consecutivo 2026 = 25.
- Tope de carga respetado: técnico 1 = 5, técnico 2 = 4, técnico 3 = 3, técnico 4 (inactivo) = 0.
- 4 tickets VENCIDOS presentes (fechas relativas a `NOW()` ⇒ la demo siempre los tendrá).
- Idempotencia: segunda ejecución del seed mantiene los mismos conteos.
- CHECK rechazan CERRADO sin solución y descripción < 20 caracteres (0 filas persistidas).
- `Support\Database` contra la BD real: sesión con `time_zone = -05:00`, `transaction()` con
  COMMIT y ROLLBACK (excepción simulada ⇒ `ultimo` del consecutivo intacto en 25).

**Qué se modificó (agente):**
- `config/config.php` local (NO versionado): contraseña del contenedor (`root`) para poder conectar.
  `config.example.php` sigue con placeholders.

**No modificado:** `database/schema.sql` y `database/seed.sql` pasaron las verificaciones sin cambios.

## 2026-10-18 — Fase 3: Endpoints de lectura (GET) y tablero

**Qué se pidió (usuario):** Implementar la Fase 3 — endpoints GET de consulta, detalle, filtros y
resumen — con commits por fase y luego seguir.

**Qué se generó (agente):**
- Specs: `specs/003-endpoints-get/{spec,plan,tasks}.md`.
- `src/Domain/Constantes.php` (MAX_TICKETS_TECNICO=5, ESTADOS, PRIORIDADES) y
  `src/Domain/SlaCalculator.php` (RN-08: Alta 4h/Media 24h/Baja 72h; VENCIDO/POR_VENCER/EN_TIEMPO/null;
  recibe `now` por parámetro ⇒ testeable sin congelar el reloj).
- Repositories: `UsuarioRepository`, `TecnicoRepository` (carga activa por SQL), `CategoriaRepository`,
  `TicketRepository` (listado con filtros + paginación, detalle, historial, conteos, vencidos).
- Services: `TicketService` (valida filtros → 422 VALIDACION con `fields`, formatea ISO-8601 `-05:00`
  y calcula el indicador SLA), `UsuarioService` (404 si inexistente/inactivo, RN-02), `TecnicoService`,
  `CategoriaService`.
- Controllers delgados: `TicketController` (listar/detalle/resumen), `UsuarioController`,
  `TecnicoController`, `CategoriaController`.
- `public/index.php`: inyección manual PDO → Repos → Services → Controllers y registro de las 6 rutas GET.

**Qué se modificó (agente):**
- `src/Http/Request.php`: `query()` renombrado a `parametro()` porque el guard de capas (§12) leía
  `->query(` como acceso a PDO. Se actualizaron `TicketController` y las notas de la fase 1.

**Incidencias resueltas durante las pruebas (para sustentar):**
1. `q=Torres` daba 500 `SQLSTATE[HY093]`: con prepares nativos (`EMULATE_PREPARES=false`) un mismo
   placeholder no puede repetirse en el SQL; se usaron `:q_numero` y `:q_usuario` con el mismo valor.
2. `/api/resumen` devolvía 404: se había registrado como `/api/tickets/resumen`; el contrato §5 usa la
   ruta raíz `/api/resumen`. Corregido.
3. Guard de capas: falso positivo por el nombre `query()` en Request ⇒ renombrado a `parametro()`.

**Verificación (curl con clave válida, BD sembrada):** lista (total 25, ISO `-05:00`, SLA), filtros
combinados, `q` por nombre y por número, paginación (page=3 → 5 filas), detalle con historial, 404 de
ticket, usuario activo 200 / inactivo 404 / inexistente 404, técnicos con carga 5/4/3 y `disponible`,
categorías, resumen (`por_estado`, `vencidos=4`, `carga_por_tecnico`), 422 con `fields` para
`page=0`/`per_page=99`/`estado=X`/`tecnico=abc`, y 401 sin clave. Guards: TODO OK.
