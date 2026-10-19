# Plan — Fase 6: SPA (interfaz web)

> **Spec:** `specs/006-spa/spec.md`

## Archivos
```text
public/app.html                  # única página (tablero, filtros, tabla, 2 <dialog>)
public/assets/css/estilos.css    # UI sin CDN
public/assets/js/utils.js        # helper h(), escapeHtml, debounce, formatearFecha
public/assets/js/api.js          # ÚNICO punto de fetch + X-API-Key + formato de error
public/assets/js/ui.js           # tablero, detalle, insignias, avisos, catálogos cacheados
public/assets/js/tabla.js        # filtros + paginación de servidor
public/assets/js/form.js         # debounce de usuario, validación, creación y mapeo de fields
public/index.php                 # sirve / y /app.html inyectando la API key desde config
```

## Decisiones y alternativas descartadas
- **D1 — Módulos por responsabilidad, no un bundle.** Separa `api/ui/tabla/form/utils` (§7) como IIFEs que
  exponen un único global (`Api`, `Ui`, `Tabla`, `Formulario`, `Utils`). *Descartado:* un `app.js` monolítico
  o un bundler (prohibido introducir toolchain; no hay build en el MVP).
- **D2 — La API key se inyecta en el HTML servido por PHP.** `index.php` intercepta `/` y `/app.html`, lee
  `config/config.php` (no versionado) y reemplaza el marcador `__CLAVE_API__`. Así el secreto NO queda en Git.
  Si el HTML se sirve como estático (Apache/nginx), la SPA detecta el marcador y pide la clave por pantalla
  (guardada en `localStorage`). *Descartado:* claves embebidas en el JS versionado (serían un secreto filtrado).
- **D3 — DOM seguro con `Utils.h`.** Todo nodo se crea con `createElement` y texto con `textContent`; el guard
  §12 prohíbe `innerHTML`, así que la construcción de la tabla y los modales pasa por este helper.
- **D4 — Paginación y filtros 100% en servidor.** El cliente solo manda `page/per_page/filtros`; el backend ya
  aplica la lista blanca y la validación (§5, fase 3). El cliente no ordena ni recorta en memoria.
- **D5 — `<dialog>` nativo + CSS propio.** Sin framework ni CDN: la SPA funciona sin internet y sin build.

## Punto de defensa
`api.js` es el único lugar con `fetch`; `ui.js` es el único que arma nodos con datos externos; el resto son
controladores finos. Cambiar el contrato de un endpoint se reduce a `api.js`.
