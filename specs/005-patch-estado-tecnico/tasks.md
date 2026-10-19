# Tasks — Fase 5: Cambio de estado y reasignación (PATCH)

- [x] T1. `Domain/EstadoTicket.php` (transiciones como datos + helpers).
- [x] T2. `TicketRepository`: `bloquearPorNumero`, `actualizarEstado`, `actualizarTecnico` e historial genérico.
- [x] T3. `TicketService::cambiarEstado` (422 estado desconocido, 409 transición, 422 cierre sin observación).
- [x] T4. `TicketService::reasignarTecnico` (409 cerrado, 422 técnico, 409 tope, no-op mismo técnico).
- [x] T5. `TicketController` + registro de rutas PATCH en `index.php`.
- [x] T6. Pruebas PATCH: salto/retroceso (409), cierre con/sin solución, reasignación OK, cerrado (409), tope (409), no-op, 404, 401.
- [x] T7. Guards en verde + `docs/ai-log.md`.
