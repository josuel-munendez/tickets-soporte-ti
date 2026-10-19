# Plan — Fase 8: Extras

> **Spec:** `specs/008-extras/spec.md`

## Archivos
```text
phpunit.xml                          # suites Unit (dominio puro) e Integration (BD real)
tests/Unit/{EstadoTicket,SlaCalculator,TopeTecnico,CsvExporter}Test.php
tests/Integration/{IntegrationTestCase,FlujoTicketTest}.php
src/Domain/CsvExporter.php           # CSV puro: RFC 4180 + neutralización de fórmulas
src/Http/Response.php                # nueva fábrica descarga() (cuerpo no-JSON con MIME)
src/Repositories/TicketRepository.php# listarParaExportar() (mismos filtros, sin paginar)
src/Services/TicketService.php       # exportarCsv() + criteriosFiltros() reutilizable
src/Controllers/TicketController.php # exportarCsv()
public/index.php                     # ruta GET /api/tickets/export.csv
public/assets/js/{api,tabla}.js      # Api.exportarCsv() + botón «Exportar CSV»
Dockerfile, docker-compose.yml, .env.example, .dockerignore   # (parte 3, al final)
```

## Decisiones y alternativas descartadas
- **D1 — Tests de dominio puros, integración aparte.** El SLA y la máquina de estados viven en `Domain`
  y reciben el «ahora» por parámetro, así que se prueban sin BD ni reloj. La integración usa `config.php`
  real y se **omite** si no hay MySQL, para no romper la suite en cualquier entorno. *Descartado:* mocks de
  PDO (probarían el mock, no la regla) y datos de prueba en la BD de desarrollo (ensuciarían el seed).
- **D2 — El consecutivo no se quema al fallar el tope.** Se prueba de verdad: se llena el cupo, se intenta
  un ticket más, se captura el 409 y se verifica que `consecutivos_ticket.ultimo` no cambió.
- **D3 — `Response::descarga()`.** El contrato es JSON (§5), pero una descarga necesita otro MIME; se añade
  una fábrica explícita en vez de `echo` en el Controller (que rompería la capa: cero salida en Controllers).
- **D4 — CSV con `;` y `\r\n`.** Separador regional y terminador de Excel; celdas entrecomilladas (RFC 4180)
  y fórmulas neutralizadas con apóstrofo (defensa frente a inyección CSV al abrir en Excel/Sheets).
- **D5 — Sobrescritura de archivo en la SPA con `<a download>` temporal.** Un `<a href>` directo no envía
  `X-API-Key`, por eso la descarga se obtiene con `fetch` y se materializa como blob.

## Punto de defensa
PHPUnit fija las reglas que se piden defender en la sustentación (consecutivo, transiciones, SLA, tope) y
la exportación CSV reutiliza exactamente los mismos filtros de la lista, garantizando que «lo que ves es lo
que exportas».
