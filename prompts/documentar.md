Lee @AGENTS.md (sección 12) y documenta el proyecto como Doc-as-Code. NO documentes cada línea de código:
documenta QUÉ ES, QUÉ HACE, DÓNDE ESTÁ y POR QUÉ SE CREÓ cada pieza relevante (capas, módulos, clases, métodos
importantes, atributos, entidades, constantes, promesas/fetch del frontend, reglas de negocio, APIs y decisiones).

Genera o actualiza, según el estado real del código:
1. docs/00-vision-y-alcance.md: problema, usuarios, MVP, MoSCoW, supuestos y riesgos.
2. docs/01-requisitos.md: requisitos funcionales (RF-xx), no funcionales (RNF-xx: seguridad, concurrencia, mantenibilidad,
   rendimiento, usabilidad) y de información (RI-xx: entidades, atributos, reglas de integridad), con una matriz de trazabilidad
   requisito → regla → archivo/clase → prueba.
3. docs/02-arquitectura.md: capas y responsabilidades, flujo de una petición, y el modelo C4 en PlantUML (nivel 1 contexto,
   nivel 2 contenedores, nivel 3 componentes de la API) usando !include <C4/C4_Container> de la librería estándar de PlantUML.
4. docs/03-modelo-datos.md: ER en Mermaid, diccionario de datos con el POR QUÉ de cada tabla, restricción e índice.
5. docs/04-api.md y docs/openapi.yaml (OpenAPI 3): propósito, parámetros, respuestas, errores y ejemplo de cada endpoint.
6. docs/05-reglas-negocio.md: cada regla RN-xx con descripción, dónde se implementa y por qué existe; diagrama de estados
   (Mermaid stateDiagram-v2) y diagrama de secuencia UML del consecutivo seguro.
7. docs/06-decisiones/ADR-NNN-*.md: una decisión por archivo (contexto, decisión, alternativas, consecuencias).
   Mínimo: capas sin framework, consecutivo con tabla y FOR UPDATE, orden de bloqueos, API key por middleware, transacciones.
8. docs/07-pruebas.md: plan de pruebas y matriz caso ↔ requisito.
9. Diagramas en docs/diagrams/ como archivos fuente (.puml y .mmd) y también incrustados en los .md con Mermaid donde aplique.
   Usa: arquitectura (C4), flujo (diagrama de flujo del alta de ticket y del SLA), UML (clases de dominio/servicios y secuencia).
10. Docblocks breves en el código: responsabilidad de cada clase y el POR QUÉ cuando no sea obvio; @see RN-xx en reglas de negocio.

Reglas: español claro, párrafos cortos, tablas donde ayuden, nada inventado (si algo aún no existe, márcalo "pendiente").
Al final lista qué archivos creaste o cambiaste y qué quedó pendiente.