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

## 2026-10-19 — Fase 4: Creación de tickets (POST /api/tickets)

**Qué se pidió (usuario):** Implementar la Fase 4 — creación de tickets con consecutivo seguro,
tope de carga y SLA — con commits por fase.

**Qué se generó (agente):**
- Specs: `specs/004-post-tickets/{spec,plan,tasks}.md`.
- `TicketRepository`: `siguienteConsecutivo(anio)` (INSERT IGNORE + SELECT…FOR UPDATE + UPDATE),
  `insertar(datos): id` (nace siempre ABIERTO, sin solución) e `insertarHistorialCreacion(...)`.
- `TecnicoRepository`: `bloquearPorId(id)` (`SELECT…FOR UPDATE`) y `contarActivos(id)`.
- `CategoriaRepository`: `existe(id)`.
- `TicketService::crear` (valida y junta TODOS los errores → 422; verifica usuario/categoría antes de
  bloquear; orquesta la transacción con `Database::transaction`) y `validarCreacion`/`enteroRequerido`.
- `TicketController::crear` (responde 201) y registro de `POST /api/tickets` en `index.php`
  (se inyectó `Database` en el Service).

**Decisiones (para sustentación):**
- Orden de bloqueos técnico → consecutivo (§6.1; al crear no existe fila de ticket), lo que evita
  interbloqueos contra una reasignación que ya bloqueó al técnico.
- El consecutivo NO se calcula con `MAX(numero)+1`: se serializa con `SELECT…FOR UPDATE` sobre la fila
  del año y `tickets.numero UNIQUE` es la segunda barrera.
- Si el tope falla, el ROLLBACK deja el consecutivo sin quemar.

**Verificación (curl, BD sembrada):**
- POST válido → `201`, `TK-2026-000026`, ABIERTO, `vence = creado + 4h` (Alta), historial `CREACION`.
- `422` con `fields` en: descripción corta + prioridad inválida (ambos juntos), usuario inactivo `U010`,
  categoría inexistente y técnico inactivo; `409 TECNICO_AL_TOPE` (técnico 1 en 5); `401` sin clave.
- **Concurrencia (RN-01):** 20 POST en paralelo (liberando capacidad y activando el técnico 4 para
  tener 20 cupos) → **20×201, 20 números únicos y consecutivos (26–45), consecutivo final 45 sin
  saltos, 0 duplicados**. BD restaurada con `seed.sql` después de la prueba.
- Guards: TODO OK.

## 2026-10-19 — Fase 5: Cambio de estado y reasignación (PATCH)

**Qué se pidió (usuario):** Implementar la Fase 5 — PATCH de estado y de técnico — con commits por fase.

**Qué se generó (agente):**
- Specs: `specs/005-patch-estado-tecnico/{spec,plan,tasks}.md`.
- `Domain/EstadoTicket.php`: máquina de estados como DATOS (`ABIERTO→EN CURSO→CERRADO`, CERRADO final),
  con `esValido`, `siguiente` y `permiteTransicion` (§10 O).
- `TicketRepository`: `bloquearPorNumero` (`SELECT…FOR UPDATE`), `actualizarEstado`, `actualizarTecnico` e
  `insertarHistorial(evento)` **genérico** (reemplaza `insertarHistorialCreacion`; DRY entre creación,
  cambio de estado y reasignación).
- `TicketService::cambiarEstado` (422 estado desconocido / 422 cierre sin observación / 409 transición ilegal)
  y `TicketService::reasignarTecnico` (409 si CERRADO, 422 técnico inválido, 409 tope, no-op si es el mismo).
- `TicketController::cambiarEstado` y `reasignarTecnico`; rutas PATCH registradas en `index.php`.

**Decisiones (para sustentación):**
- Estado desconocido → 422 (validación de entrada); transición ilegal → 409 (conflicto con el estado actual).
- Reasignar un CERRADO → `409 TRANSICION_INVALIDA` (RN-07 fija 409; el contrato no define otro código de 409).
- Reasignar al MISMO técnico → no-op 200 (RN-05: no cuenta) sin ruido en el historial.
- Orden de bloqueos ticket → técnico (§6.1); el historial se inserta en la misma transacción (RN-09).

**Verificación (curl + jq, BD sembrada):** ABIERTO→CERRADO y EN CURSO→ABIERTO → `409`; estado `PENDIENTE`
→ `422`; cerrar sin observación → `422 fields.observacion`; cerrar con solución → `200` (`sla=null`,
`solucion` y `cerrado_en` guardados); reasignación a técnico con cupo → `200 REASIGNACION`; reasignar a un
CERRADO → `409`; reasignar a técnico en el tope (5) → `409 TECNICO_AL_TOPE`; mismo técnico → `200` sin
nuevo historial; `404` inexistente y `401` sin clave. Guards: TODO OK. BD restaurada con `seed.sql`.

