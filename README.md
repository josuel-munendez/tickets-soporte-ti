# Sistema de Tickets de Soporte TI — MEGACOMERCIAL SAS

## Prueba Técnica – Practicante de Desarrollo SW

| Campo | Valor |
| - | - |
| Apellidos y nombres | Jose Manuel Muñoz Fernández |
| Fecha de entrega | Domingo 18 de Octubre 2026 |
| Repositorio | [https://github.com/josuel-munendez/tickets-soporte-ti](https://github.com/josuel-munendez/tickets-soporte-ti) |

## Contexto

El área de Soporte TI necesita una herramienta web para registrar, asignar y hacer seguimiento a los tickets de soporte de los usuarios internos. El auxiliar de TI registra cada solicitud, la asigna a un técnico según su disponibilidad y carga de trabajo, y la lleva hasta su cierre. La solución debe ser una sola página (SPA): toda la interacción ocurre por AJAX, sin recargar la página.

## Solución

Herramienta web (SPA) para que el área de Soporte TI registre, asigne y haga seguimiento a los tickets de soporte técnico de usuarios internos. El frontend es HTML5 + JavaScript ES6 vanilla que consume, vía `fetch`, una **API REST pura en PHP 8** (sin framework de backend).

> Documentación de arquitectura, reglas y decisiones en [`docs/`](docs/) (Doc-as-Code) y especificaciones por funcionalidad en [`specs/`](specs/) (Spec-Driven Development). El contrato de la API se documenta en [`docs/04-api.md`](docs/04-api.md) y [`docs/openapi.yaml`](docs/openapi.yaml) (OpenAPI 3.0).

## 1. Stack

| Capa | Tecnología |
| - | - |
| Backend | PHP 8.1+ sin framework (Composer solo para autoload PSR-4 y PHPUnit) |
| Base de datos | MySQL 8.0+ (InnoDB, `utf8mb4`), acceso **solo con PDO y sentencias preparadas** |
| Frontend | HTML5 + JavaScript ES6 vanilla (`fetch`); CSS propio (sin CDN) |
| Zona horaria | `America/Bogota` (UTC-05:00) en PHP y en la sesión de MySQL |
| Seguridad | API key por `X-API-Key` (`hash_equals`), formato único de error, sin trazas al cliente |

## 2. Requisitos

- PHP **8.1+** con la extensión `pdo_mysql`
- MySQL **8.0+**
- Composer (opcional; solo para autoload y pruebas)
- Un servidor web. Para desarrollo, el servidor embebido de PHP:

```bash
php -S localhost:8080 -t public public/index.php
```

> `public/index.php` es el **Front Controller**: sirve la SPA e inyecta la API key, y enruta `/api/*`. Los archivos estáticos (`assets/`) se sirven tal cual.

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

La app queda en **http://localhost:8080/** (cambia `APP_PUERTO` en `.env` para otro puerto). MySQL ejecuta `database/schema.sql` y `database/seed.sql` automáticamente la primera vez que se crea el volumen de datos. El archivo `config/config.php` lo genera `docker/entrypoint.sh` desde las variables de entorno, así que ningún secreto se versiona. Para reiniciar de cero: `docker compose down -v`.

> Con Docker **no** hacen falta los pasos manuales de `mysql` de arriba: el contenedor crea la base, ejecuta el esquema y carga el seed por ti.

> Los contenedores se nombran `<proyecto>-db-1` / `<proyecto>-app-1` (el compose no fija `container_name`), así no chocan con otros contenedores de tu máquina. Si ya habías levantado la app con una versión previa, limpia con `docker compose down -v` antes de volver a subir.

> Con Apache, el enrutamiento al Front Controller lo hace `public/.htaccess` (mod_rewrite); en el servidor embebido de PHP lo hace el propio `public/index.php`.

### Datos de prueba (`seed.sql`)

- **10 usuarios** (`U001`…`U010`); `U010` está **inactivo** (para probar el rechazo).
- **4 técnicos** (1 = Carlos Ramírez, 2 = Laura Gómez, 3 = Andrés Pérez; el 4, Marta Díaz, está **inactivo**).
- **4 categorías**: 1 Hardware, 2 Software, 3 Red, 4 Otro.
- **25 tickets** repartidos en ABIERTO / EN CURSO / CERRADO, incluyendo vencidos y por vencer.

## 4. Endpoints

Base URL: `/api`. **Todos** exigen el encabezado `X-API-Key`.

| Método | Ruta | Descripción | OK | Errores |
| - | - | - | - | - |
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

Códigos: `NO_AUTORIZADO` (401) · `NO_ENCONTRADO` (404) · `METODO_NO_PERMITIDO` (405) · `TRANSICION_INVALIDA` / `TECNICO_AL_TOPE` (409) · `VALIDACION` (422) · `ERROR_INTERNO` (500).

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
  "creado": "2026-10-19T00:02:05-05:00", "vence": "2026-10-19T04:02:05-05:00", "historial": [ ] }
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

