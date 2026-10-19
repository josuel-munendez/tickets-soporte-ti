# Sistema de Tickets de Soporte TI — MEGACOMERCIAL SAS

Herramienta web (SPA) para que el área de Soporte TI registre, asigne y haga seguimiento a
los tickets de soporte técnico de usuarios internos. El frontend es HTML5 + JavaScript ES6
vanilla que consume, vía `fetch`, una **API REST pura en PHP 8** (sin framework de backend).

> Documentación de arquitectura, reglas y decisiones en [`docs/`](docs/) (Doc-as-Code) y
> especificaciones por funcionalidad en [`specs/`](specs/) (Spec-Driven Development). El contrato
> de la API se documenta en `docs/04-api.md` y `docs/openapi.yaml` (OpenAPI 3.0).

---

## 1. Stack

| Capa | Tecnología |
| --- | --- |
| Backend | PHP 8.1+ sin framework (Composer solo para autoload PSR-4 y PHPUnit) |
| Base de datos | MySQL 8.0+ (InnoDB, `utf8mb4`), acceso **solo con PDO y sentencias preparadas** |
| Frontend | HTML5 + JavaScript ES6 vanilla (`fetch`); CSS propio (sin CDN) |
| Zona horaria | `America/Bogota` (UTC-05:00) en PHP y en la sesión de MySQL |
| Seguridad | API key por `X-API-Key` (`hash_equals`), formato único de error, sin trazas al cliente |

---

## 2. Requisitos

- PHP **8.1+** con la extensión `pdo_mysql`
- MySQL **8.0+**
- Composer (opcional; solo para autoload y pruebas)
- Un servidor web. Para desarrollo, el servidor embebido de PHP:

```bash
php -S localhost:8080 -t public public/index.php
```

> `public/index.php` es el **Front Controller**: sirve la SPA e inyecta la API key, y enruta `/api/*`.
> Los archivos estáticos (`assets/`) se sirven tal cual.

---

## 3. Instalación

```bash
# 1. Dependencias (opcional: hay un autoload PSR-4 de respaldo si no usas Composer)
composer install

# 2. Configuración (NUNCA se versiona config/config.php)
cp config/config.example.php config/config.php
#   Edita config/config.php con tus credenciales de MySQL y tu API key propia.

# 3. Base de datos
mysql -u root -p -e "CREATE DATABASE soporte_ti CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p soporte_ti < database/schema.sql
mysql -u root -p soporte_ti < database/seed.sql      # datos de prueba (re-ejecutable)

# 4. Levantar la aplicación
php -S localhost:8080 -t public public/index.php
```

Abre **http://localhost:8080/** para la SPA y **http://localhost:8080/api/ping** para la API.

### Alternativa con Docker (opcional, +10 pts)

```bash
cp .env.example .env          # ajusta credenciales y API_KEY (el .env NO se versiona)
docker compose up --build     # levanta MySQL 8.0 + PHP 8.1/Apache
```

La app queda en **http://localhost:8080/** (cambia `APP_PUERTO` en `.env` para otro puerto).
MySQL ejecuta `database/schema.sql` y `database/seed.sql` automáticamente la primera vez que se crea
el volumen de datos. El archivo `config/config.php` lo genera `docker/entrypoint.sh` desde las variables
de entorno, así que ningún secreto se versiona. Para reiniciar de cero: `docker compose down -v`.

> Con Apache, el enrutamiento al Front Controller lo hace `public/.htaccess` (mod_rewrite); en el
> servidor embebido de PHP lo hace el propio `public/index.php`.

### Datos de prueba (`seed.sql`)
- **10 usuarios** (`U001`…`U010`); `U010` está **inactivo** (para probar el rechazo).
- **4 técnicos** (1 = Carlos Ramírez, 2 = Laura Gómez, 3 = Andrés Pérez; el 4, Marta Díaz, está **inactivo**).
- **4 categorías**: 1 Hardware, 2 Software, 3 Red, 4 Otro.
- **25 tickets** repartidos en ABIERTO / EN CURSO / CERRADO, incluyendo vencidos y por vencer.

---

## 4. Endpoints

