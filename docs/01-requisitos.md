# 01 — Requisitos

> **Trazabilidad:** cada requisito tiene un ID y, cuando aplica, la regla de negocio
> (`RN-xx`, ver [`05-reglas-negocio.md`](05-reglas-negocio.md)), el endpoint y/o la prueba
> que lo verifica (ver [`07-pruebas.md`](07-pruebas.md)).

## 1. Requisitos funcionales (RF)

| ID | Requisito | Regla | Endpoint / UI | Verificación |
| --- | --- | --- | --- | --- |
| RF-01 | Registrar un ticket con consecutivo `TK-AAAA-NNNNNN` único | RN-01 | `POST /api/tickets` | `FlujoTicketTest`, concurrencia |
| RF-02 | Validar que el solicitante exista y esté activo | RN-02 | `POST /api/tickets` | `FlujoTicketTest::test_usuario_inactivo…` |
| RF-03 | Exigir descripción de 20 a 1000 caracteres | RN-03 | `POST /api/tickets` | Unit + 422 de campo |
| RF-04 | Validar categoría existente y prioridad ∈ {Alta, Media, Baja} | RN-04 | `POST /api/tickets`, `/categorias` | 422 de campo |
| RF-05 | Limitar a 5 tickets activos por técnico | RN-05 | `POST`, `PATCH .../tecnico` | `FlujoTicketTest::test_tecnico_al_tope…` |
| RF-06 | Máquina de estados `ABIERTO ➔ EN CURSO ➔ CERRADO` | RN-06 | `PATCH .../estado` | `EstadoTicketTest`, `FlujoTicketTest` |
| RF-07 | Exigir solución al cerrar; guardarla en el ticket | RN-06 | `PATCH .../estado` | `FlujoTicketTest::test_cerrar_sin_solucion…` |
| RF-08 | No reasignar un ticket cerrado | RN-07 | `PATCH .../tecnico` | 409 |
| RF-09 | Calcular y mostrar el SLA por prioridad | RN-08 | `GET /tickets`, detalle | `SlaCalculatorTest` |
| RF-10 | Registrar historial de cada cambio | RN-09 | `GET /tickets/{numero}` | `FlujoTicketTest` (conteo de eventos) |
| RF-11 | Listar tickets con filtros y paginación de servidor | — | `GET /api/tickets` | SPA + curl |
| RF-12 | Ver el detalle de un ticket con su historial | — | `GET /api/tickets/{numero}` | SPA (modal) |
| RF-13 | Buscar solicitante por código | — | `GET /api/usuarios/{codigo}` | SPA (debounce 400 ms) |
| RF-14 | Listar técnicos activos con su carga | RN-05 | `GET /api/tecnicos` | SPA (técnico de menor carga) |
| RF-15 | Resumen del tablero (por estado, vencidos, carga) | — | `GET /api/resumen` | SPA (tablero) |
| RF-16 | Catálogo de categorías desde la base | — | `GET /api/categorias` | SPA (select dinámico) |
| RF-17 | Exportar a CSV la lista filtrada | — | `GET /api/tickets/export.csv` | `CsvExporterTest` + SPA |

## 2. Requisitos no funcionales (RNF)

| ID | Requisito | Cómo se cumple |
| --- | --- | --- |
| RNF-01 | Seguridad ante inyección SQL | 100% sentencias preparadas; SQL solo en `Repositories` (guard §12) |
| RNF-02 | Autenticación de la API | `X-API-Key` validada con `hash_equals` (401 si falta o es inválida) |
| RNF-03 | Prevención de XSS en el navegador | DOM con `createElement`/`textContent`; prohibido HTML crudo |
| RNF-04 | Sin filtrado de información sensible | `display_errors = 0`; 500 genérico; trazas solo en logs internos |
| RNF-05 | Respuestas consistentes | Todo JSON con `Content-Type` y `X-Content-Type-Options: nosniff` |
| RNF-06 | Zona horaria consistente | `America/Bogota` en PHP y `SET time_zone='-05:00'` en PDO |
| RNF-07 | Rendimiento de listado | Índices por `estado`, `prioridad`, `tecnico_id` y orden por `creado_en` |
| RNF-08 | Reproducibilidad del entorno | `schema.sql` + `seed.sql` re-ejecutables; Docker opcional |
| RNF-09 | Mantenibilidad | Capas + SOLID + `declare(strict_types=1)` en todo el PHP |
| RNF-10 | Portabilidad | Sin frameworks; Composer solo para autoload y pruebas |

## 3. Requisitos de interfaz (RI)

| ID | Requisito | Detalle |
| --- | --- | --- |
| RI-01 | API REST pura | Base `/api`, JSON, sin sesiones |
| RI-02 | Formato único de error | `{"error":{"code","message","fields"}}` incluso en 404/405/500 |
| RI-03 | SPA de una sola página | `public/app.html`, todo por `fetch`, sin recargas |
| RI-04 | Catálogos dinámicos | Categorías y técnicos se cargan de la API, no del HTML |
| RI-05 | Tabla con columnas exactas | número, usuario, categoría, prioridad, técnico, estado, creación, SLA |
| RI-06 | Guías de uso | `README.md` y colección Postman / archivo `.http` con casos de error |

## 4. Fuera de alcance

Login/roles, notificaciones, adjuntos, reapertura de tickets y despliegue en hosting en línea
(ver [`00-vision-y-alcance.md`](00-vision-y-alcance.md) §3).
