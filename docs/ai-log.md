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

## 2026-10-19 — Fase 9: Documentación Doc-as-Code

**Qué se pidió (usuario):** «Seguir con la documentación» (completar `docs/`, AGENTS.md §11).

**Qué se generó (agente):**
- `docs/00-vision-y-alcance.md`: problema, visión, alcance del MVP, actores, principios y restricciones.
- `docs/01-requisitos.md`: 17 RF, 10 RNF y 6 RI, cada uno con trazabilidad a regla (RN-xx), endpoint y/o test.
- `docs/02-arquitectura.md`: C4 (contexto, contenedores, componentes) y diagrama de secuencia del POST /api/tickets.
- `docs/03-modelo-datos.md`: diagrama ER (Mermaid) y diccionario de datos de las 6 tablas, derivado de `schema.sql`.
- `docs/04-api.md`: contrato REST en Markdown con ejemplos `curl` reales; `docs/openapi.yaml` (OpenAPI 3.0) validado.
- `docs/05-reglas-negocio.md`: RN-01…RN-09 con diagrama de estados y mapa de códigos HTTP.
- `docs/06-decisiones/ADR-001…005.md`: PHP sin framework, consecutivo seguro, API key fail-closed, SPA vanilla, reglas como datos (con alternativas descartadas).
- `docs/07-pruebas.md`: matriz casos ↔ requisitos, resultado de PHPUnit y checklist de la Definición de Terminado.
- `docs/diagrams/`: fuentes `.puml` (contexto, estados) y `.mmd` (contenedores, estados), validadas con PlantUML.
- `specs/009-documentacion/` (spec/plan/tasks) y README con índice de documentación (§12).

**Decisiones de documentación:**
- La doc se derivó del código (schema, Controllers/Services, respuestas reales de la API) para no inventar campos.
- Diagramas como texto (Mermaid + PlantUML) para versionarlos y re-renderizarlos.
- ADR con alternativas descartadas, porque la sustentación exige defender el «por qué».

**Verificación:**
- `docs/openapi.yaml` → YAML válido (10 rutas, 10 esquemas). Diagramas `.puml` compilan con PlantUML.
- `scripts/guards.sh` → TODO OK. Suite PHPUnit sigue en verde (38 tests).

## 2026-10-19 — Fase 10: Corrección del compose (nombres de contenedor)

**Qué se pidió (usuario):** Al seguir el README y correr `docker compose up --build`, falló con
`Conflict. The container name "/tickets-db" is already in use`.

**Diagnóstico (agente):** el `docker-compose.yml` fijaba `container_name: tickets-db` / `tickets-app`,
nombres genéricos que colisionan con cualquier contenedor ajeno (o con un levante previo). Además el
usuario intentó los pasos manuales de `mysql` contra el socket del host, que no aplican cuando la BD
vive en un contenedor.

**Qué se generó (agente):**
- `docker-compose.yml`: se **eliminaron los `container_name` fijos**; Compose pasa a nombrar
  `<proyecto>-db-1` / `<proyecto>-app-1`, sin colisiones.
- `README.md`: nota aclarando que con Docker se **omiten** los pasos manuales de `mysql`, y que los
  contenedores llevan nombre derivado del proyecto.

**Verificación:** `docker compose up -d --build` levanta `tickets-soporte-ti-db-1` (healthy) y
`tickets-soporte-ti-app-1`; `GET /api/tickets` responde con el seed; SPA `200`; 401 sin clave.
`docker compose down` limpia. Guards y PHPUnit en verde.

## 2026-10-19 — Fase 11: Despliegue en línea (Render) + limpieza del README

**Qué se pidió (usuario):** borrar el tag basura `1.0.0`, limpiar el README y adaptar el repo para
desplegarlo gratis y accesible en línea (Render).

**Qué se generó (agente):**
- `docker/entrypoint.sh`: ahora acepta **`DB_URL`** (`mysql://usuario:pass@host:puerto/nombre`) además
  de las variables sueltas (`DB_HOST`, `DB_PUERTO`, …), y **adapta Apache al `$PORT`** que inyectan
  Render/Railway (con fallback a 80 en local). El parseo se hace con PHP (`parse_url`) para no depender
  de herramientas externas; las contraseñas con caracteres especiales se decodifican con `urldecode`.
- `Dockerfile`: define `ENTRYPOINT` propio (antes lo pasaba el compose) y expone `$PORT`.
- `docker-compose.yml`: deja de montar/pasar el entrypoint (ahora vive en la imagen).
- `database/init-render.sh`: aplica `schema.sql` + `seed.sql` en proveedores gestionados, **omitiendo**
  `CREATE DATABASE`/`USE` (que solo aplican en local) y conectando por `DB_URL`. Pensado para ejecutarse
  una sola vez contra la base gestionada.