Base URL: `/api`. **Todos** exigen el encabezado `X-API-Key`.

| Método | Ruta | Descripción | OK | Errores |
| --- | --- | --- | --- | --- |
| `GET` | `/api/tickets` | Lista paginada y filtrable (`estado`, `prioridad`, `tecnico`, `q`, `page`, `per_page`). | `200` | `401`, `422` |
| `GET` | `/api/tickets/{numero}` | Detalle del ticket con su historial completo. | `200` | `401`, `404` |
| `POST` | `/api/tickets` | Crea un ticket. | `201` | `401`, `409`, `422` |
| `PATCH` | `/api/tickets/{numero}/estado` | Cambia el estado (`{"estado", "observacion"}`). | `200` | `401`, `404`, `409`, `422` |
| `PATCH` | `/api/tickets/{numero}/tecnico` | Reasigna técnico (`{"tecnico_id"}`). | `200` | `401`, `404`, `409`, `422` |
| `GET` | `/api/usuarios/{codigo}` | Datos del solicitante por código. | `200` | `401`, `404` |
| `GET` | `/api/tecnicos` | Técnicos activos con su carga actual. | `200` | `401` |
| `GET` | `/api/resumen` | Métricas del tablero. | `200` | `401` |
| `GET` | `/api/tickets/export.csv` | Exporta a CSV los tickets filtrados (mismos filtros que la lista). | `200` | `401`, `422` |
| `GET` | `/api/categorias` | Catálogo de categorías. | `200` | `401` |

**Formato único de error** (sin excepciones, incluso 404/405/500):

```json
{ "error": { "code": "VALIDACION", "message": "Mensaje para el usuario", "fields": { "campo": "detalle" } } }
```

Códigos: `NO_AUTORIZADO` (401) · `NO_ENCONTRADO` (404) · `METODO_NO_PERMITIDO` (405) ·
`TRANSICION_INVALIDA` / `TECNICO_AL_TOPE` (409) · `VALIDACION` (422) · `ERROR_INTERNO` (500).

---

## 5. Ejemplos con `curl`

Define tu clave una vez (usa la misma que pusiste en `config/config.php`):

```bash
MI_CLAVE="tu-clave-aqui"                 # reemplázala por tu clave real
BASE="http://localhost:8080/api"
```

En cada `curl` de abajo se envía como `-H "X-API-Key: $MI_CLAVE"`.

### Listar (paginado y con filtros)
```bash
curl -s -H "X-API-Key: $MI_CLAVE" "$BASE/tickets?estado=EN%20CURSO&prioridad=Alta&page=1&per_page=10"
```

### Ver el detalle con historial
```bash
curl -s -H "X-API-Key: $MI_CLAVE" "$BASE/tickets/TK-2026-000001"
```

### Crear un ticket
```bash
curl -s -X POST -H "X-API-Key: $MI_CLAVE" -H "Content-Type: application/json" \
  -d '{"usuario_codigo":"U001","categoria_id":3,"descripcion":"La VPN corporativa se cae cada 10 minutos.","prioridad":"Alta","tecnico_id":3}' \
  "$BASE/tickets"
```
Respuesta `201`:
```json
{ "numero": "TK-2026-000026", "estado": "ABIERTO", "sla": "EN_TIEMPO",
  "creado": "2026-10-19T00:02:05-05:00", "vence": "2026-10-19T04:02:05-05:00", "historial": [ … ] }
```

### Cambiar de estado
```bash
# ABIERTO -> EN CURSO
curl -s -X PATCH -H "X-API-Key: $MI_CLAVE" -H "Content-Type: application/json" \
  -d '{"estado":"EN CURSO"}' "$BASE/tickets/TK-2026-000002/estado"

# EN CURSO -> CERRADO (la observación es la solución aplicada)
curl -s -X PATCH -H "X-API-Key: $MI_CLAVE" -H "Content-Type: application/json" \
  -d '{"estado":"CERRADO","observacion":"Se reinstaló el cliente de VPN y quedó operativo."}' \
  "$BASE/tickets/TK-2026-000001/estado"
```

