# 04 — API REST

> **Contrato formal:** [`openapi.yaml`](openapi.yaml) (contract-first). Si el código y el contrato
> difieren, se corrigen ambos en el mismo commit (AGENTS.md §5).
> **Base URL:** `/api` · **Formato:** JSON (`application/json; charset=utf-8`).
> **Autenticación:** todo request a `/api/*` exige el encabezado `X-API-Key`.

## 1. Autenticación

```http
X-API-Key: <clave configurada en config/config.php>
```

- Validada con `hash_equals()` (comparación en tiempo constante).
- Falta o es inválida → **`401 NO_AUTORIZADO`**.
- "Fail-closed": si la clave configurada está vacía, **nadie** autentica.

## 2. Formato único de error

Todas las respuestas de error —**incluidos 404, 405 y 500**— usan la misma estructura:

```json
{
  "error": {
    "code": "CODIGO_ERROR",
    "message": "Mensaje comprensible para el usuario",
    "fields": {}
  }
}
```

| Código | HTTP | Cuándo |
| --- | --- | --- |
| `NO_AUTORIZADO` | 401 | Falta `X-API-Key` o no coincide. |
| `NO_ENCONTRADO` | 404 | Recurso o ruta inexistente. |
| `METODO_NO_PERMITIDO` | 405 | Ruta válida con método no soportado (incluye cabecera `Allow`). |
| `VALIDACION` | 422 | Datos inválidos; `fields` mapea `campo → mensaje`. |
| `TRANSICION_INVALIDA` | 409 | Cambio de estado no permitido por la máquina de estados (RN-06). |
| `TECNICO_AL_TOPE` | 409 | El técnico ya tiene 5 tickets activos (RN-05). |
| `ERROR_INTERNO` | 500 | Falla inesperada; sin trazas al cliente (solo en logs). |

## 3. Endpoints

### 3.1 `GET /api/tickets` — lista paginada y filtrable

**Query params**

| Param | Tipo | Por defecto | Reglas |
| --- | --- | --- | --- |
| `estado` | string | — | `ABIERTO` \| `EN CURSO` \| `CERRADO`. Otro → 422. |
| `prioridad` | string | — | `Alta` \| `Media` \| `Baja`. Otro → 422. |
| `tecnico` | int | — | `id` de técnico. |
| `q` | string | — | Texto libre (número de ticket o nombre de usuario). |
| `page` | int | `1` | ≥ 1. Inválido → 422. |
| `per_page` | int | `10` | 1–50. Inválido → 422. |

**200 OK**

```json
{
  "data": [
    {
      "numero": "TK-2026-000042",
      "usuario": "Ana María Torres",
      "usuario_codigo": "U001",
      "categoria": "Red",
      "prioridad": "Alta",
      "tecnico": "Carlos Ramírez",
      "tecnico_id": 1,
      "estado": "EN CURSO",
      "creado": "2026-10-18T17:00:00-05:00",
      "vence": "2026-10-18T21:00:00-05:00",
      "sla": "EN_TIEMPO"
    }
  ],
  "meta": { "page": 1, "per_page": 10, "total": 37 }
}
```

`sla` ∈ `EN_TIEMPO` \| `POR_VENCER` \| `VENCIDO` \| `null` (cerrado).

---

### 3.2 `GET /api/tickets/{numero}` — detalle con historial

**200 OK** — mismos campos que la lista **más** `descripcion`, `solucion`, `cerrado` e `historial`:

```json
{
  "numero": "TK-2026-000001",
  "usuario": "Ana María Torres",
  "usuario_codigo": "U001",
  "categoria": "Red",
  "prioridad": "Alta",
  "tecnico": "Carlos Ramírez",
  "tecnico_id": 1,
  "estado": "EN CURSO",
  "creado": "2026-10-19T09:06:32-05:00",
  "vence": "2026-10-19T13:06:32-05:00",
  "sla": "VENCIDO",
  "descripcion": "La VPN corporativa se desconecta cada pocos minutos…",
  "solucion": null,
  "cerrado": null,
  "historial": [
    {
      "fecha": "2026-10-19T09:06:32-05:00",
      "tipo_cambio": "CREACION",
      "estado_anterior": null,
      "estado_nuevo": "ABIERTO",
      "tecnico_anterior": null,
      "tecnico_nuevo": "Carlos Ramírez",
      "observacion": "Ticket registrado"
    }
  ]
}
```

**Errores:** `401`, `404` (número inexistente).

---

### 3.3 `POST /api/tickets` — crear ticket

**Request**

```json
{
  "usuario_codigo": "U001",
  "categoria_id": 1,
  "descripcion": "El monitor no enciende desde esta mañana.",
  "prioridad": "Alta",
  "tecnico_id": 1
}
```

| Campo | Regla |
| --- | --- |
| `usuario_codigo` | Debe existir y estar activo (RN-02) → si no, 422 en `usuario_codigo`. |
| `categoria_id` | Debe existir en `categorias` (RN-04) → 422. |
| `descripcion` | 20–1000 caracteres tras `trim` (RN-03) → 422. |
| `prioridad` | `Alta` \| `Media` \| `Baja` (RN-04) → 422. |
| `tecnico_id` | Técnico activo con menos de 5 tickets activos (RN-05) → 409 `TECNICO_AL_TOPE`. |

