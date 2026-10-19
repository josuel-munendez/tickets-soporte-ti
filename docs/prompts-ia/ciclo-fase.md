Lee @AGENTS.md completo. Vamos a hacer la FASE {N}: {nombre de la fase}.

PASO 1 — Modo Plan, NO escribas código todavía:
Crea specs/{NNN-nombre}/spec.md (historias, criterios de aceptación Dado/Cuando/Entonces, casos de error),
specs/{NNN-nombre}/plan.md (capas, clases y métodos, endpoints, decisiones tomadas y alternativas descartadas)
y specs/{NNN-nombre}/tasks.md (checklist ordenada de tareas pequeñas).
Alcance exacto de esta fase: {alcance}.
Muéstrame un resumen de máximo 10 líneas y ESPERA mi "aprobado".

PASO 2 — Cuando yo apruebe, implementa SOLO lo que está en tasks.md y márcalo [x].
Aplica SOLID y código limpio de AGENTS.md §10 y documenta en el código lo que pide AGENTS.md §12
(qué es, qué hace, dónde está y por qué existe; referencia las reglas con RN-xx; sin comentar lo obvio).

PASO 3 — Actualiza la documentación afectada en docs/ (dime cuáles archivos y diagramas tocaste),
ejecuta `bash scripts/guards.sh` y corrige lo que falle.

PASO 4 — Dame: (a) 5 pruebas manuales con el resultado esperado, (b) una explicación en lenguaje simple
de las 2 decisiones más importantes (las voy a defender en el video), (c) una entrada para docs/ai-log.md.
NO hagas git commit; lo hago yo.