# Tasks — Fase 4: Creación de tickets (POST /api/tickets)

- [x] T1. `TicketRepository`: `siguienteConsecutivo`, `insertar`, `insertarHistorialCreacion`.
- [x] T2. `TecnicoRepository`: `bloquearPorId` (FOR UPDATE) y `contarActivos`.
- [x] T3. `CategoriaRepository`: `existe`.
- [x] T4. `TicketService::crear` + `validarCreacion` (junta errores → 422) y transacción con orden de bloqueos.
- [x] T5. `TicketController::crear` (201) y registro de `POST /api/tickets` en `index.php` (inyectar Database).
- [x] T6. Pruebas manuales: POST válido (201, número 26, SLA, historial), 422s (descripción, prioridad, usuario inactivo, categoría, técnico), 409 tope, 401.
- [x] T7. Prueba de concurrencia: N POST en paralelo ⇒ números únicos.
- [x] T8. Guards en verde + `docs/ai-log.md`.
