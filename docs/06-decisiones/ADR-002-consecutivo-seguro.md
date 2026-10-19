# ADR-002 — Consecutivo anual seguro (transacción + `FOR UPDATE`)

- **Estado:** Aceptada
- **Fecha:** 2026-10-18
- **Regla:** RN-01 · **Requisito:** 20 POST concurrentes → 20 números únicos.

## Contexto

El número de ticket `TK-AAAA-NNNNNN` debe ser único y **secuencial por año**. Varios agentes pueden
crear tickets al mismo tiempo; el reto clásico es el *race condition* del contador.

## Decisión

Generar el consecutivo **dentro de una transacción atómica** con el siguiente orden de bloqueos
(para evitar deadlocks): **ticket → técnico → consecutivo**.

```sql
BEGIN;
SELECT ... FROM tecnicos WHERE id = ? FOR UPDATE;      -- RN-05
INSERT IGNORE INTO consecutivos_ticket (anio) VALUES (?);
SELECT ultimo FROM consecutivos_ticket WHERE anio = ? FOR UPDATE;
UPDATE consecutivos_ticket SET ultimo = ultimo + 1 WHERE anio = ?;
INSERT INTO tickets (...) VALUES (...);
INSERT INTO ticket_historial (...) VALUES (...);
COMMIT;
```

Barreras de seguridad:

1. El `SELECT ... FOR UPDATE` serializa a los creadores concurrentes.
2. `tickets.numero` es `UNIQUE`: **segunda barrera** si el contador se desincronizara.
3. Si falla el tope del técnico, `ROLLBACK` **no quema** el consecutivo.

## Alternativas consideradas

| Alternativa | Por qué se descartó |
| --- | --- |
| `AUTO_INCREMENT` como número visible | No permite el formato `TK-AAAA-NNNNNN` ni reinicio anual legible. |
| `MAX(numero) + 1` sin bloqueo | Race condition: dos requests leen el mismo máximo. |
| `INSERT` optimista + reintento | Complejo y con más latencia bajo concurrencia alta; el `FOR UPDATE` es directo y correcto. |
| UUID | Cumple unicidad pero no el formato exigido ni la legibilidad. |

## Consecuencias

- **Positivas:** unicidad garantizada; consecutivo nunca duplicado ni quemado; el orden de bloqueos
  reduce el riesgo de deadlock.
- **Negativas:** la creación queda serializada por el bloqueo del contador (aceptable: la creación
  es una operación corta y poco frecuente).
