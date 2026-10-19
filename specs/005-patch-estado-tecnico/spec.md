# Spec — Fase 5: Cambio de estado y reasignación (PATCH)

> **Estado:** En implementación
> **Reglas:** AGENTS.md §5 (contrato), §6.5 (tope), §6.6 (máquina de estados), §6.7 (no reasignar
> cerrado), §6.9 (historial), §4 (seguridad).

## Endpoints
- `PATCH /api/tickets/{numero}/estado` — body `{"estado": "...", "observacion": "..."}`.
- `PATCH /api/tickets/{numero}/tecnico` — body `{"tecnico_id": N, "observacion": "..."}`.
Ambos responden `200` con el ticket actualizado (incluye historial). Errores: `401`, `404`, `409`, `422`.

## Criterios de aceptación (Dado/Cuando/Entonces)
- **CA-1 (RN-06)** Dado un ticket ABIERTO, cuando se pide `CERRADO`, entonces `409 TRANSICION_INVALIDA`
  (prohibido saltar). Dado EN CURSO, cuando se pide `ABIERTO`, entonces `409` (prohibido retroceder).
- **CA-2 (RN-06)** Dado un estado desconocido (`PENDIENTE`), entonces `422 VALIDACION` con `fields.estado`.
- **CA-3 (RN-06)** Dado EN CURSO→CERRADO sin `observacion`, entonces `422 VALIDACION` con
  `fields.observacion`; con `observacion`, entonces `200`, guarda `solucion`, `cerrado_en` y `sla = null`.
- **CA-4 (RN-06)** Dado cualquier cambio válido, entonces se lee el ticket con `FOR UPDATE` y se inserta
  el historial `ESTADO` en la misma transacción (RN-09).
- **CA-5 (RN-07)** Dado un ticket CERRADO, cuando se reasigna, entonces `409` (no se reabre ni reasigna).
- **CA-6 (RN-05)** Dado un técnico con 5 activos, cuando se le asigna otro ticket, entonces
  `409 TECNICO_AL_TOPE`; dado un técnico inactivo/inexistente, entonces `422 fields.tecnico_id`.
- **CA-7 (RN-05)** Dado que se reasigna al MISMO técnico, entonces `200` y NO se cuenta ni registra
  historial (no-op idempotente).
- **CA-8 (RN-09)** Dada una reasignación efectiva, entonces se inserta historial `REASIGNACION` con
  `tecnico_anterior`/`tecnico_nuevo` (el `estado_nuevo` repite el estado vigente, el esquema lo exige NOT NULL).
- **CA-9** Dado un número inexistente, entonces `404 NO_ENCONTRADO` en ambos endpoints.

## Concurrencia (§6.1)
Orden de bloqueos: **ticket → técnico**. El ticket se lee/bloquea primero; en reasignación se bloquea
luego la fila del técnico destino antes de contar su carga. La transacción cubre también el historial.
