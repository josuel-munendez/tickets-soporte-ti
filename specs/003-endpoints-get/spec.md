# Spec — Fase 3: Endpoints de lectura (GET) y tablero

> **Estado:** En implementación
> **Reglas:** AGENTS.md §5 (contrato), §6.8 (SLA), §3 (capas), §4 (seguridad).

## Alcance
Los 6 endpoints GET del contrato, consumibles ya por Postman/SPA. Sin escrituras (van en Fases 4-5).

## Endpoints y criterios (Dado/Cuando/Entonces)

### GET `/api/tickets` (lista paginada y filtrable)
- **CA-1** Dado el filtro `estado` ∈ {ABIERTO, EN CURSO, CERRADO}, `prioridad` ∈ {Alta, Media, Baja},
  `tecnico` (id numérico), `q` (texto libre: número de ticket o nombre de usuario), cuando se pide la
  lista, entonces solo se devuelven coincidencias, ordenadas por creación descendente.
- **CA-2** Dado `page` (mín 1, defecto 1) y `per_page` (defecto 10, máx 50), cuando se piden, entonces
  la respuesta trae `data` (máx `per_page` filas) y `meta {page, per_page, total}`.
- **CA-3** Dado `page=0`, `per_page=99`, `estado=X` o `tecnico=abc`, cuando se validan, entonces
  `422 VALIDACION` con `fields` indicando el campo. Sin excepciones de formato.
- **CA-4** Dada la respuesta, cuando se serializa, entonces `creado`/`vence` son ISO 8601 con
  offset `-05:00` y `sla` ∈ {EN_TIEMPO, POR_VENCER, VENCIDO} o `null` si el ticket está CERRADO.

### GET `/api/tickets/{numero}`
- **CA-5** Dado un ticket existente, cuando se pide, entonces devuelve el detalle completo
  (incluye `descripcion`, `solucion`) + `historial` completo en orden cronológico.
- **CA-6** Dado un número inexistente, entonces `404 NO_ENCONTRADO`.

### GET `/api/usuarios/{codigo}`
- **CA-7** Dado `U001` (activo), devuelve `{codigo, nombre, cargo, area}`.
  Dado `U010` (inactivo) o inexistente → `404` ("no encontrado o inactivo"): el formulario
  deshabilita el registro (la validación fuerte se reitera en el POST, RN-02).

### GET `/api/tecnicos`
- **CA-8** Devuelve solo técnicos activos con `{id, nombre, carga_activa, disponible}`
  (`disponible = carga_activa < 5`, RN-05), ordenados de menor a mayor carga.

### GET `/api/categorias`
- **CA-9** Devuelve el catálogo desde BD: `{data: [{id, nombre}]}` (nunca quemado en HTML).

### GET `/api/resumen`
- **CA-10** Devuelve `{por_estado: {ABIERTO, EN CURSO, CERRADO}, vencidos, carga_por_tecnico}`
  para el tablero; se refresca junto con la tabla.

## Reglas de negocio implementadas
- **RN-08 (SLA):** Alta +4h, Media +24h, Baja +72h. `VENCIDO` si `now > vence` y no cerrado;
  `POR_VENCER` si queda < 25% del tiempo total; `EN_TIEMPO` en otro caso activo; `null` si CERRADO.
  Vive en `Domain\SlaCalculator` (clase pura, recibe `now` por parámetro ⇒ testeable sin congelar tiempo).

## Errores
401 sin clave (middleware), 404 (recurso/ruta), 422 (filtros/paginación inválidos),
500 genérico si la BD cae (sin trazas).