### Reasignar técnico
```bash
curl -s -X PATCH -H "X-API-Key: $MI_CLAVE" -H "Content-Type: application/json" \
  -d '{"tecnico_id":2}' "$BASE/tickets/TK-2026-000004/tecnico"
```

### Catálogos y tablero
```bash
curl -s -H "X-API-Key: $MI_CLAVE" "$BASE/usuarios/U001"
curl -s -H "X-API-Key: $MI_CLAVE" "$BASE/tecnicos"
curl -s -H "X-API-Key: $MI_CLAVE" "$BASE/categorias"
curl -s -H "X-API-Key: $MI_CLAVE" "$BASE/resumen"
```

### Exportar a CSV (respeta los filtros)
```bash
curl -s -H "X-API-Key: $MI_CLAVE" \
  "$BASE/tickets/export.csv?estado=EN%20CURSO&prioridad=Alta" -o tickets.csv
```

---

## 6. Pruebas de las reglas de negocio (manuales)

```bash
# 401 sin clave
curl -s -o /dev/null -w "%{http_code}\n" "$BASE/tickets"                 # -> 401

# 409: no se puede saltar de ABIERTO a CERRADO
curl -s -X PATCH -H "X-API-Key: $MI_CLAVE" -H "Content-Type: application/json" \
  -d '{"estado":"CERRADO","observacion":"x"}' "$BASE/tickets/TK-2026-000002/estado"

# 422: cerrar sin solución / descripción corta / usuario inactivo
curl -s -X POST -H "X-API-Key: $MI_CLAVE" -H "Content-Type: application/json" \
  -d '{"usuario_codigo":"U010","categoria_id":1,"descripcion":"corta","prioridad":"Urgente","tecnico_id":3}' \
  "$BASE/tickets"

# 409 TECNICO_AL_TOPE: el técnico 1 ya tiene 5 tickets activos en el seed
curl -s -X PATCH -H "X-API-Key: $MI_CLAVE" -H "Content-Type: application/json" \
  -d '{"tecnico_id":1}' "$BASE/tickets/TK-2026-000008/tecnico"
```

### Consecutivo seguro ante concurrencia (RN-01)
```bash
# 20 POST en paralelo: nunca se repite el número
for i in $(seq 1 20); do
  curl -s -X POST -H "X-API-Key: $MI_CLAVE" -H "Content-Type: application/json" \
    -d "{\"usuario_codigo\":\"U00$(( (i % 9) + 1 ))\",\"categoria_id\":1,\"descripcion\":\"Prueba de concurrencia número $i con texto suficiente.\",\"prioridad\":\"Media\",\"tecnico_id\":$(( (i % 4) + 1 ))}" \
    "$BASE/tickets" >> /tmp/tickets.json &
done; wait
grep -o 'TK-2026-[0-9]*' /tmp/tickets.json | sort | uniq -d   # debe salir vacío
```

> El número de tickets que se creen depende del cupo libre (tope de 5 por técnico, RN-05).
> Para una corrida de 20 exitosos, libera cupo cerrando tickets activos o activando más técnicos.

---

## 7. Colección Postman / archivo `.http`

- **Postman:** importa `postman/Soporte-TI.postman_collection.json` y selecciona el entorno
  `Local` (`postman/Soporte-TI.local.postman_environment.json`). Define `api_key` con tu clave.
- **VS Code (REST Client) / JetBrains:** abre `postman/soporte-ti.http` y ajusta las variables
  `@base_url` y `@api_key`.

Ambos incluyen los 9 endpoints y los casos de error (401, 404, 409, 422).

---

## 8. Estructura del proyecto