> El número de tickets que se creen depende del cupo libre (tope de 5 por técnico, RN-05). Para una corrida de 20 exitosos, libera cupo cerrando tickets activos o activando más técnicos.

## 7. Colección Postman / archivo `.http`

- **Postman:** importa `postman/Soporte-TI.postman_collection.json` y selecciona el entorno `Local` (`postman/Soporte-TI.local.postman_environment.json`). Define `api_key` con tu clave.
- **VS Code (REST Client) / JetBrains:** abre `postman/soporte-ti.http` y ajusta las variables `@base_url` y `@api_key`.

Ambos incluyen los 9 endpoints y los casos de error (401, 404, 409, 422).

## 8. Estructura del proyecto

```
├── AGENTS.md              # Constitución del proyecto (reglas para agentes/desarrolladores)
├── README.md              # Este archivo
├── composer.json          # Autoload PSR-4 (App\ => src/) y PHPUnit
├── .opencode/             # Configuración de la herramienta de IA (opencode.json)
├── config/                # config.example.php (plantilla) / config.php (real, ignorado por Git)
├── database/              # schema.sql + seed.sql + init-render.sh (proveedores gestionados)
├── public/                # ÚNICA carpeta pública: index.php (front controller), app.html (SPA), .htaccess, assets/
├── docs/                  # Doc-as-Code
│   ├── contexto/          # Enunciado y descripción del cargo (material de referencia)
│   ├── prompts-ia/        # Prompts de trabajo con el agente
│   ├── herramientas-ia/   # Notas sobre las herramientas de IA
│   └── evidencias/        # Ejemplo de export CSV
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
├── docker/                # entrypoint.sh (app) y entrypoint-allinone.sh (app+BD)
├── Dockerfile             # Imagen PHP 8.1 + Apache con el docroot en public/ (solo app)
├── Dockerfile.allinone    # Imagen PHP 8.1 + Apache + MariaDB (app + BD en un contenedor)
├── docker-compose.yml     # app + MySQL 8.0 (schema/seed como initdb)
├── render.yaml            # Blueprint de Render (solo el web service; DB_URL manual)
└── .env.example           # Plantilla de variables de entorno para Docker (sin secretos)
```

## 9. Reglas de negocio implementadas

| ID | Regla | Dónde |
| - | - | - |
| RN-01 | Consecutivo `TK-AAAA-NNNNNN` seguro ante concurrencia (transacción + `FOR UPDATE` + `UNIQUE`) | `TicketService`, `TicketRepository` |
| RN-02 | El solicitante debe existir y estar activo | `UsuarioService`, `TicketService` |
| RN-03 | Descripción de 20 a 1000 caracteres (`trim`, `mb_strlen`) | `TicketService::validarCreacion` |
| RN-04 | Categoría existente y prioridad ∈ {Alta, Media, Baja} | `TicketService` |
| RN-05 | Máximo 5 tickets activos por técnico (mismo técnico no cuenta) | `TicketService`, `Constantes::MAX_TICKETS_TECNICO` |
| RN-06 | Máquina de estados `ABIERTO ➔ EN CURSO ➔ CERRADO` | `Domain\EstadoTicket` |
| RN-07 | No se reasigna un ticket cerrado | `TicketService::reasignarTecnico` |
| RN-08 | SLA: Alta +4 h, Media +24 h, Baja +72 h; VENCIDO/POR_VENCER/EN_TIEMPO | `Domain\SlaCalculator` |
| RN-09 | Toda creación/cambio/reasignación deja historial en la misma transacción | `TicketRepository::insertarHistorial` |

## 10. Pruebas automatizadas (PHPUnit)

```bash
composer install          # instala PHPUnit (dependencia de desarrollo)
./vendor/bin/phpunit      # o: composer test
```

La suite tiene dos grupos:

