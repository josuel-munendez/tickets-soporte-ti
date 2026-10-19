# ADR-001 — PHP nativo en capas, sin framework de backend

- **Estado:** Aceptada
- **Fecha:** 2026-10-18
- **Contexto de la prueba:** AGENTS.md §2 prohíbe frameworks de backend.

## Contexto

El MVP debe construirse en PHP 8.1+ **sin framework** (nada de Laravel, Symfony…). Aun así,
necesitamos una estructura que resista el crecimiento, sea testeable y demuestre dominio de
arquitectura (es evaluación técnica).

## Decisión

Adoptar **arquitectura en capas** con separación estricta:

- `Controllers/` — HTTP delgado (lee `Request`, llama `Service`, responde `Response`).
- `Services/` — reglas de negocio y transacciones (sin SQL, sin `$_GET`/`$_POST`).
- `Repositories/` — **único** lugar con SQL/PDO.
- `Domain/` — clases puras (`SlaCalculator`, `EstadoTicket`, `Constantes`, `CsvExporter`).
- `Http/` — `Router`, `Request`, `Response`, middlewares.

La inyección de dependencias es **manual**, en `public/index.php`. Composer se usa solo para
autoload PSR-4 (`App\ => src/`), PHPUnit y herramientas de desarrollo.

## Alternativas consideradas

| Alternativa | Por qué se descartó |
| --- | --- |
| Micro-framework (Slim) | La prueba lo prohíbe; además no aportaría valor frente a un Router propio de ~100 líneas. |
| Un único archivo `index.php` con todo | Rápido al inicio, insostenible y difícil de testear; iría contra el criterio de evaluación. |
| Contenedor de DI completo | Sobrediseño para el tamaño del MVP. |

## Consecuencias

- **Positivas:** lógica de dominio testeable sin BD; los guards pueden verificar capas
  (SQL confinado a `Repositories`); el autor puede explicar cada pieza.
- **Negativas:** hay que escribir el routedo y el autoload a mano; nada que no se resuelva con
  `public/index.php` y `composer.json`.