- `render.yaml`: Blueprint (web service Docker + base MySQL) para crear todo de una vez.
- `.env.example`: documenta la alternativa `DB_URL`.
- `README.md`: **reescrito limpio** (se eliminaron los escapados `\_` y los `file:///home/...`; ahora los
  enlaces son relativos) y se añadió la **sección 12 «Despliegue en línea (Render)»**.
- Tag `1.0.0` (sin `v`, duplicado y obsoleto) eliminado en local y en remoto.

**Verificación (todo ejecutado):**
- `docker compose up -d --build` → path de variables sueltas OK (ping, lista, SPA 200, 401 sin clave).
- Imagen standalone con `-e PORT=10000 -e DB_URL=mysql://...` → Apache reescribió a `Listen 10000`, la
  app respondió en `:10000` y el `config.php` generado salió correcto (usuario, host, base).
- `database/init-render.sh` probado contra un usuario **sin** `CREATE DATABASE`: cargó 10 usuarios,
  4 técnicos, 25 tickets y 4 categorías.
- `scripts/guards.sh` → TODO OK. Suite PHPUnit → 38 tests, 65 aserciones, 0 omitidos.

**Nota para el autor:** el despliegue free de Render **se suspende tras ~15 min** sin tráfico (despierta
en ~30 s). Render ofrece MySQL y PostgreSQL; el proyecto es MySQL 8.0, así que si la región no habilita
MySQL free, la alternativa indicada en el README es Railway con los mismos pasos (`DB_URL`).

## 2026-10-19 — Fase 12: Organización del repositorio

**Qué se pidió (usuario):** mantener los archivos extra (contexto de la prueba, prompts de IA,
config del agente, CSV de prueba) pero **organizarlos mejor en subcarpetas** en vez de dejarlos
sueltos en la raíz.

**Qué se generó (agente):**
- `CONTEXT/` → `docs/contexto/` (enunciado PDF/MD/TXT + README explicativo).
- `prompts/` → `docs/prompts-ia/` (+ README).
- `opencode.json` → `.opencode/opencode.json` (ubicación que OpenCode descubre automáticamente;
  antes estaba en la raíz). Se añadió `docs/herramientas-ia/` con notas.
- `tickets_20261019_161023.csv` → `docs/evidencias/ejemplo-export.csv` (evidencia del punto extra CSV).
- `.gitignore`: ignora `tickets_*.csv` (los export de prueba no se versionan).
- `AGENTS.md` y `README.md`: actualizados con la nueva estructura.

**Decisión de diseño:** mover `opencode.json` a `.opencode/` (en vez de `docs/herramientas-ia/`) para
**no romper** el descubrimiento de configuración de OpenCode, que busca `opencode.json` en la raíz del
proyecto o en `.opencode/`. Los movimientos se hicieron con `git mv` para conservar el historial.

**Verificación:** `scripts/guards.sh` → TODO OK. Sin referencias rotas a las rutas antiguas.

## 2026-10-19 — Fase 13: Despliegue realista (Render no da MySQL nativo)

**Qué se pidió (usuario):** corregir el enfoque de despliegue: Render **no** ofrece MySQL
gestionado de forma nativa (solo PostgreSQL y Redis). Hay que dejar el repo honesto y
funcional, sin prometer algo que la plataforma no da.

**Verificación (fuentes oficiales):**
- Render FAQ (render.com/docs/faq): "Render offers managed Postgres databases... Key Value
  instances... You can also run your own custom database instance... such as MySQL"
  → MySQL **no es nativo**, solo contenedor propio con disco persistente.
- Railway (2026): sí ofrece MySQL con un clic (`mysql:8`) y prueba gratis de $5 por 30 días
  sin tarjeta; luego plan Free con $1/mes de crédito.

**Qué se cambió (agente):**
- `render.yaml`: se quitó el bloque `databases:` (MySQL gestionado) que **haría fallar** el
  blueprint. Ahora define solo el `web service`; `DB_URL` pasa a `sync: false` (valor manual
  en el panel, apuntando a un MySQL externo). Nota explicativa en la cabecera.
- `README.md` §12: reescrito con tres rutas reales — (1) Railway app+MySQL (recomendada),
  (2) Render app + MySQL externo gratis, (3) solo local para el video. Se aclara que el
  despliegue en línea es **punto extra opcional** y que la nota principal se juega en local.
- Estructura del README: descripción de `render.yaml` actualizada.

**Verificación:** `render.yaml` parsea como YAML válido (solo clave `services`).
`scripts/guards.sh` → TODO OK.

## 2026-10-19 — Fase 14: Despliegue "todo en uno" para Render Free (sin MySQL externo)

**Qué se pidió (usuario):** no usar Railway (lo considera de pago) y desplegar en Render **Free**
la app **con la base de datos dentro del mismo contenedor** (Opción B), para que funcione sin
contratar un MySQL externo.