## 2026-10-19 — Fase 6: SPA (interfaz web)

**Qué se pidió (usuario):** Implementar la Fase 6 — la SPA — como siguiente paso.

**Qué se generó (agente):**
- `public/app.html`: una sola página con cabecera, tablero, filtros, tabla, paginación y dos `<dialog>`
  (detalle y nuevo ticket). Sin recargas.
- `public/assets/js/`: `utils.js` (helper `h` que crea nodos con `createElement`/`textContent`, `escapeHtml`,
  `debounce`, `formatearFecha`), `api.js` (ÚNICO punto de `fetch`, envía `X-API-Key` y traduce el formato único
  de error a un `Error` con `codigo`/`fields`/`http`), `ui.js` (tablero, detalle con historial, insignias,
  avisos y catálogos cacheados), `tabla.js` (filtros + paginación de servidor, 10/página) y `form.js`
  (debounce ~400 ms del usuario, validación y mapeo de `fields` a los inputs).
- `public/assets/css/estilos.css`: estilos propios, sin CDN (funciona sin internet).
- `public/index.php`: intercepta `/` y `/app.html` y sirve el HTML **inyectando la API key** desde
  `config/config.php` (no versionada), reemplazando el marcador `__CLAVE_API__`.

**Decisiones (para sustentación):**
- La API key no se versiona: el HTML se sirve por PHP con la clave inyectada; si `app.html` se abre como
  archivo estático, la SPA detecta el marcador y la pide por pantalla (se recuerda en `localStorage`).
- DOM seguro: todo nodo pasa por `Utils.h`; el guard §12 prohíbe `innerHTML`, así que la tabla y los modales
  se construyen sin HTML crudo (CA-7, §4.5).
- Filtros y paginación 100% en servidor; el cliente no ordena ni recorta en memoria.
- Módulos como IIFEs con un global por archivo; sin bundler ni framework (§7, §2).

**Verificación:**
- `GET /` y `/app.html` → HTML con `apiKey: "mi-clave-secreta-123"` (0 marcadores restantes);
  `/assets/js/api.js` y `/assets/css/estilos.css` servidos con su MIME; `/api/ping` sigue OK.
- `node --check` sin errores en los 5 módulos JS.
- Flujo que consume el front (curl): crear (`TK-2026-000026`), resumen, lista filtrada, cerrar
  (`EN CURSO`→`CERRADO`, `sla=null`) y reasignar (`REASIGNACION`). Guards: TODO OK (tras reformular dos
  comentarios que contenían la palabra prohibida). BD restaurada con `seed.sql`.

## 2026-10-19 — Fase 7: README y colección Postman / .http

**Qué se pidió (usuario):** Continuar; el agente siguió con la Fase 7 (documentación de entrega).

**Qué se generó (agente):**
- `README.md`: descripción, stack, requisitos, instalación paso a paso (config, BD, `schema.sql`/`seed.sql`,
  servidor embebido), tabla de los 9 endpoints, formato único de error, ejemplos `curl` de cada operación,
  pruebas manuales de las reglas (401/404/405/409/422) y el bloque de concurrencia del consecutivo (RN-01),
  estructura del proyecto, tabla de reglas RN-01…RN-09 con su ubicación en el código, `guards.sh` y la
  **declaración de uso de IA** (§15 de AGENTS.md).
- `postman/soporte-ti.http`: archivo `.http` (VS Code REST Client / JetBrains) con los 9 endpoints y 10 casos
  de error etiquetados (E1…E10).
- `postman/Soporte-TI.postman_collection.json`: colección Postman v2.1 importable (22 requests) con scripts de
  aserción (status y `error.code`) y guardado automático de `{{numero}}` al crear.
- `postman/Soporte-TI.local.postman_environment.json`: entorno `Local` con `base_url` y `api_key` (secreto).

**Verificación:**
- JSON de colección y entorno validados con `python3 -m json.tool` (válidos).
- Casos de error ejecutados contra el servidor real y contrastados con el README:
  401 sin clave y con clave mala (`NO_AUTORIZADO`), 405 `DELETE /api/tickets` (`METODO_NO_PERMITIDO`),
  404 ticket inexistente y 404 usuario inactivo (`NO_ENCONTRADO`), 422 paginación
  (`VALIDACION`, `fields.page`) y 422 crear inválido (`fields.descripcion` y `fields.prioridad`).
- `scripts/guards.sh` en verde: el guard de secretos detectó `API_KEY="…"` en el README y se reformuló el
  ejemplo a `MI_CLAVE` con valor placeholder (no es un secreto real, pero el guard es intencionalmente estricto).

## 2026-10-19 — Fase 8 (parte 1): PHPUnit y export CSV

**Qué se pidió (usuario):** Continuar con la Fase 8 (extras); aprobó correr `composer install`
y dejar Docker para el final de la fase.

