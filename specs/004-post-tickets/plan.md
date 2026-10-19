# Plan — Fase 4: Creación de tickets (POST /api/tickets)

> **Spec:** `specs/004-post-tickets/spec.md`

## Cambios por capa
- **Domain:** sin cambios de lógica; se reutiliza `SlaCalculator::calcularVence` y `Constantes`.
- **Repositories (SQL):**
  - `TicketRepository`: `siguienteConsecutivo(anio)` (INSERT IGNORE + SELECT…FOR UPDATE + UPDATE),
    `insertar(datos): id`, `insertarHistorialCreacion(...)`.
  - `TecnicoRepository`: `bloquearPorId(id)` (`SELECT … FOR UPDATE`) y `contarActivos(id)`.
  - `CategoriaRepository`: `existe(id)`.
  - `UsuarioRepository`: reutiliza `buscarPorCodigo` (ya valida `activo`).
- **Service (`TicketService::crear`):** valida el body (junta TODOS los errores → 422), verifica
  usuario y categoría (falla rápido, antes de tomar bloqueos), y orquesta la transacción vía
  `Support\Database::transaction(callable)`.
- **Controller (`TicketController::crear`):** `Response::json($service->crear($request->json()), 201)`.
- **`public/index.php`:** se inyecta `Database` en `TicketService` y se registra `POST /api/tickets`.

## Decisiones y alternativas descartadas
- **D1 — Bloqueo del técnico, no del consecutivo, primero.** §6.1 fija el orden
  `ticket → técnico → consecutivo` para evitar deadlocks; al crear no existe ticket, así que el orden
  real es técnico → consecutivo. *Descartado:* bloquear el consecutivo primero (podría interbloquear
  contra una reasignación que ya tiene el técnico bloqueado).
- **D2 — Validaciones de usuario/categoría ANTES de la transacción.** Son lecturas sin estado que hay
  que bloquear; hacerlas fuera reduce el tiempo con locks tomados. El tope del técnico SÍ va dentro
  porque depende del bloqueo. *Descartado:* meter todo dentro (más tiempo de bloqueo, sin beneficio).
- **D3 — `estado` siempre `ABIERTO` en el INSERT** (constante en el repositorio): un ticket creado no
  puede nacer en otro estado (RN-06). *Descartado:* aceptar `estado` del body.
- **D4 — `insertarHistorialCreacion` en el mismo `transaction()`.** Garantiza RN-09: nunca hay ticket
  sin su fila de historial (si el historial falla, se revierte el ticket).
- **D5 — 409 con `TECNICO_AL_TOPE` reutilizando `ConflictoException`** (código parametrizable creado
  en Fase 1). *Descartado:* una excepción nueva por cada regla de conflicto.

## Punto clave para la sustentación
El consecutivo no se "adivina" con `MAX(numero)+1` (propenso a duplicados concurrentes): se serializa
con un `SELECT … FOR UPDATE` sobre la fila del año, y `tickets.numero UNIQUE` actúa como segunda barrera.
