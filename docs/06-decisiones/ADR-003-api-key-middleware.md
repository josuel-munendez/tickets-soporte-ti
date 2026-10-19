# ADR-003 — Autenticación por API key en un middleware (fail-closed)

- **Estado:** Aceptada
- **Fecha:** 2026-10-18
- **Requisito:** AGENTS.md §4.3 · Formato único de error (§5).

## Contexto

Toda la API (`/api/*`) debe exigir `X-API-Key`. La prueba prohíbe login/roles, así que la
autenticación es a nivel de integración (clave compartida), no de usuario.

## Decisión

Validar la clave en un **middleware** (`ApiKeyMiddleware`) aplicado a todas las rutas `/api/*`,
antes de llegar a los Controllers:

- Comparación con `hash_equals()` (tiempo constante, resistente a *timing attacks*).
- Falta o no coincide → **`401 NO_AUTORIZADO`** con el formato único de error.
- **Fail-closed:** si la clave configurada está vacía, **nadie** autentica (evita abrir la API por
  un error de configuración).

## Alternativas consideradas

| Alternativa | Por qué se descartó |
| --- | --- |
| Comparación con `==` / `strcmp` | Vulnerable a *timing*; `hash_equals` es la práctica correcta. |
| Validar en cada Controller | Duplicación; olvidarlo en un endpoint abriría un agujero. |
| JWT / sesiones | Fuera de alcance (sería login/roles, prohibido). |
| Clave vacía = "sin auth" | Convierte un error de config en un agujero de seguridad. |

## Consecuencias

- **Positivas:** una sola barrera para todos los endpoints; sin duplicación; comportamiento
  seguro por defecto.
- **Negativas:** la clave se comparte entre clientes (aceptable para una API interna sin roles).
