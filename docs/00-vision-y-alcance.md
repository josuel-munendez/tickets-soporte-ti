# 00 — Visión y Alcance

> **Documento:** Doc-as-Code (AGENTS.md §11). Fuente de verdad de la intención del producto.
> **Última actualización:** 2026-10-19 · **Estado:** MVP completo.

## 1. Problema y contexto

El área de **Soporte TI de MEGACOMERCIAL SAS** atiende solicitudes de usuarios internos
(incidentes de hardware, software, red y otros) sin una herramienta central. Los reportes
llegan por canales informales (llamadas, chat, correo), lo que dificulta saber **qué está
pendiente**, **quién lo atiende**, **cuánto lleva esperando** y **si se cumplió el tiempo
de respuesta comprometido (SLA)**.

## 2. Visión

Una herramienta web **simple, rápida y auditable** donde Soporte TI registra, asigna y hace
seguimiento a los tickets, con reglas de negocio claras (consecutivo seguro, máquina de
estados, tope de carga por técnico y SLA), accesible para el equipo sin instalar nada.

## 3. Alcance del MVP

### Dentro del alcance
- Registro de tickets con **consecutivo seguro** `TK-AAAA-NNNNNN`.
- Flujo de estados con **historial auditable** (`ABIERTO ➔ EN CURSO ➔ CERRADO`).
- Cálculo de **SLA** por prioridad con indicadores `EN_TIEMPO / POR_VENCER / VENCIDO`.
- **Tabla** con filtros y **paginación en servidor**, y **tablero** de resumen.
- **API REST** con 9 endpoints, autenticada por **API key** y con **formato único de error**.
- **SPA** en JavaScript vanilla que consume la API por `fetch` (sin recargas).
- Exportación a **CSV** de la lista filtrada.

### Fuera del alcance (*Won't* por ahora, AGENTS.md §1)
- Login y roles de usuario.
- Frameworks de backend o frontend (Laravel, React, Vue, etc.).
- Notificaciones, adjuntos y reapertura de tickets.

## 4. Objetivos y métricas de éxito

| Objetivo | Cómo se verifica |
| --- | --- |
| Trazabilidad total del ticket | Historial completo en `GET /api/tickets/{numero}` (RN-09) |
| Nunca duplicar números de ticket | 20 POST concurrentes → 20 números únicos (RN-01) |
| Respetar el tiempo de respuesta | Indicador de SLA correcto por prioridad (RN-08) |
| Repartir la carga justa | Máximo 5 tickets activos por técnico (RN-05) |
| Operación conforme al contrato | Los 9 endpoints con sus códigos y formato de error (§5) |

## 5. Actores

| Actor | Descripción | Interacción |
| --- | --- | --- |
| **Agente de Soporte TI** | Registra tickets y hace seguimiento | SPA (registrar, filtrar, ver detalle) |
| **Técnico** | Atiende y resuelve los tickets | Asignado a tickets; cambia su estado |
| **Usuario solicitante** | Colaborador interno que reporta | Se identifica por código (`U00x`), no inicia sesión |
| **Consumidor de la API** | Cliente `curl`, Postman u otro sistema | Endpoints REST con `X-API-Key` |

## 6. Principios de diseño

1. **Reglas como datos**, no como cascadas de `if` (transiciones, SLA, prioridades).
2. **Separación estricta en capas**: Controller (HTTP) → Service (negocio) → Repository (SQL).
3. **Seguridad por defecto**: sentencias preparadas, API key con `hash_equals`, sin trazas al cliente.
4. **Trazabilidad**: toda mutación deja historial en la misma transacción.
5. **Documentación como código**: specs, ADR y diagramas de texto versionados junto al código.

## 7. Restricciones

- **PHP 8.1+** sin framework de backend; **MySQL 8.0+**; **JS ES6 vanilla**.
- Zona horaria única **`America/Bogota`** (UTC-05:00).
- Secreto (API key y credenciales) **solo** en `config/config.php`, nunca versionado.