- **`Unit`** (dominio puro, sin base de datos): máquina de estados (RN-06), SLA (RN-08), tope de carga (RN-05) y el generador CSV.
- **`Integration`** (contra MySQL con `config/config.php`): flujo completo ABIERTO→EN CURSO→CERRADO con historial, 409 al saltar de estado, 422 al cerrar sin solución, 422 con usuario inactivo y verificación de que el **tope no quema el consecutivo** (ROLLBACK). Si no hay BD, este grupo se **omite** en vez de fallar.

Las pruebas de integración crean sus propios datos y los eliminan al terminar (no ensucian el `seed`).

## 11. Calidad y barreras automáticas

```bash
bash scripts/guards.sh
```

`guards.sh` falla si un PHP no compila, si se commitea `config/config.php` o hay secretos, si aparece HTML crudo (`innerHTML`), si hay SQL concatenado, si hay acceso a PDO fuera de `Repositories/Support` o si queda depuración olvidada.

## 12. Despliegue en línea (punto extra, opcional)

> El enunciado marca el despliegue en línea como **punto extra opcional**. La calificación
> principal (100 pts) se juega en **local**, con `docker compose` o `php -S`, donde tienes control
> total de MySQL, el consecutivo y PHPUnit. Si el tiempo aprieta, prioriza local + video + README.

El repo ya trae todo lo necesario para desplegar por Docker **sin cambiar código**:

- `Dockerfile.allinone` (**app + MariaDB en un solo contenedor**) + `docker/entrypoint-allinone.sh`: levanta la BD, aplica esquema/seed y sirve la app. Ideal para el plan Free de Render sin base externa.
- `Dockerfile` (solo app) con `ENTRYPOINT` propio: genera `config/config.php` desde variables de entorno y **adapta Apache al `$PORT`** que inyecta la plataforma.
- `docker/entrypoint.sh` acepta **`DB_URL`** (`mysql://usuario:pass@host:puerto/nombre`) o las variables sueltas (`DB_HOST`, `DB_PUERTO`, …) que usa `docker compose`.
- `database/init-render.sh` aplica el esquema y el seed en proveedores gestionados (omite `CREATE DATABASE`/`USE`, que solo aplican en local).

> ⚠️ **Render NO ofrece MySQL gestionado nativo** (solo PostgreSQL y Redis). Por eso hay tres caminos reales:

### Opción 1 — Render "todo en uno" (app + BD en un solo contenedor) ⭐

Si **no quieres usar Railway ni contratar una base externa**, esta es la vía directa: un `Dockerfile.allinone` empaqueta **PHP 8.1 + Apache + MariaDB** en **un único contenedor**. Al arrancar, el entrypoint levanta MariaDB, aplica `schema.sql` + `seed.sql` y sirve la app. Todo dentro del **plan Free de Render**.

1. En Render: **New +** → **Web Service** → conecta `tickets-soporte-ti`.
2. Configura:
   - **Language:** `Docker`
   - **Branch:** `main`
   - **Dockerfile Path:** `./Dockerfile.allinone`  ← **el nombre importa**
   - **Health Check Path:** `/api/ping`
   - **Compute Plan:** `Free`
3. **Environment Variables:** solo `API_KEY` = *tu clave* (no hace falta `DB_URL`).
4. **Deploy**. Al terminar, abre `https://<servicio>.onrender.com/` y usa la misma `API_KEY` en `X-API-Key`.

> El esquema y el seed se aplican **en cada arranque** (el `seed.sql` es re-ejecutable).

> ⚠️ **Limitación del plan Free (léela):** Render Free **no da discos persistentes** y el servicio se apaga tras ~15 min sin tráfico. Al apagarse o re-desplegarse, el contenedor se recrea y la base **vuelve al estado del seed** (los 25 tickets de demo). Es perfecto para una **demo/entrega**; los datos que se creen durante la sustentación se perderán tras la inactividad. Si necesitas persistencia real, usa la Opción 2.

> Al despertar de la suspensión, la primera petición tarda ~30–60 s (arranca MariaDB + aplica el seed). Es normal.

### Opción 2 — Render (app) + MySQL externo gratis

Render hospeda el **servicio web** gratis, pero el MySQL lo pones **tú, externo** (Railway, Aiven, Clever Cloud…), que te da una URL `mysql://…` gratuita. Es la opción con **datos persistentes**:

1. Crea un MySQL gratuito en el proveedor externo y copia su **URL de conexión**.
2. En Render: **New +** → **Blueprint** (usa `render.yaml`) o **New +** → **Web Service** con:
   - **Language:** Docker · **Branch:** `main` · **Dockerfile Path:** `./Dockerfile` · **Health Check Path:** `/api/ping`
