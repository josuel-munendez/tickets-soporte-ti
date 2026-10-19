# Plan — Fase 3: Endpoints de lectura (GET) y tablero

> **Spec:** `specs/003-endpoints-get/spec.md`

## Capas y clases nuevas
```text
src/Domain/Constantes.php          # MAX_TICKETS_TECNICO=5, ESTADOS, PRIORIDADES (datos, no ifs)
src/Domain/SlaCalculator.php       # horasDePrioridad, calcularVence, indicador (puro; recibe now)
src/Repositories/UsuarioRepository.php     # buscarPorCodigo
src/Repositories/TecnicoRepository.php     # listarActivosConCarga
src/Repositories/CategoriaRepository.php   # listarTodas
src/Repositories/TicketRepository.php      # listar(filtros,pagina,porPagina), buscarPorNumero,
                                           # historialDe, conteosPorEstado, vencidosActivos
src/Services/TicketService.php     # listar, detalle, resumen (valida filtros → 422; formatea fechas)
src/Services/UsuarioService.php    # buscarParaRegistro (404 si inexistente o inactivo)
src/Services/TecnicoService.php    # listarConCarga
src/Services/CategoriaService.php  # listar
src/Controllers/TicketController.php     # listar, detalle, resumen
src/Controllers/UsuarioController.php    # buscar
src/Controllers/TecnicoController.php    # listar
src/Controllers/CategoriaController.php  # listar
```
`public/index.php` se ajusta: inyección manual PDO → Repos → Services → Controllers (§10 D)
y registro de las 6 rutas GET (`/api/resumen` en la raíz, según el contrato §5).

## Decisiones y alternativas descartadas
- **D1 — Orden fijo `creado_en DESC, id DESC` sin ORDER BY dinámico.** Evita arriesgar la lista
  blanca de §4.1: no hay parámetro de orden hoy y el contrato no lo pide. *Descartado:* aceptar
  `?sort=` (complejidad innecesaria para el MVP).
- **D2 — Búsqueda `q` escapando `%`, `_` y `\` con `addcslashes`.** MySQL ya usa `\` como escape
  por defecto del LIKE ⇒ sin cláusula ESCAPE extra. *Descartado:* FTS/RPATern (sobreingeniería).
- **D3 — `WHERE` armado en arreglo de strings estáticos + parámetros enlazados.** Ninguna variable
  se concatena al SQL (§4.1); solo cambia la lista de condiciones, no su texto.
- **D4 — Usuario inactivo responde 404** ("no encontrado o inactivo") en vez de 200 con bandera:
  el contrato solo prevé 200/404 y la SPA deshabilita el botón ante 404. RN-02 se reitera en el POST.
- **D5 — LIMIT/OFFSET con `bindValue(..., PDO::PARAM_INT)`:** con prepares nativos (sin emulación)
  MySQL exige enteros reales en LIMIT; pasarlos como string rompería (§4.2).
- **D6 — Fechas ISO-8601 `-05:00` formateadas en el Service** con `DateTimeImmutable::format('c')`
  (el default de PHP ya es America/Bogota y la BD también) ⇒ sin cálculos manuales de offset.

## Punto de extensión futuro
`SlaCalculator` no conoce PDO ni HTTP: los tests de Fase 8 (PHPUnit) lo prueban inyectando
`now` artificial sin congelar el reloj.