**Contexto/limitación verificada:** el plan Free de Render **no incluye discos persistentes** y el
servicio se suspende tras ~15 min sin tráfico; al recrearse el contenedor, la BD vuelve a cero.
Solución: empaquetar PHP + Apache + MariaDB en una sola imagen y **re-aplicar el seed en cada
arranque** (el `seed.sql` es re-ejecutable por diseño).

**Qué se generó (agente):**
- `Dockerfile.allinone`: imagen única con **MariaDB Server** + PHP 8.1 + Apache. Inicializa el
  datadir en build, instala `pdo_mysql`/`zip`, ajusta docroot a `public/` y usa
  `docker/entrypoint-allinone.sh` como `ENTRYPOINT`.
- `docker/entrypoint-allinone.sh`: arranca MariaDB, espera el socket, aplica `schema.sql` + `seed.sql`,
  **genera `config/config.php` con las claves correctas en español** (`puerto`/`nombre`/`usuario`/
  `contrasena`) y adapta Apache al `$PORT`.
- `README.md` §12: reordenado — **Opción 1 = Render todo en uno** (recomendada para quien no usa
  Railway), con la advertencia de la poca persistencia del plan Free; las demás opciones quedan
  como alternativas. Estructura del proyecto actualizada.
- NO se tocaron `Dockerfile` ni `docker/entrypoint.sh` (siguen sirviendo para docker-compose y para
  Render con BD externa): se añadió una variante en paralelo.

**Por qué NO se siguió literalmente el script propuesto por el usuario:** el `config.php` de ese
script usaba claves en inglés (`port`, `database`, `username`, `password`), pero la app lee
`puerto`, `nombre`, `usuario`, `contrasena` (ver `src/Support/Database.php:30-38`), por lo que
habría fallado al conectar. También faltaba `libzip-dev`/`unzip` para Composer.

**Verificación:** `sh -n` del entrypoint OK; imagen construida y probada (ver commit).

**Incidente durante la prueba de la imagen (resuelto):**
La primera versión del entrypoint generaba `config.php` con `usuario = root` y sin contraseña.
La app (que conecta por TCP a `127.0.0.1:3306`) devolvía `500` con
`SQLSTATE[HY000] [1698] Access denied for user 'root'@'localhost'`. Causa: en Debian/Ubuntu el
usuario `root` de MariaDB usa **autenticación unix_socket** y **rechaza** las conexiones TCP.

**Corrección:** el entrypoint ahora crea un **usuario dedicado** `soporte`@`%` con contraseña
(`CREATE USER IF NOT EXISTS ... IDENTIFIED BY ...` + `GRANT` sobre `soporte_ti`), y genera
`config.php` apuntando a él. El CLI sigue usando `mysql -u root` (vía socket), que sí funciona.
Verificado: `/api/ping`, `/api/tickets`, `/api/resumen` responden `200` y el 401 sin API key se
comporta como corresponde.

**Verificación final (imagen `Dockerfile.allinone`, simulando Render Free con `PORT` + `API_KEY`):**
- Arranque limpio: MariaDB + schema/seed + Apache en el `$PORT`.
- `GET /api/ping` y `GET /api/tickets` sin key → **401** (fail-closed, §4.3).
- `GET /api/tickets` con key → **200**, `total: 25` (seed aplicado).
- `GET /api/resumen` → datos reales del tablero (6 abiertos, 6 en curso, 13 cerrados, 4 vencidos).
- `POST /api/tickets` → **201**, `TK-2026-000026` (consecutivo correcto) y `total: 26`.
- `PATCH .../estado` ABIERTO→CERRADO → **409 TRANSICION_INVALIDA**; ABIERTO→EN CURSO → **200**.
- `GET /` (SPA) → **200**.
- Tras `docker restart`, la BD vuelve a 25 tickets (comportamiento documentado del plan Free sin disco).

## 2026-10-19 — Hotfix: `/api/ping` público (health check de Render)

**Síntoma en el despliegue:** el servicio de Render quedaba en bucle "In progress" porque su
health check (configurado en `/api/ping`) recibía **401** y nunca un 200.

**Causa:** `ApiKeyMiddleware` protege **todas** las rutas `/api/*`, incluida `/api/ping`. Pero el
contrato ya declaraba que ping **no exige clave** (`docs/04-api.md:257` y `docs/openapi.yaml`
con `security: []`). Es decir: **la implementación contradecía su propia documentación**.

**Corrección:** se añadió una lista blanca de rutas públicas (`RUTAS_PUBLICAS = ['/api/ping']`)
en `ApiKeyMiddleware`; esa ruta responde 200 sin clave y no expone datos de negocio. El resto de
endpoints sigue exigiendo `X-API-Key` (401 si falta o es inválida).

**Verificación:** `scripts/guards.sh` → TODO OK; PHPUnit → 38 tests OK; en la imagen todo-en-uno,
`/api/ping` sin clave → 200 y `/api/tickets` sin clave → 401.