3. Variables de entorno del servicio web:
   - `API_KEY` → tu clave (o usa el `generateValue` del blueprint).
   - `DB_URL` → la URL del MySQL externo (en el blueprint va como `sync: false`, se pega a mano).
4. Inicializa la base una sola vez:

```bash
DB_URL="mysql://usuario:pass@host:puerto/soporte_ti" bash database/init-render.sh
```

5. Abre **`https://<servicio>.onrender.com/`** (SPA) y **`/api/ping`** (API).

### Opción 3 — Railway (app + MySQL gestionado en un proyecto)

[Railway](https://railway.com) sí ofrece **MySQL con un clic** y detecta el `Dockerfile`:

1. **New Project** → **Deploy from GitHub repo** → elige `tickets-soporte-ti`.
2. En el mismo proyecto: **+ New** → **Database** → **MySQL**.
3. Añade `API_KEY` al servicio de la app y apunta `DB_URL` al MySQL del proyecto.
4. Inicializa una vez: `DB_URL="mysql://…" bash database/init-render.sh`

> Railway da una **prueba gratuita de $5 por 30 días** (sin tarjeta) y luego un plan Free con $1/mes de crédito.

### Opción 4 — Solo local (lo más seguro para el video)

Si no quieres lidiar con la nube, graba el video con la app local (`docker compose up --build` o `php -S localhost:8080 -t public public/index.php`). Todo funciona sin latencia de red y controlas el consecutivo, el SLA y los errores HTTP.

> La misma mecánica (`DB_URL` + `database/init-render.sh`) sirve para cualquier proveedor con MySQL: Fly.io, Clever Cloud, Aiven, etc.

## 13. Documentación (Doc-as-Code)

Toda la documentación vive en el repo y evoluciona con el código (AGENTS.md §11):

| Documento | Contenido |
| - | - |
| [`docs/00-vision-y-alcance.md`](docs/00-vision-y-alcance.md) | Problema, visión, alcance del MVP, actores |
| [`docs/01-requisitos.md`](docs/01-requisitos.md) | RF, RNF y RI con trazabilidad |
| [`docs/02-arquitectura.md`](docs/02-arquitectura.md) | C4 (contexto, contenedores, componentes) |
| [`docs/03-modelo-datos.md`](docs/03-modelo-datos.md) | Diagrama ER y diccionario de datos |
| [`docs/04-api.md`](docs/04-api.md) · [`docs/openapi.yaml`](docs/openapi.yaml) | Contrato REST (OpenAPI 3.0) |
| [`docs/05-reglas-negocio.md`](docs/05-reglas-negocio.md) | RN-01…RN-09 y máquina de estados |
| [`docs/06-decisiones/`](docs/06-decisiones/) | ADR-001…ADR-005 |
| [`docs/07-pruebas.md`](docs/07-pruebas.md) | Matriz de pruebas y checklist de entrega |
| [`docs/diagrams/`](docs/diagrams/) | Fuentes Mermaid (`.mmd`) y PlantUML (`.puml`) |
| [`docs/contexto/`](docs/contexto/) | Enunciado de la prueba y descripción del cargo (material de referencia) |
| [`docs/evidencias/`](docs/evidencias/) | Ejemplo de export CSV |
| [`docs/prompts-ia/`](docs/prompts-ia/) · [`docs/herramientas-ia/`](docs/herramientas-ia/) | Prompts y configuración de las herramientas de IA |
| [`specs/`](specs/) | SDD: `spec.md` / `plan.md` / `tasks.md` por fase |
| [`docs/ai-log.md`](docs/ai-log.md) | Bitácora de uso de IA |

## 14. Declaración de uso de IA

> Este proyecto se desarrolló con apoyo de herramientas de IA (OpenCode, GitHub Copilot y asistentes de chat) para generar y revisar código y documentación, siguiendo una metodología Spec-Driven con `AGENTS.md` como reglas. Todo el código fue leído, probado y entendido por el autor, quien puede explicarlo y modificarlo en vivo. La bitácora está en `docs/ai-log.md`.

## 15. Autor

Jose Manuel Muñoz Fernández — Desarrollador Junior Full-Stack y Aprendiz SENA en la Tecnología de Análisis y Desarrollo de Software en etapa productiva.

Prueba técnica — Aprendiz de Soporte TI, **MEGACOMERCIAL SAS**. Zona horaria: `America/Bogota`.
