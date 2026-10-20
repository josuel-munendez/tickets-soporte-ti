# Diagramas

Diagramas del proyecto en **Diagram-as-Code** (texto versionable) con sus **imágenes renderizadas**.

| Diagrama | Fuente | Imagen (PNG / SVG) | Usado en |
| --- | --- | --- | --- |
| Contexto (C4 Nivel 1) | [`contexto.puml`](contexto.puml) | [`img/contexto.png`](img/contexto.png) · [`img/contexto.svg`](img/contexto.svg) | [`../02-arquitectura.md`](../02-arquitectura.md) |
| Contenedores (C4 Nivel 2) | [`contenedores.mmd`](contenedores.mmd) | [`img/contenedores.png`](img/contenedores.png) · [`img/contenedores.svg`](img/contenedores.svg) | [`../02-arquitectura.md`](../02-arquitectura.md) |
| Componentes (C4 Nivel 3) | [`componentes.mmd`](componentes.mmd) | [`img/componentes.png`](img/componentes.png) · [`img/componentes.svg`](img/componentes.svg) | [`../02-arquitectura.md`](../02-arquitectura.md) |
| Secuencia de una petición | [`secuencia-peticion.mmd`](secuencia-peticion.mmd) | [`img/secuencia-peticion.png`](img/secuencia-peticion.png) · [`img/secuencia-peticion.svg`](img/secuencia-peticion.svg) | [`../02-arquitectura.md`](../02-arquitectura.md) |
| Máquina de estados del ticket | [`estados-ticket.puml`](estados-ticket.puml) · [`estados-ticket.mmd`](estados-ticket.mmd) | [`img/estados-ticket.png`](img/estados-ticket.png) · [`img/estados-ticket.svg`](img/estados-ticket.svg) | [`../05-reglas-negocio.md`](../05-reglas-negocio.md) |

> Las fuentes `.mmd` (Mermaid) se ven directamente en GitHub dentro de los `.md`; las imágenes
> `.png`/`.svg` garantizan que los diagramas se vean también **fuera de GitHub** (PDF, ZIP, editores
> sin soporte de Mermaid).

## Regenerar las imágenes

Requiere [PlantUML](https://plantuml.com) (con Java) y, para Mermaid,
`@mermaid-js/mermaid-cli` (Node). Ejecuta desde la raíz del proyecto:

```bash
bash scripts/render-diagramas.sh
```

El script:
1. Renderiza los `.puml` con `plantuml -tpng` y `-tsvg`.
2. Renderiza los `.mmd` con `mmdc` (Mermaid CLI) a PNG y SVG.
3. Deja el resultado en `docs/diagrams/img/`.

> Nota: los `.mmd` incluyen líneas de comentario con `#` (útiles para Mermaid Live). El CLI de
> Mermaid no las admite, así que el script las filtra antes de renderizar; las fuentes no se tocan.
