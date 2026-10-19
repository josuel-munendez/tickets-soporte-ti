# Spec — Fase 9: Documentación Doc-as-Code

> **Estado:** En implementación
> **Reglas:** AGENTS.md §11 (Doc-as-Code), §9 (SDD + ADLC).

## Alcance

Completar la documentación viva del proyecto (`docs/`), en Markdown y con diagramas de texto,
coherente con el código real y con [`AGENTS.md`](../AGENTS.md):

- `00-vision-y-alcance.md` — problema, visión, alcance del MVP (Must/Should/Won't).
- `01-requisitos.md` — RF, RNF y RI con trazabilidad a reglas y pruebas.
- `02-arquitectura.md` — C4 (contexto, contenedores, componentes) + flujo de petición.
- `03-modelo-datos.md` — diagrama ER y diccionario de datos.
- `04-api.md` + `openapi.yaml` — contrato REST (contract-first).
- `05-reglas-negocio.md` — RN-01…RN-09 con diagramas de estado.
- `06-decisiones/ADR-001…005` — decisiones de arquitectura (contexto, decisión, alternativas).
- `07-pruebas.md` — matriz de casos ↔ requisitos y checklist de la Definición de Terminado.
- `diagrams/` — fuentes `.puml` y `.mmd`.

## Criterios de aceptación

- **CA-1** Cada requisito (RF/RNF/RI) traza a su regla y/o prueba.
- **CA-2** El `openapi.yaml` es YAML válido y describe los 10 endpoints reales (9 base + `ping` + export).
- **CA-3** Los diagramas `.puml` compilan con PlantUML; los `.mmd` son Mermaid válido.
- **CA-4** El modelo de datos coincide **exactamente** con `database/schema.sql`.
- **CA-5** El contrato de `04-api.md` coincide con las respuestas reales de la API.
- **CA-6** Los ADR registran alternativas descartadas (no solo la decisión).
- **CA-7** El `README.md` enlaza a `docs/`.
