# Spec — Fase 2: Schema y Seed contra MySQL real

> **Estado:** Completada
> **Reglas:** AGENTS.md §8 (base de datos), §2 (zona horaria), §6.1 (consecutivo), §6.5 (tope de carga).

## Objetivo
Cargar `database/schema.sql` y `database/seed.sql` en el MySQL 8.0 del contenedor `tickets-db`
y verificar que son reproducibles, consistentes con las reglas de negocio y utilizables por la
capa `Support\Database` de la Fase 1.

## Criterios de aceptación (verificados)
- **CA-2.1** `schema.sql` crea las 6 tablas (usuarios, tecnicos, categorias, tickets, ticket_historial,
  consecutivos_ticket) en InnoDB/utf8mb4 con sus índices y CHECK. ✅
- **CA-2.2** `seed.sql` deja: 10 usuarios (1 inactivo), 4 técnicos (1 inactivo), 4 categorías,
  25 tickets (6 ABIERTO, 6 EN CURSO, 13 CERRADO) y consecutivo 2026 = 25. ✅
- **CA-2.3** Carga activa respeta el tope: técnico 1 = 5, técnico 2 = 4, técnico 3 = 3, técnico 4 = 0. ✅
- **CA-2.4** Hay tickets VENCIDOS (4) y POR VENCER garantizados por fechas relativas a `NOW()`. ✅
- **CA-2.5** El seed es re-ejecutable: segunda corrida ⇒ mismos conteos (25 tickets, 59 historial). ✅
- **CA-2.6** Los CHECK rechazan datos inválidos: ticket CERRADO sin solución y descripción < 20
  caracteres fallan; ningún registro de prueba persiste. ✅
- **CA-2.7** `Support\Database` conecta contra el MySQL real: `SET time_zone = '-05:00'` efectivo
  en sesión y `transaction()` confirma (COMMIT) y revierte (ROLLBACK) correctamente. ✅

## Casos de error cubiertos
Conexión con credenciales incorrectas → PDOException (manejada como 500 por el Front Controller).
Seed re-ejecutado → no duplica datos (TRUNCATE + INSERT idempotente).