```text
├── AGENTS.md              # Constitución del proyecto (reglas para agentes/desarrolladores)
├── README.md              # Este archivo
├── composer.json          # Autoload PSR-4 (App\ => src/) y PHPUnit
├── config/                # config.example.php (plantilla) / config.php (real, ignorado por Git)
├── database/              # schema.sql + seed.sql
├── public/                # ÚNICA carpeta pública: index.php (front controller), app.html (SPA), .htaccess, assets/
├── src/
│   ├── Controllers/       # HTTP delgado (lee Request, llama Service, responde JSON)
│   ├── Services/          # Reglas de negocio y transacciones
│   ├── Repositories/      # ÚNICO lugar con SQL (PDO preparado)
│   ├── Domain/            # Clases puras: SlaCalculator, EstadoTicket, Constantes, CsvExporter
│   ├── Http/              # Router, Request, Response, Middlewares
│   ├── Exceptions/        # Excepciones de dominio con código HTTP
│   └── Support/           # Config, Database (PDO + transaction())
├── specs/                 # Spec-Driven Development (spec/plan/tasks por funcionalidad)
├── tests/                 # PHPUnit: Unit (dominio puro) e Integration (BD real)
├── scripts/               # guards.sh (barreras automáticas)
├── docker/                # entrypoint.sh (genera config.php desde el entorno)
├── Dockerfile             # Imagen PHP 8.1 + Apache con el docroot en public/
├── docker-compose.yml     # app + MySQL 8.0 (schema/seed como initdb)
└── .env.example           # Plantilla de variables de entorno para Docker (sin secretos)
```

---

## 9. Reglas de negocio implementadas

| ID | Regla | Dónde |
| --- | --- | --- |
| RN-01 | Consecutivo `TK-AAAA-NNNNNN` seguro ante concurrencia (transacción + `FOR UPDATE` + `UNIQUE`) | `TicketService`, `TicketRepository` |
| RN-02 | El solicitante debe existir y estar activo | `UsuarioService`, `TicketService` |
| RN-03 | Descripción de 20 a 1000 caracteres (`trim`, `mb_strlen`) | `TicketService::validarCreacion` |
| RN-04 | Categoría existente y prioridad ∈ {Alta, Media, Baja} | `TicketService` |
| RN-05 | Máximo 5 tickets activos por técnico (mismo técnico no cuenta) | `TicketService`, `Constantes::MAX_TICKETS_TECNICO` |
| RN-06 | Máquina de estados `ABIERTO ➔ EN CURSO ➔ CERRADO` | `Domain\EstadoTicket` |
| RN-07 | No se reasigna un ticket cerrado | `TicketService::reasignarTecnico` |
| RN-08 | SLA: Alta +4 h, Media +24 h, Baja +72 h; VENCIDO/POR_VENCER/EN_TIEMPO | `Domain\SlaCalculator` |
| RN-09 | Toda creación/cambio/reasignación deja historial en la misma transacción | `TicketRepository::insertarHistorial` |

---

## 10. Pruebas automatizadas (PHPUnit)

```bash
composer install          # instala PHPUnit (dependencia de desarrollo)
./vendor/bin/phpunit      # o: composer test
```

La suite tiene dos grupos:

- **`Unit`** (dominio puro, sin base de datos): máquina de estados (RN-06), SLA (RN-08),
  tope de carga (RN-05) y el generador CSV.
- **`Integration`** (contra MySQL con `config/config.php`): flujo completo ABIERTO→EN CURSO→CERRADO
  con historial, 409 al saltar de estado, 422 al cerrar sin solución, 422 con usuario inactivo y
  verificación de que el **tope no quema el consecutivo** (ROLLBACK). Si no hay BD, este grupo se
  **omite** en vez de fallar.

Las pruebas de integración crean sus propios datos y los eliminan al terminar (no ensucian el `seed`).

---

## 11. Calidad y barreras automáticas

```bash
bash scripts/guards.sh
```

`guards.sh` falla si un PHP no compila, si se commitea `config/config.php` o hay secretos,
si aparece HTML crudo (`innerHTML`), si hay SQL concatenado, si hay acceso a PDO fuera de
`Repositories/Support` o si queda depuración olvidada.

---

## 12. Declaración de uso de IA

> Este proyecto se desarrolló con apoyo de herramientas de IA (OpenCode, GitHub Copilot y
> asistentes de chat) para generar y revisar código y documentación, siguiendo una metodología
> Spec-Driven con `AGENTS.md` como reglas. Todo el código fue leído, probado y entendido por el
> autor, quien puede explicarlo y modificarlo en vivo. La bitácora está en `docs/ai-log.md`.

---

## 13. Autor

Prueba técnica — Aprendiz de Soporte TI, **MEGACOMERCIAL SAS**. Zona horaria: `America/Bogota`.
