# Spec — Fase 4: Creación de tickets (POST /api/tickets)

> **Estado:** En implementación
> **Reglas:** AGENTS.md §6.1 (consecutivo), §6.2 (solicitante), §6.3 (descripción), §6.4 (categoría/prioridad),
> §6.5 (tope de carga), §6.8 (SLA), §6.9 (historial), §4 (seguridad).

## Endpoint
`POST /api/tickets` — crea un ticket ABIERTO con número `TK-AAAA-NNNNNN` y su historial inicial,
todo en UNA transacción. Respuesta `201` con el ticket creado (detalle). Errores: `401`, `422`, `409`.

## Petición
```json
{ "usuario_codigo": "U001", "categoria_id": 3, "descripcion": "texto de 20 a 1000", "prioridad": "Alta", "tecnico_id": 2 }
```

## Criterios de aceptación (Dado/Cuando/Entonces)
- **CA-1 (RN-01)** Dado que dos peticiones intentan crear ticket a la vez, cuando ambas confirman,
  entonces reciben números DISTINTOS y consecutivos; `tickets.numero` es UNIQUE como segunda barrera.
- **CA-2 (RN-02)** Dado `usuario_codigo` inexistente o inactivo (`U010`), entonces `422` con
  `fields.usuario_codigo`.
- **CA-3 (RN-03)** Dada una descripción fuera de [20, 1000] (tras `trim`, `mb_strlen`), entonces `422`
  con `fields.descripcion`.
- **CA-4 (RN-04)** Dada `categoria_id` inexistente o `prioridad` ∉ {Alta, Media, Baja}, entonces `422`.
- **CA-5 (RN-05)** Dado un técnico con 5 tickets activos, entonces `409 TECNICO_AL_TOPE`;
  dado un técnico inactivo o inexistente, entonces `422`.
- **CA-6 (RN-08)** Dado que se crea un ticket, cuando se guarda, entonces `vence_en = creado_en + horas`
  (Alta 4 / Media 24 / Baja 72) calculado con `SlaCalculator`.
- **CA-7 (RN-09)** Dado que se crea un ticket, entonces en la MISMA transacción se inserta la fila de
  historial `CREACION` (estado_nuevo ABIERTO, técnico nuevo).
- **CA-8** Dado un 422, entonces `fields` trae TODOS los campos inválidos juntos (no uno por respuesta).

## Concurrencia (RN-01 / §6.1)
Orden de bloqueos: **técnico → consecutivo** (el ticket aún no existe).
`BEGIN` → `SELECT ... FOR UPDATE` del técnico → validar tope → `INSERT IGNORE` del año en
`consecutivos_ticket` → `SELECT ... FOR UPDATE` → `UPDATE ultimo + 1` → `INSERT` ticket e historial →
`COMMIT`. Si falla el tope, `ROLLBACK` (no se quema el consecutivo).

## Errores
`401` sin API key · `422 VALIDACION` (fields) · `409 TECNICO_AL_TOPE` · `500` genérico sin trazas.
