# ADR-004 — SPA en JavaScript vanilla, sin framework ni CDN

- **Estado:** Aceptada
- **Fecha:** 2026-10-19
- **Requisito:** AGENTS.md §2 y §7.

## Contexto

La interfaz es una SPA que consume la API por `fetch`. La prueba **prohíbe** React/Vue/Angular y
aconseja no depender de CDNs (reproducibilidad y seguridad en un entorno interno).

## Decisión

Construir la SPA con **JavaScript ES6 vanilla** y CSS propio, dividida en módulos con
responsabilidad única:

- `utils.js` — utilidades (`escapeHtml`, helpers del DOM con `createElement`/`textContent`).
- `api.js` — **único** punto de `fetch` (añade `X-API-Key`, normaliza errores).
- `ui.js` — render (tablero, avisos, modal de detalle).
- `tabla.js` — tabla, filtros, paginación y export CSV.
- `form.js` — validación y creación de tickets (debounce de 400 ms al buscar usuario).

Prevención de XSS: **nunca** `innerHTML` con datos externos (lo verifica `guards.sh`); todo el
DOM se construye con `textContent`.

## Alternativas consideradas

| Alternativa | Por qué se descartó |
| --- | --- |
| React/Vue/Angular | Prohibido por la prueba. |
| Bootstrap/jQuery por CDN | Dependencia externa en tiempo de ejecución; rompería sin red y añade riesgo. |
| Un único archivo JS | Difícil de mantener y de defender pieza por pieza en la sustentación. |

## Consecuencias

- **Positivas:** cero dependencias en el navegador; carga rápida; el guard puede prohibir
  `innerHTML` de forma mecánica.
- **Negativas:** hay que escribir a mano el manejo de DOM y estado (asumible para el alcance del MVP).
