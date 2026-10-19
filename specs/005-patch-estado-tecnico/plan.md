# Plan — Fase 5: Cambio de estado y reasignación (PATCH)

> **Spec:** `specs/005-patch-estado-tecnico/spec.md`

## Cambios por capa
- **Domain:** `EstadoTicket` (clase pura): mapa de transiciones como DATOS
  (`ABIERTO→EN CURSO→CERRADO`), `esValido`, `siguiente`, `permiteTransicion`. Sin cascadas de `if` (§10 O).
- **Repositories:**
  - `TicketRepository`: `bloquearPorNumero(numero)` (`SELECT ... FOR UPDATE`), `actualizarEstado(...)`,
    `actualizarTecnico(...)` e `insertarHistorial(evento)` **genérico** (reemplaza a
    `insertarHistorialCreacion`, ahora reutilizado por creación, estado y reasignación — DRY).
  - `TecnicoRepository`: reutiliza `bloquearPorId` y `contarActivos` (creados en Fase 4).
- **Service (`TicketService`):** `cambiarEstado` y `reasignarTecnico`, cada uno validando el body y
  orquestando la transacción; devuelven `detalle($numero)`.
- **Controller (`TicketController`):** `cambiarEstado` y `reasignarTecnico` (PATCH → `$request->json()`).
- **`public/index.php`:** se registran las dos rutas PATCH.

## Decisiones y alternativas descartadas
- **D1 — Máquina de estados como DATOS** (`EstadoTicket::TRANSICIONES`). Cumple Open/Closed: añadir una
  transición es editar un mapa, no buscar `if` dispersos. *Descartado:* `switch` de transiciones.
- **D2 — Estado desconocido = 422, transición ilegal = 409.** Distingue "no existe tal estado" (validación de
  entrada) de "existe pero el flujo no lo permite" (conflicto con el estado actual). *Descartado:* 422 para todo.
- **D3 — Reasignar un CERRADO responde `409 TRANSICION_INVALIDA`.** RN-07 fija el 409 pero no un código nuevo;
  el contrato §5 solo define `TRANSICION_INVALIDA` y `TECNICO_AL_TOPE` para 409, así que se reutiliza el
  primero (el mensaje aclara que es por cierre). *Descartado:* inventar un código `TICKET_CERRADO` fuera del contrato.
- **D4 — Reasignar al mismo técnico = no-op 200.** RN-05 dice que no cuenta; tratarlo como idempotente evita
  ruido en el historial y errores espurios de tope. *Descartado:* registrar historial con anterior == nuevo.
- **D5 — `observacion` en cierre se guarda como `solucion`; en cambios/reasignaciones va al historial.**
  Refleja el esquema: `tickets.solucion` (TEXT) y `ticket_historial.observacion` (VARCHAR 1000).

## Nota de esquema
`ticket_historial.tipo_cambio` ∈ {CREACION, ESTADO, REASIGNACION} y `estado_nuevo` es NOT NULL: en una
reasignación se repite el estado vigente del ticket.
