# 02 — Arquitectura

> **Metodología:** modelo C4 (contexto → contenedores → componentes). Diagramas en Mermaid
> (texto versionable). Fuente en [`diagrams/`](diagrams/).

## 1. Nivel 1 — Contexto

```mermaid
flowchart LR
    agente["Agente de Soporte TI<br/>(navegador)"]
    api["Sistema de Tickets<br/>de Soporte TI"]
    db[("Base de datos<br/>MySQL 8.0")]
    cliente["Cliente de API<br/>(curl / Postman)"]

    agente -->|"HTTPS · SPA + fetch"| api
    cliente -->|"HTTPS · JSON + X-API-Key"| api
    api -->|"PDO · SQL preparado"| db
```

El sistema es autónomo: no depende de servicios externos. Los usuarios interactúan por la SPA;
otros consumidores usan la API directamente.

## 2. Nivel 2 — Contenedores

```mermaid
flowchart TB
    subgraph navegador["Navegador"]
        spa["SPA (app.html + ES6)<br/>tabla, tablero, formulario"]
    end

    subgraph servidor["Servidor PHP 8.1"]
        front["Front Controller<br/>public/index.php"]
        router["Router + Middlewares"]
        controllers["Controllers"]
        services["Services"]
        repos["Repositories (PDO)"]
        domain["Domain (clases puras)"]
    end

    db[("MySQL 8.0<br/>InnoDB · utf8mb4")]

    spa -->|"fetch + X-API-Key"| front
    front --> router --> controllers --> services
    services --> repos -->|"SQL preparado"| db
    services --> domain
    front -.->|"inyecta API key"| spa
```

| Contenedor | Responsabilidad | Tecnología |
| --- | --- | --- |
| **SPA** | Interfaz de una página; consume la API por `fetch` | HTML5 + JS ES6 vanilla + CSS propio |
| **Front Controller** | Único punto de entrada; sirve la SPA e inyecta la API key; ruta `/api/*` | `public/index.php` |
| **Router + Middlewares** | Resuelve rutas, aplica la API key, responde 404/405 | PHP |
| **Controllers / Services / Repositories / Domain** | Capas de la aplicación | PHP 8.1 |
| **Base de datos** | Persistencia relacional | MySQL 8.0 (InnoDB, utf8mb4) |

## 3. Nivel 3 — Componentes (lado servidor)

```mermaid
flowchart TB
    subgraph http["Http"]
        router["Router"]
        request["Request"]
        response["Response"]
        apikey["ApiKeyMiddleware"]
    end

    subgraph ctrl["Controllers (delgados)"]
        cTicket["TicketController"]
        cUsuario["UsuarioController"]
        cTecnico["TecnicoController"]
        cCategoria["CategoriaController"]
    end

    subgraph svc["Services (negocio, sin SQL ni HTTP)"]
        sTicket["TicketService"]
        sUsuario["UsuarioService"]
        sTecnico["TecnicoService"]
        sCategoria["CategoriaService"]
    end

    subgraph repo["Repositories (único lugar con SQL)"]
        rTicket["TicketRepository"]
        rUsuario["UsuarioRepository"]
        rTecnico["TecnicoRepository"]
        rCategoria["CategoriaRepository"]
    end

    subgraph dom["Domain (puro)"]
        sla["SlaCalculator"]
        estado["EstadoTicket"]
        const["Constantes"]
        csv["CsvExporter"]
    end

    support["Support<br/>Config · Database (PDO + transaction())"]
    exc["Exceptions<br/>Api/Validacion/Conflicto/NoEncontrado"]

    router --> cTicket & cUsuario & cTecnico & cCategoria
    router --> apikey
    cTicket --> sTicket --> rTicket
    cUsuario --> sUsuario --> rUsuario
    cTecnico --> sTecnico --> rTecnico
    cCategoria --> sCategoria --> rCategoria
    sTicket --> sla & estado & const & csv
    sTicket --> support
    sTicket -.-> exc
```

### Reglas de dependencia (AGENTS.md §3)

- **Controller:** delgado. Cero SQL, cero lógica de negocio; traduce Request → Service → Response.
- **Service:** no conoce HTTP (`$_GET`, `$_POST`) ni ejecuta SQL; **orquesta transacciones** y reglas.
- **Repository:** el **único** lugar con SQL y PDO (verificado por `guards.sh`).
- **Domain:** sin dependencias externas; lógica pura (transiciones, SLA, CSV).

## 4. Flujo de una petición

```mermaid
sequenceDiagram
    participant N as Navegador (SPA)
    participant F as Front Controller
    participant M as ApiKeyMiddleware
    participant C as TicketController
    participant S as TicketService
    participant R as TicketRepository
    participant D as MySQL

    N->>F: POST /api/tickets (X-API-Key, JSON)
    F->>M: valida la clave (hash_equals)
    alt clave inválida
        M-->>N: 401 NO_AUTORIZADO
    end
    M->>C: Request autenticado
    C->>S: crear(datos)
    S->>S: valida RN-02/03/04
    S->>D: BEGIN · bloquear técnico · tope (RN-05)
    S->>R: siguienteConsecutivo (RN-01)
    S->>R: insertar ticket + historial (RN-09)
    S->>D: COMMIT
    S-->>C: detalle del ticket
    C-->>N: 201 Created (JSON + SLA)
```

## 5. Decisiones de arquitectura

Ver los registros completos en [`06-decisiones/`](06-decisiones/):

| ADR | Decisión |
| --- | --- |
| [ADR-001](06-decisiones/ADR-001-php-nativo-sin-framework.md) | PHP nativo en capas, sin framework |
| [ADR-002](06-decisiones/ADR-002-consecutivo-seguro.md) | Consecutivo anual con transacción y `FOR UPDATE` |
| [ADR-003](06-decisiones/ADR-003-api-key-middleware.md) | API key por middleware (`hash_equals`, fail-closed) |
| [ADR-004](06-decisiones/ADR-004-spa-vanilla-sin-cdn.md) | SPA vanilla sin framework ni CDN |
| [ADR-005](06-decisiones/ADR-005-reglas-como-datos.md) | Transiciones y SLA como datos del dominio |

## 6. Despliegue

- **Desarrollo:** servidor embebido `php -S localhost:8080 -t public public/index.php`.
- **Contenedores:** `docker compose up` (Apache + PHP 8.1 y MySQL 8.0); ver [`README.md`](../README.md).
- **Producción:** cualquier servidor con PHP 8.1+ y MySQL 8.0+; la **única** carpeta expuesta es `public/`
  (con `public/.htaccess` reescribiendo al Front Controller).