**201 Created** — devuelve el ticket creado (mismo formato del detalle).
**Errores:** `401`, `409` (`TECNICO_AL_TOPE`), `422` (`fields` con los campos inválidos).

---

### 3.4 `PATCH /api/tickets/{numero}/estado` — cambiar estado

**Request**

```json
{ "estado": "EN CURSO", "observacion": "Se inició la revisión en sitio." }
```

- Transiciones válidas: `ABIERTO ➔ EN CURSO ➔ CERRADO` (RN-06).
- Saltar, retroceder o repetir estado → **409 `TRANSICION_INVALIDA`**.
- Estado desconocido → **422 `VALIDACION`**.
- Cerrar (`CERRADO`) **exige** `observacion` (la solución); se guarda en `solucion`. Sin ella → **422**.

**200 OK** — ticket actualizado (con `sla: null` si quedó cerrado).
**Errores:** `401`, `404`, `409`, `422`.

---

### 3.5 `PATCH /api/tickets/{numero}/tecnico` — reasignar

**Request**

```json
{ "tecnico_id": 2 }
```

- El ticket **no** puede estar `CERRADO` → 409 (RN-07).
- El nuevo técnico debe estar activo y con cupo; si es el mismo, no cuenta contra el tope (RN-05).
- Sin cupo → **409 `TECNICO_AL_TOPE`**.

**200 OK** — ticket reasignado (deja historial `REASIGNACION`).
**Errores:** `401`, `404`, `409`, `422`.

---

### 3.6 `GET /api/usuarios/{codigo}` — solicitante por código

**200 OK**

```json
{ "codigo": "U001", "nombre": "Ana María Torres", "cargo": "Analista de Ventas", "area": "Comercial" }
```

**Errores:** `401`, `404` (código inexistente o usuario inactivo).

---

### 3.7 `GET /api/tecnicos` — técnicos activos con su carga

**200 OK**

```json
{
  "data": [
    { "id": 3, "nombre": "Andrés Pérez", "carga_activa": 3, "disponible": true },
    { "id": 2, "nombre": "Laura Gómez", "carga_activa": 4, "disponible": true },
    { "id": 1, "nombre": "Carlos Ramírez", "carga_activa": 5, "disponible": false }
  ]
}
```

Se ordena por carga ascendente; `disponible` es `false` cuando `carga_activa >= 5`.

---

### 3.8 `GET /api/resumen` — métricas del tablero

**200 OK**

```json
{
  "por_estado": { "ABIERTO": 6, "EN CURSO": 6, "CERRADO": 13 },
  "vencidos": 4,
  "carga_por_tecnico": [
    { "tecnico_id": 3, "nombre": "Andrés Pérez", "carga_activa": 3, "disponible": true }
  ]
}
```

---

### 3.9 `GET /api/categorias` — catálogo desde la BD

**200 OK**

```json
{ "data": [ { "id": 1, "nombre": "Hardware" }, { "id": 2, "nombre": "Software" } ] }
```

---

### 3.10 `GET /api/tickets/export.csv` — exportar la lista filtrada *(extra)*

Acepta **los mismos filtros** de `GET /api/tickets` (`estado`, `prioridad`, `tecnico`, `q`) y **no pagina**.

**200 OK**

```http
Content-Type: text/csv; charset=utf-8
Content-Disposition: attachment; filename="tickets_20261019_150742.csv"
```

Separador `;`, celdas entrecomilladas (RFC 4180) y fórmulas neutralizadas (`=`, `+`, `-`, `@`).

**Errores:** `401`, `422` (filtro inválido).

---

### 3.11 `GET /api/ping` — health check *(no exige clave)*

**200 OK** → `{ "status": "ok" }`

## 4. Ejemplos con `curl`

```bash
BASE=http://localhost:8080/api
K="X-API-Key: mi-clave-secreta-123"

# Lista filtrada y paginada
curl -s -H "$K" "$BASE/tickets?estado=EN%20CURSO&prioridad=Alta&page=1&per_page=10"

# Detalle con historial
curl -s -H "$K" "$BASE/tickets/TK-2026-000001"

# Crear
curl -s -X POST -H "$K" -H 'Content-Type: application/json' \
  -d '{"usuario_codigo":"U001","categoria_id":1,"descripcion":"El monitor no enciende desde esta mañana.","prioridad":"Alta","tecnico_id":1}' \
  "$BASE/tickets"

# Cambiar estado (requiere observación al cerrar)
curl -s -X PATCH -H "$K" -H 'Content-Type: application/json' \
  -d '{"estado":"EN CURSO","observacion":"Revisión iniciada."}' "$BASE/tickets/TK-2026-000001/estado"

# Reasignar
curl -s -X PATCH -H "$K" -H 'Content-Type: application/json' \
  -d '{"tecnico_id":2}' "$BASE/tickets/TK-2026-000001/tecnico"

# Exportar CSV
curl -s -H "$K" "$BASE/tickets/export.csv?prioridad=Alta" -o tickets.csv
```

> La colección Postman y el archivo `.http` con casos de error están en [`postman/`](../postman/).
