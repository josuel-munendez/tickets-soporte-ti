# Spec — Fase 6: SPA (interfaz web)

> **Estado:** En implementación
> **Reglas:** AGENTS.md §7 (frontend), §4.5 (XSS), §4.3 (API key), §2 (stack sin frameworks).

## Alcance
Una sola página `public/app.html` que consume la API por `fetch` (sin recargas), con:
tablero de resumen, tabla filtrable y paginada, formulario de registro y modal de detalle
con cambio de estado y reasignación. JS ES6 vanilla en módulos `utils/api/ui/tabla/form`.

## Criterios de aceptación (Dado/Cuando/Entonces)
- **CA-1** Dado que se abre la SPA, cuando carga, entonces ve el tablero (`/api/resumen`) y la
  primera página de la tabla (`/api/tickets?page=1&per_page=10`), sin recargar la página.
- **CA-2 (§7)** Dados los filtros (estado, prioridad, técnico, texto libre), cuando cambian, entonces la
  tabla se recarga desde el servidor (paginación de servidor, 10 por página) y se reinicia a la página 1.
- **CA-3 (§7)** Dado el input de usuario, cuando se escribe (debounce ~400 ms), entonces se consulta
  `/api/usuarios/{codigo}` y se muestra nombre, cargo y área; si es 404/inactivo, se deshabilita "Registrar".
- **CA-4 (§7)** Dado el formulario, cuando se abre, entonces categorías y técnicos vienen de la API
  (nunca del HTML); el técnico de MENOR carga queda preseleccionado y los del tope aparecen deshabilitados.
- **CA-5 (§7)** Dada una fila, cuando se hace clic, entonces se abre el detalle con el historial; desde ahí
  se puede cambiar de estado (con observación al cerrar) y reasignar; al terminar se refrescan tabla y tablero.
- **CA-6 (§7)** Dado un error de la API, entonces se muestra `error.message` y se mapean los `fields` a los
  inputs; nunca se vuelca JSON crudo.
- **CA-7 (§4.5)** Dados datos de la API/usuario, cuando se pintan, entonces se usan `createElement`/`textContent`
  (helper `Utils.h`): cero HTML crudo y cero `innerHTML`.
- **CA-8 (§4.3)** Dado cada request a `/api/*`, entonces lleva `X-API-Key`. La clave se sirve inyectada por
  PHP desde `config/config.php` (no versionada); si la página se abre como archivo estático, la SPA la pide
  por pantalla y la recuerda en `localStorage`.

## Decisiones de UX
- Sin dependencias de CDN (Bootstrap opcional): CSS propio para funcionar sin internet.
- Modales con el elemento nativo `<dialog>`; insignias de color por estado/prioridad/SLA.
