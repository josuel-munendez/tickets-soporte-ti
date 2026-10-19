# ADR-005 — Reglas de negocio como datos, no como cascadas de `if`

- **Estado:** Aceptada
- **Fecha:** 2026-10-19
- **Requisito:** AGENTS.md §10 (principio **O** de SOLID: abierto/cerrado).

## Contexto

Tres reglas del dominio son, en esencia, **tablas de datos**:

1. Las **transiciones** válidas de la máquina de estados (RN-06).
2. Las **horas de SLA** por prioridad (RN-08).
3. Las **prioridades** y **estados** permitidos (RN-04/06) y el tope de carga (RN-05).

Implementarlas con `if`/`switch` anidados las vuelve difíciles de leer, testear y extender.

## Decisión

Modelarlas como **constantes** en el dominio:

- `EstadoTicket` — mapa de transiciones `ABIERTO → EN CURSO → CERRADO`.
- `SlaCalculator::HORAS_POR_PRIORIDAD = ['Alta' => 4, 'Media' => 24, 'Baja' => 72]`.
- `Constantes::PRIORIDADES`, `ESTADOS`, `ESTADOS_ACTIVOS`, `MAX_TICKETS_TECNICO = 5`.

Ventaja clave: agregar un estado o una prioridad es **cambiar un dato**, no reescribir lógica; y
las clases puras se prueban sin BD ni reloj (el `now` entra por parámetro).

## Alternativas consideradas

| Alternativa | Por qué se descartó |
| --- | --- |
| `switch`/`if` anidados | Duplicación y riesgo de olvidar un caso al extender. |
| Tablas de configuración en BD | Sobrediseño para reglas estables; añade latencia y complejidad de conexión. |
| Enums PHP nativos solos | No aportan el mapa de transiciones ni las horas; mezclan estructura con política. |

## Consecuencias

- **Positivas:** reglas legibles y centralizadas; cobertura de pruebas directa
  (`EstadoTicketTest`, `SlaCalculatorTest`, `TopeTecnicoTest`); el autor puede explicar y
  modificar en vivo el SLA, las transiciones y el tope.
- **Negativas:** los valores viven en código (requieren despliegue para cambiar); aceptable porque
  son reglas de negocio estables, no configuración operativa.