**Qué se generó (agente):**
- `phpunit.xml` (PHPUnit 13.4) con dos suites: `Unit` (dominio puro) e `Integration` (BD real).
- `tests/Unit/EstadoTicketTest.php` (RN-06), `SlaCalculatorTest.php` (RN-08) y `TopeTecnicoTest.php` (RN-05):
  transiciones válidas e inválidas (saltar/retroceder/reabrir), umbrales de SLA
  (EN_TIEMPO/POR_VENCER/VENCIDO/null) y el borde del tope (4 recibe, 5 no), con data providers.
- `tests/Integration/IntegrationTestCase.php` + `FlujoTicketTest.php`: flujo real contra la BD
  (crear → EN CURSO → CERRADO con historial), 409 por salto de estado, 422 al cerrar sin solución,
  422 con usuario inactivo y **tope sin quemar el consecutivo** (ROLLBACK). Limpia lo que crea y,
  sin `config.php`/MySQL, se omite en vez de fallar.
- `src/Domain/CsvExporter.php`: generador CSV puro (RFC 4180) con **neutralización de fórmulas**
  (CSV injection). `tests/Unit/CsvExporterTest.php` lo cubre.
- Exportación CSV extremo a extremo: `TicketRepository::listarParaExportar()` (mismos filtros, sin paginar),
  `TicketService::exportarCsv()` (reutiliza la validación de filtros vía `criteriosFiltros()`),
  `Response::descarga()` (cuerpo no-JSON con su MIME) y la ruta `GET /api/tickets/export.csv`.
- SPA: botón «Exportar CSV» que descarga con los filtros activos; `Api.exportarCsv()` usa `fetch`
  con `X-API-Key` (un `<a href>` no enviaría el encabezado).
- `composer.json`: autoload-dev `Tests\ => tests/`. `.gitignore`: `.phpunit.cache/`.

**Decisiones (para sustentación):**
- Las pruebas de dominio no tocan la BD: el «ahora» del SLA entra por parámetro (clase pura).
- La integración se autocontiene (crea y borra sus datos) y se omite si no hay BD, para no ensuciar el seed.
- El consecutivo se verifica con ROLLBACK: al fallar el tope, `ultimo` no avanza.
- CSV con `;` y `\r\n`; celdas entrecomilladas y fórmulas neutralizadas con apóstrofo.

**Verificación:**
- `./vendor/bin/phpunit` → **38 tests, 65 aserciones, OK** (33 unit + 5 integración).
- `GET /api/tickets/export.csv?prioridad=Alta` → `200 text/csv`, `Content-Disposition: attachment`,
  8 filas + encabezado; sin clave → `401` JSON.
- `scripts/guards.sh` → TODO OK. BD restaurada con `seed.sql` tras las pruebas.

## 2026-10-19 — Fase 8 (parte 2): Docker

**Qué se pidió (usuario):** Terminar la Fase 8 con Docker (se dejó para el final, como se acordó).

**Qué se generó (agente):**
- `Dockerfile`: imagen `php:8.1-apache` con `pdo_mysql`, docroot en `public/`, `AllowOverride All`
  para el `.htaccess`, ini con `date.timezone = America/Bogota` y `display_errors = 0`, y `composer install --no-dev`.
- `docker-compose.yml`: servicios `app` (Apache+PHP) y `db` (`mysql:8.0`), con `schema.sql` y `seed.sql`
  montados como scripts de initdb, `healthcheck` de MySQL y volúmenes para datos y logs.
- `docker/entrypoint.sh`: genera `config/config.php` desde variables de entorno (el secreto no se versiona)
  y espera a que MySQL acepte conexiones antes de arrancar Apache.
- `.env.example` (plantilla versionada) y `.dockerignore` (no copia `vendor/`, `config/config.php`, `.env`, tests).
- `public/.htaccess`: reescribe todo al Front Controller para Apache (el servidor embebido no lo necesitaba).
- `public/index.php`: la inyección de la API key en la SPA ahora aplica también bajo Apache (antes solo `cli-server`).
- `README.md`: sección de instalación con Docker y estructura del proyecto actualizada.

**Decisión relevante:** el nombre de los contenedores chocaba con el MySQL de desarrollo local
(`tickets-db`); el compose es autocontenido y se validó levantándolo en otro puerto (`APP_PUERTO`).

**Verificación (docker compose up --build en el puerto 8090):**
- `GET /api/ping` con clave → `{"status":"ok"}`; sin clave → `401`.
- SPA servida con la API key inyectada (`apiKey: "cambia-esta-clave-de-api"`).
- `GET /api/tickets` → lista con datos del seed; `GET /api/tickets/export.csv?prioridad=Alta` → `text/csv`.
- Transición inválida ABIERTO→CERRADO → `409`. Assets estáticos servidos por Apache (`api.js` → `text/javascript`).
- Al terminar: `docker compose down -v` y se restauró el contenedor de desarrollo. Guards: TODO OK.
