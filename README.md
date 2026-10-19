# Sistema de Tickets de Soporte TI — MEGACOMERCIAL SAS

## Prueba Técnica – Practicante de Desarrollo SW

| Campo | Valor |
| - | - |
| Apellidos y nombres | Jose Manuel Muñoz Fernández |
| Fecha de entrega | Domingo 18 de Octubre 2026 |
| Repositorio | [https://github.com/josuel-munendez/tickets-soporte-ti](https://github.com/josuel-munendez/tickets-soporte-ti) |

## Contexto:

El área de Soporte TI necesita una herramienta web para registrar, asignar y hacer seguimiento a los tickets de soporte de los usuarios internos. El auxiliar de TI registra cada solicitud, la asigna a un técnico según su disponibilidad y carga de trabajo, y la lleva hasta su cierre. La solución debe ser una sola página (SPA): toda la interacción ocurre por AJAX, sin recargar la página.

## Solución:

Herramienta web (SPA) para que el área de Soporte TI registre, asigne y haga seguimiento a los tickets de soporte técnico de usuarios internos. El frontend es HTML5 + JavaScript ES6 vanilla que consume, vía `fetch`, una **API REST pura en PHP 8** (sin framework de backend).

> Documentación de arquitectura, reglas y decisiones en [`docs/`](file:///home/South_Knight/Descargas/Prueba_Tecnica_IT/tickets-soporte-ti/docs/) (Doc-as-Code) y especificaciones por funcionalidad en [`specs/`](file:///home/South_Knight/Descargas/Prueba_Tecnica_IT/tickets-soporte-ti/specs/) (Spec-Driven Development). El contrato de la API se documenta en `docs/04-api.md` y `docs/openapi.yaml` (OpenAPI 3.0).

## 1. Stack

| Capa | Tecnología |
| - | - |
| Backend | PHP 8.1+ sin framework (Composer solo para autoload PSR-4 y PHPUnit) |
| Base de datos | MySQL 8.0+ (InnoDB, `utf8mb4`), acceso **solo con PDO y sentencias preparadas** |
| Frontend | HTML5 + JavaScript ES6 vanilla (`fetch`); CSS propio (sin CDN) |
| Zona horaria | `America/Bogota` (UTC-05:00) en PHP y en la sesión de MySQL |
| Seguridad | API key por `X-API-Key` (`hash\\\_equals`), formato único de error, sin trazas al cliente |


## 2. Requisitos

- PHP **8.1+** con la extensión `pdo\\\_mysql`

- MySQL **8.0+**

- Composer (opcional; solo para autoload y pruebas)

- Un servidor web. Para desarrollo, el servidor embebido de PHP:

```
php -S localhost:8080 -t public public/index.php
```

> `public/index.php` es el **Front Controller**: sirve la SPA e inyecta la API key, y enruta `/api/\\\*`. Los archivos estáticos (`assets/`) se sirven tal cual.

## 3. Instalación

```
\\\# 1. Dependencias (opcional: hay un autoload PSR-4 de respaldo si no usas Composer)    
composer install    
    
\\\# 2. Configuración (NUNCA se versiona config/config.php)    
cp config/config.example.php config/config.php    
\\\#   Edita config/config.php con tus credenciales de MySQL y tu API key propia.    
    
\\\# 3. Base de datos    
mysql -u root -p -e "CREATE DATABASE soporte\\\_ti CHARACTER SET utf8mb4 COLLATE utf8mb4\\\_unicode\\\_ci;"    
mysql -u root -p soporte\\\_ti \\\< database/schema.sql    
mysql -u root -p soporte\\\_ti \\\< database/seed.sql      \\\# datos de prueba (re-ejecutable)    
    
\\\# 4. Levantar la aplicación    
php -S localhost:8080 -t public public/index.php
```

Abre **http://localhost:8080/** para la SPA y **http://localhost:8080/api/ping** para la API.

### Alternativa con Docker (opcional, +10 pts)

```
cp .env.example .env          \\\# ajusta credenciales y API\\\_KEY (el .env NO se versiona)    
docker compose up --build     \\\# levanta MySQL 8.0 + PHP 8.1/Apache
```

La app queda en **http://localhost:8080/** (cambia `APP\\\_PUERTO` en `.env` para otro puerto). MySQL ejecuta `database/schema.sql` y `database/seed.sql` automáticamente la primera vez que se crea el volumen de datos. El archivo `config/config.php` lo genera `docker/entrypoint.sh` desde las variables de entorno, así que ningún secreto se versiona. Para reiniciar de cero: `docker compose down -v`.

> Con Docker **no** hacen falta los pasos manuales de `mysql` de arriba: el contenedor crea la base, ejecuta el esquema y carga el seed por ti.

> Los contenedores se nombran `\<proyecto\>-db-1` / `\<proyecto\>-app-1` (el compose no fija `container\_name`), así no chocan con otros contenedores de tu máquina. Si ya habías levantado la app con una versión previa, limpia con `docker compose down -v` antes de volver a subir.

> Con Apache, el enrutamiento al Front Controller lo hace `public/.htaccess` (mod\_rewrite); en el servidor embebido de PHP lo hace el propio `public/index.php`.

### Datos de prueba (`seed.sql`)

- **10 usuarios** (`U001`…`U010`); `U010` está **inactivo** (para probar el rechazo).

- **4 técnicos** (1 = Carlos Ramírez, 2 = Laura Gómez, 3 = Andrés Pérez; el 4, Marta Díaz, está **inactivo**).

- **4 categorías**: 1 Hardware, 2 Software, 3 Red, 4 Otro.

- **25 tickets** repartidos en ABIERTO / EN CURSO / CERRADO, incluyendo vencidos y por vencer.

## 4. Endpoints

Base URL: `/api`. **Todos** exigen el encabezado `X-API-Key`.

| Método | Ruta | Descripción | OK | Errores |
| - | - | - | - | - |
| `GET` | `/api/tickets` | Lista paginada y filtrable (`estado`, `prioridad`, `tecnico`, `q`, `page`, `per\\\_page`). | `200` | `401`, `422` |
| `GET` | `/api/tickets/\\\{numero\\\}` | Detalle del ticket con su historial completo. | `200` | `401`, `404` |
| `POST` | `/api/tickets` | Crea un ticket. | `201` | `401`, `409`, `422` |
| `PATCH` | `/api/tickets/\\\{numero\\\}/estado` | Cambia el estado (`\\\{"estado", "observacion"\\\}`). | `200` | `401`, `404`, `409`, `422` |
| `PATCH` | `/api/tickets/\\\{numero\\\}/tecnico` | Reasigna técnico (`\\\{"tecnico\\\_id"\\\}`). | `200` | `401`, `404`, `409`, `422` |
| `GET` | `/api/usuarios/\\\{codigo\\\}` | Datos del solicitante por código. | `200` | `401`, `404` |
| `GET` | `/api/tecnicos` | Técnicos activos con su carga actual. | `200` | `401` |
| `GET` | `/api/resumen` | Métricas del tablero. | `200` | `401` |
| `GET` | `/api/tickets/export.csv` | Exporta a CSV los tickets filtrados (mismos filtros que la lista). | `200` | `401`, `422` |
| `GET` | `/api/categorias` | Catálogo de categorías. | `200` | `401` |


**Formato único de error** (sin excepciones, incluso 404/405/500):

```
\\\{ "error": \\\{ "code": "VALIDACION", "message": "Mensaje para el usuario", "fields": \\\{ "campo": "detalle" \\\} \\\} \\\}
```

Códigos: `NO\\\_AUTORIZADO` (401) · `NO\\\_ENCONTRADO` (404) · `METODO\\\_NO\\\_PERMITIDO` (405) · `TRANSICION\\\_INVALIDA` / `TECNICO\\\_AL\\\_TOPE` (409) · `VALIDACION` (422) · `ERROR\\\_INTERNO` (500).

## 5. Ejemplos con `curl`

Define tu clave una vez (usa la misma que pusiste en `config/config.php`):

```
MI\\\_CLAVE="tu-clave-aqui"                 \\\# reemplázala por tu clave real    
BASE="http://localhost:8080/api"
```

En cada `curl` de abajo se envía como `-H "X-API-Key: $MI\\\_CLAVE"`.

### Listar (paginado y con filtros)

```
curl -s -H "X-API-Key: $MI\\\_CLAVE" "$BASE/tickets?estado=EN%20CURSO&prioridad=Alta&page=1&per\\\_page=10"
```

### Ver el detalle con historial

```
curl -s -H "X-API-Key: $MI\\\_CLAVE" "$BASE/tickets/TK-2026-000001"
```

### Crear un ticket

```
curl -s -X POST -H "X-API-Key: $MI\\\_CLAVE" -H "Content-Type: application/json" \\\\    
  -d '\\\{"usuario\\\_codigo":"U001","categoria\\\_id":3,"descripcion":"La VPN corporativa se cae cada 10 minutos.","prioridad":"Alta","tecnico\\\_id":3\\\}' \\\\    
  "$BASE/tickets"
```

Respuesta `201`:

```
\\\{ "numero": "TK-2026-000026", "estado": "ABIERTO", "sla": "EN\\\_TIEMPO",    
  "creado": "2026-10-19T00:02:05-05:00", "vence": "2026-10-19T04:02:05-05:00", "historial": \\\[ … \\\] \\\}
```

### Cambiar de estado

```
\\\# ABIERTO -\\\> EN CURSO    
curl -s -X PATCH -H "X-API-Key: $MI\\\_CLAVE" -H "Content-Type: application/json" \\\\    
  -d '\\\{"estado":"EN CURSO"\\\}' "$BASE/tickets/TK-2026-000002/estado"    
    
\\\# EN CURSO -\\\> CERRADO (la observación es la solución aplicada)    
curl -s -X PATCH -H "X-API-Key: $MI\\\_CLAVE" -H "Content-Type: application/json" \\\\    
  -d '\\\{"estado":"CERRADO","observacion":"Se reinstaló el cliente de VPN y quedó operativo."\\\}' \\\\    
  "$BASE/tickets/TK-2026-000001/estado"
```

### Reasignar técnico

```
curl -s -X PATCH -H "X-API-Key: $MI\\\_CLAVE" -H "Content-Type: application/json" \\\\    
  -d '\\\{"tecnico\\\_id":2\\\}' "$BASE/tickets/TK-2026-000004/tecnico"
```

### Catálogos y tablero

```
curl -s -H "X-API-Key: $MI\\\_CLAVE" "$BASE/usuarios/U001"    
curl -s -H "X-API-Key: $MI\\\_CLAVE" "$BASE/tecnicos"    
curl -s -H "X-API-Key: $MI\\\_CLAVE" "$BASE/categorias"    
curl -s -H "X-API-Key: $MI\\\_CLAVE" "$BASE/resumen"
```

### Exportar a CSV (respeta los filtros)

```
curl -s -H "X-API-Key: $MI\\\_CLAVE" \\\\    
  "$BASE/tickets/export.csv?estado=EN%20CURSO&prioridad=Alta" -o tickets.csv
```

## 6. Pruebas de las reglas de negocio (manuales)

```
\\\# 401 sin clave    
curl -s -o /dev/null -w "%\\\{http\\\_code\\\}\\\\n" "$BASE/tickets"                 \\\# -\\\> 401    
    
\\\# 409: no se puede saltar de ABIERTO a CERRADO    
curl -s -X PATCH -H "X-API-Key: $MI\\\_CLAVE" -H "Content-Type: application/json" \\\\    
  -d '\\\{"estado":"CERRADO","observacion":"x"\\\}' "$BASE/tickets/TK-2026-000002/estado"    
    
\\\# 422: cerrar sin solución / descripción corta / usuario inactivo    
curl -s -X POST -H "X-API-Key: $MI\\\_CLAVE" -H "Content-Type: application/json" \\\\    
  -d '\\\{"usuario\\\_codigo":"U010","categoria\\\_id":1,"descripcion":"corta","prioridad":"Urgente","tecnico\\\_id":3\\\}' \\\\    
  "$BASE/tickets"    
    
\\\# 409 TECNICO\\\_AL\\\_TOPE: el técnico 1 ya tiene 5 tickets activos en el seed    
curl -s -X PATCH -H "X-API-Key: $MI\\\_CLAVE" -H "Content-Type: application/json" \\\\    
  -d '\\\{"tecnico\\\_id":1\\\}' "$BASE/tickets/TK-2026-000008/tecnico"
```

### Consecutivo seguro ante concurrencia (RN-01)

```
\\\# 20 POST en paralelo: nunca se repite el número    
for i in $(seq 1 20); do    
  curl -s -X POST -H "X-API-Key: $MI\\\_CLAVE" -H "Content-Type: application/json" \\\\    
    -d "\\\{\\\\"usuario\\\_codigo\\\\":\\\\"U00$(( (i % 9) + 1 ))\\\\",\\\\"categoria\\\_id\\\\":1,\\\\"descripcion\\\\":\\\\"Prueba de concurrencia número $i con texto suficiente.\\\\",\\\\"prioridad\\\\":\\\\"Media\\\\",\\\\"tecnico\\\_id\\\\":$(( (i % 4) + 1 ))\\\}" \\\\    
    "$BASE/tickets" \\\>\\\> /tmp/tickets.json &    
done; wait    
grep -o 'TK-2026-\\\[0-9\\\]\\\*' /tmp/tickets.json | sort | uniq -d   \\\# debe salir vacío
```

> El número de tickets que se creen depende del cupo libre (tope de 5 por técnico, RN-05). Para una corrida de 20 exitosos, libera cupo cerrando tickets activos o activando más técnicos.

## 7. Colección Postman / archivo `.http`

- **Postman:** importa `postman/Soporte-TI.postman\\\_collection.json` y selecciona el entorno `Local` (`postman/Soporte-TI.local.postman\\\_environment.json`). Define `api\\\_key` con tu clave.

- **VS Code (REST Client) / JetBrains:** abre `postman/soporte-ti.http` y ajusta las variables `@base\\\_url` y `@api\\\_key`.

Ambos incluyen los 9 endpoints y los casos de error (401, 404, 409, 422).

## 8. Estructura del proyecto

```
├── AGENTS.md              \\\# Constitución del proyecto (reglas para agentes/desarrolladores)    
├── README.md              \\\# Este archivo    
├── composer.json          \\\# Autoload PSR-4 (App\\\\ =\\\> src/) y PHPUnit    
├── config/                \\\# config.example.php (plantilla) / config.php (real, ignorado por Git)    
├── database/              \\\# schema.sql + seed.sql    
├── public/                \\\# ÚNICA carpeta pública: index.php (front controller), app.html (SPA), .htaccess, assets/    
├── src/    
│   ├── Controllers/       \\\# HTTP delgado (lee Request, llama Service, responde JSON)    
│   ├── Services/          \\\# Reglas de negocio y transacciones    
│   ├── Repositories/      \\\# ÚNICO lugar con SQL (PDO preparado)    
│   ├── Domain/            \\\# Clases puras: SlaCalculator, EstadoTicket, Constantes, CsvExporter    
│   ├── Http/              \\\# Router, Request, Response, Middlewares    
│   ├── Exceptions/        \\\# Excepciones de dominio con código HTTP    
│   └── Support/           \\\# Config, Database (PDO + transaction())    
├── specs/                 \\\# Spec-Driven Development (spec/plan/tasks por funcionalidad)    
├── tests/                 \\\# PHPUnit: Unit (dominio puro) e Integration (BD real)    
├── scripts/               \\\# guards.sh (barreras automáticas)    
├── docker/                \\\# entrypoint.sh (genera config.php desde el entorno)    
├── Dockerfile             \\\# Imagen PHP 8.1 + Apache con el docroot en public/    
├── docker-compose.yml     \\\# app + MySQL 8.0 (schema/seed como initdb)    
└── .env.example           \\\# Plantilla de variables de entorno para Docker (sin secretos)
```

## 9. Reglas de negocio implementadas

| ID | Regla | Dónde |
| - | - | - |
| RN-01 | Consecutivo `TK-AAAA-NNNNNN` seguro ante concurrencia (transacción + `FOR UPDATE` + `UNIQUE`) | `TicketService`, `TicketRepository` |
| RN-02 | El solicitante debe existir y estar activo | `UsuarioService`, `TicketService` |
| RN-03 | Descripción de 20 a 1000 caracteres (`trim`, `mb\\\_strlen`) | `TicketService::validarCreacion` |
| RN-04 | Categoría existente y prioridad ∈ \{Alta, Media, Baja\} | `TicketService` |
| RN-05 | Máximo 5 tickets activos por técnico (mismo técnico no cuenta) | `TicketService`, `Constantes::MAX\\\_TICKETS\\\_TECNICO` |
| RN-06 | Máquina de estados `ABIERTO ➔ EN CURSO ➔ CERRADO` | `Domain\\\\EstadoTicket` |
| RN-07 | No se reasigna un ticket cerrado | `TicketService::reasignarTecnico` |
| RN-08 | SLA: Alta +4 h, Media +24 h, Baja +72 h; VENCIDO/POR\_VENCER/EN\_TIEMPO | `Domain\\\\SlaCalculator` |
| RN-09 | Toda creación/cambio/reasignación deja historial en la misma transacción | `TicketRepository::insertarHistorial` |


## 10. Pruebas automatizadas (PHPUnit)

```
composer install          \\\# instala PHPUnit (dependencia de desarrollo)    
./vendor/bin/phpunit      \\\# o: composer test
```

La suite tiene dos grupos:

- **`Unit`** (dominio puro, sin base de datos): máquina de estados (RN-06), SLA (RN-08), tope de carga (RN-05) y el generador CSV.

- **`Integration`** (contra MySQL con `config/config.php`): flujo completo ABIERTO→EN CURSO→CERRADO con historial, 409 al saltar de estado, 422 al cerrar sin solución, 422 con usuario inactivo y verificación de que el **tope no quema el consecutivo** (ROLLBACK). Si no hay BD, este grupo se **omite** en vez de fallar.

Las pruebas de integración crean sus propios datos y los eliminan al terminar (no ensucian el `seed`).

## 11. Calidad y barreras automáticas

```
bash scripts/guards.sh
```

`guards.sh` falla si un PHP no compila, si se commitea `config/config.php` o hay secretos, si aparece HTML crudo (`innerHTML`), si hay SQL concatenado, si hay acceso a PDO fuera de `Repositories/Support` o si queda depuración olvidada.

## 12. Documentación (Doc-as-Code)

Toda la documentación vive en el repo y evoluciona con el código (AGENTS.md §11):

| Documento | Contenido |
| - | - |
| [`docs/00-vision-y-alcance.md`](file:///home/South_Knight/Descargas/Prueba_Tecnica_IT/tickets-soporte-ti/docs/00-vision-y-alcance.md) | Problema, visión, alcance del MVP, actores |
| [`docs/01-requisitos.md`](file:///home/South_Knight/Descargas/Prueba_Tecnica_IT/tickets-soporte-ti/docs/01-requisitos.md) | RF, RNF y RI con trazabilidad |
| [`docs/02-arquitectura.md`](file:///home/South_Knight/Descargas/Prueba_Tecnica_IT/tickets-soporte-ti/docs/02-arquitectura.md) | C4 (contexto, contenedores, componentes) |
| [`docs/03-modelo-datos.md`](file:///home/South_Knight/Descargas/Prueba_Tecnica_IT/tickets-soporte-ti/docs/03-modelo-datos.md) | Diagrama ER y diccionario de datos |
| [`docs/04-api.md`](file:///home/South_Knight/Descargas/Prueba_Tecnica_IT/tickets-soporte-ti/docs/04-api.md) · [`docs/openapi.yaml`](file:///home/South_Knight/Descargas/Prueba_Tecnica_IT/tickets-soporte-ti/docs/openapi.yaml) | Contrato REST (OpenAPI 3.0) |
| [`docs/05-reglas-negocio.md`](file:///home/South_Knight/Descargas/Prueba_Tecnica_IT/tickets-soporte-ti/docs/05-reglas-negocio.md) | RN-01…RN-09 y máquina de estados |
| [`docs/06-decisiones/`](file:///home/South_Knight/Descargas/Prueba_Tecnica_IT/tickets-soporte-ti/docs/06-decisiones/) | ADR-001…ADR-005 |
| [`docs/07-pruebas.md`](file:///home/South_Knight/Descargas/Prueba_Tecnica_IT/tickets-soporte-ti/docs/07-pruebas.md) | Matriz de pruebas y checklist de entrega |
| [`docs/diagrams/`](file:///home/South_Knight/Descargas/Prueba_Tecnica_IT/tickets-soporte-ti/docs/diagrams/) | Fuentes Mermaid (`.mmd`) y PlantUML (`.puml`) |
| [`specs/`](file:///home/South_Knight/Descargas/Prueba_Tecnica_IT/tickets-soporte-ti/specs/) | SDD: `spec.md` / `plan.md` / `tasks.md` por fase |
| [`docs/ai-log.md`](file:///home/South_Knight/Descargas/Prueba_Tecnica_IT/tickets-soporte-ti/docs/ai-log.md) | Bitácora de uso de IA |


## 13. Declaración de uso de IA

> Este proyecto se desarrolló con apoyo de herramientas de IA (OpenCode, GitHub Copilot y asistentes de chat) para generar y revisar código y documentación, siguiendo una metodología Spec-Driven con `AGENTS.md` como reglas. Todo el código fue leído, probado y entendido por el autor, quien puede explicarlo y modificarlo en vivo. La bitácora está en `docs/ai-log.md`.

## 14. Autor

Jose Manuel Muñoz Fernández — Desarrollador Junior Full-Stack y Aprendiz SENA en la Tecnología de Análisis y Desarrollo de Software en etapa productiva.

Prueba técnica — Aprendiz de Soporte TI, **MEGACOMERCIAL SAS**. Zona horaria: `America/Bogota`.

