# Tasks — Fase 3: Endpoints de lectura (GET) y tablero

- [x] T1. `Domain/Constantes.php` y `Domain/SlaCalculator.php` (RN-08: horas, vence, indicador).
- [x] T2. `Repositories/UsuarioRepository`, `TecnicoRepository`, `CategoriaRepository` (PDO inyectado).
- [x] T3. `Repositories/TicketRepository` (listado con filtros + paginación, detalle, historial, conteos).
- [x] T4. `Services/TicketService` (validación de filtros → 422, formato ISO, indicador SLA).
- [x] T5. `Services/UsuarioService` (404 si inexistente/inactivo), `TecnicoService`, `CategoriaService`.
- [x] T6. `Controllers/TicketController`, `UsuarioController`, `TecnicoController`, `CategoriaController`.
- [x] T7. Cablear todo en `public/index.php` (inyección manual + registro de rutas GET).
- [x] T8. Pruebas manuales con curl (lista, filtros, paginación, 422s, detalle, 404, usuarios, técnicos, categorías, resumen, 401).
- [x] T9. Guards en verde + actualizar `docs/ai-log.md`.
