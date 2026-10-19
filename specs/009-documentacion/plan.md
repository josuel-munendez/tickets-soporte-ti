# Plan — Fase 9: Documentación Doc-as-Code

> **Spec:** `specs/009-documentacion/spec.md`

## Archivos

```text
docs/00-vision-y-alcance.md          # Problema, visión, alcance, actores, principios
docs/01-requisitos.md                # RF, RNF, RI con trazabilidad
docs/02-arquitectura.md              # C4 + flujo de petición (Mermaid)
docs/03-modelo-datos.md              # ER + diccionario de datos (coincide con schema.sql)
docs/04-api.md                       # Contrato REST en Markdown + ejemplos curl
docs/openapi.yaml                    # Contrato formal (contract-first)
docs/05-reglas-negocio.md            # RN-01..RN-09 + diagrama de estados
docs/06-decisiones/ADR-001..005.md   # Decisiones de arquitectura
docs/07-pruebas.md                   # Matriz casos ↔ requisitos + checklist DoD
docs/diagrams/*.puml, *.mmd          # Fuentes de los diagramas
```

## Decisiones

- **D1 — La documentación se deriva del código.** Se leyeron `schema.sql`, las cabeceras de los
  Controllers/Services, el `Response` y las respuestas reales de la API para no inventar campos.
  Si algo difería, se corrige el código/contrato en el mismo commit (§5).
- **D2 — Diagramas como texto.** Mermaid incrustado en los `.md` (renderiza en GitHub) y fuentes
  `.puml`/`.mmd` en `diagrams/` para quien prefiera PlantUML. Ambos se validan.
- **D3 — ADR con alternativas.** Cada ADR registra el contexto, la decisión y **las alternativas
  descartadas con su razón**, porque la sustentación exige defender el «por qué».
- **D4 — Trazabilidad explícita.** Cada requisito apunta a una regla (`RN-xx`), a un endpoint y/o a
  un test, de modo que `07-pruebas.md` sea la matriz verificable.

## Punto de defensa

La documentación no es decorativa: permite al autor explicar el consecutivo, las transiciones, el
SLA y el tope, y demuestra que el contrato (`openapi.yaml`) y el código evolucionan juntos.
