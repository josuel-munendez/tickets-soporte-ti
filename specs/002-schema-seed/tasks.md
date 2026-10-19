# Tasks — Fase 2: Schema y Seed contra MySQL real

- [x] T1. Localizar el servidor MySQL (contenedor Docker `tickets-db`, puerto 3306).
- [x] T2. Cargar `database/schema.sql` (6 tablas, InnoDB/utf8mb4, índices y CHECK).
- [x] T3. Cargar `database/seed.sql` y verificar conteos (10/4/4/25, consecutivo 25).
- [x] T4. Verificar tope de carga activa por técnico (5/4/3/0) y vencidos presentes.
- [x] T5. Re-ejecutar el seed y confirmar idempotencia.
- [x] T6. Probar CHECKs negativos (CERRADO sin solución, descripción corta) ⇒ rechazados.
- [x] T7. Probar `Support\Database` contra la BD real (conexión, time_zone, COMMIT, ROLLBACK).
- [x] T8. Actualizar `docs/ai-log.md` y ejecutar guards.
