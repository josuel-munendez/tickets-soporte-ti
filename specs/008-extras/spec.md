# Spec — Fase 8: Extras (PHPUnit, export CSV, Docker)

> **Estado:** En implementación
> **Reglas:** AGENTS.md §13 (Definición de Terminado), §9.4 (explicar decisiones), §12 (guards).

## Alcance
Mejoras opcionales del enunciado («Could, +10 pts») que refuerzan la calidad del entregable:

1. **PHPUnit** — pruebas automatizadas de las tres reglas que el autor debe poder
   defender en vivo: máquina de estados (RN-06), SLA (RN-08) y tope de carga (RN-05).
2. **Exportar CSV** — descarga de los tickets filtrados desde la tabla.
3. **docker-compose** — levantar app + MySQL con un comando.

## Criterios de aceptación
- **CA-1** `./vendor/bin/phpunit` corre sin BD (pruebas unitarias de dominio) y, si hay
  `config/config.php` y MySQL, también las de integración; sin BD, las de integración se omiten.
- **CA-2** Los tests cubren: transiciones válidas/ inválidas (saltar, retroceder, reabrir),
  umbrales de SLA (EN_TIEMPO/POR_VENCER/VENCIDO/null) y el borde del tope (4 recibe, 5 no).
- **CA-3** La creación fallida por tope **no quema el consecutivo** (se verifica en integración con ROLLBACK).
- **CA-4** `GET /api/tickets/export.csv` respeta los filtros de la lista, responde `text/csv`
  con `Content-Disposition: attachment` y exige `X-API-Key` (401 sin ella).
- **CA-5** El CSV neutraliza fórmulas (`=`, `+`, `-`, `@`) y entrecomilla según RFC 4180.
- **CA-6** La SPA tiene botón «Exportar CSV» que descarga con los filtros activos (enviando la API key).
- **CA-7** `docker compose up` deja la app y la BD listas; los secretos van por `.env` (no versionado).

## Fuera de alcance
- Despliegue en un hosting en línea (no es requisito del MVP).
