#!/usr/bin/env bash
# =====================================================================
# Renderiza los diagramas (Diagram-as-Code) a imágenes PNG y SVG.
#
#   bash scripts/render-diagramas.sh
#
# Salida: docs/diagrams/img/*.png y *.svg
#
# Requisitos:
#   • plantuml  (necesita Java)              -> diagramas .puml
#   • node/npx + @mermaid-js/mermaid-cli     -> diagramas .mmd
#   • Un navegador basado en Chromium para Mermaid (Chrome, Chromium, Brave, Edge…)
#
# La ruta del navegador se detecta automáticamente o se indica con CHROME_BIN:
#   CHROME_BIN=/usr/bin/brave-browser bash scripts/render-diagramas.sh
# =====================================================================
set -euo pipefail

RAIZ="$(cd "$(dirname "$0")/.." && pwd)"
DIAGRAMAS="$RAIZ/docs/diagrams"
SALIDA="$DIAGRAMAS/img"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

mkdir -p "$SALIDA"

echo "== Diagramas -> $SALIDA =="

# --- 1. PlantUML (.puml) -> PNG + SVG ---------------------------------------
if command -v plantuml >/dev/null 2>&1; then
    echo "-- PlantUML"
    for f in "$DIAGRAMAS"/*.puml; do
        [ -e "$f" ] || continue
        base="$(basename "$f" .puml)"
        plantuml -tpng -o "$SALIDA" "$f"
        plantuml -tsvg -o "$SALIDA" "$f"
        echo "   $base.png / $base.svg"
    done
else
    echo "!! plantuml no está instalado: se omite .puml (ver docs/diagrams/README.md)"
fi

# --- 2. Mermaid (.mmd) -> PNG + SVG -----------------------------------------
# El CLI de Mermaid NO entiende los comentarios '#' de las fuentes; se filtran.
if command -v npx >/dev/null 2>&1; then
    echo "-- Mermaid"

    # Navegador Chromium para Puppeteer (que usa mermaid-cli).
    NAVEGADOR="${CHROME_BIN:-}"
    if [ -z "$NAVEGADOR" ]; then
        for c in google-chrome-stable google-chrome chromium chromium-browser brave-browser-stable brave-browser microsoft-edge; do
            if command -v "$c" >/dev/null 2>&1; then NAVEGADOR="$(command -v "$c")"; break; fi
        done
    fi
    PUPPETEER_CONF=""
    if [ -n "$NAVEGADOR" ]; then
        PUPPETEER_CONF="$TMP/puppeteer.json"
        cat > "$PUPPETEER_CONF" <<JSON
{ "executablePath": "$NAVEGADOR", "headless": "new",
  "args": ["--no-sandbox", "--disable-setuid-sandbox", "--disable-dev-shm-usage", "--disable-gpu"] }
JSON
        echo "   navegador: $NAVEGADOR"
    fi

    MM_OPCIONES=(-y @mermaid-js/mermaid-cli@latest -b white)
    [ -n "$PUPPETEER_CONF" ] && MM_OPCIONES+=(-p "$PUPPETEER_CONF")

    for f in "$DIAGRAMAS"/*.mmd; do
        [ -e "$f" ] || continue
        base="$(basename "$f" .mmd)"
        # Si existe un .puml con el mismo nombre, ya se renderizó (evita pisarlo).
        if [ -e "$DIAGRAMAS/$base.puml" ]; then
            echo "   $base.mmd omitido (existe $base.puml)"
            continue
        fi
        limpio="$TMP/$base.mmd"
        grep -v '^[[:space:]]*#' "$f" > "$limpio"
        npx "${MM_OPCIONES[@]}" -i "$limpio" -o "$SALIDA/$base.png" -s 2
        npx "${MM_OPCIONES[@]}" -i "$limpio" -o "$SALIDA/$base.svg"
        echo "   $base.png / $base.svg"
    done
else
    echo "!! npx no está disponible: se omite .mmd (ver docs/diagrams/README.md)"
fi

echo "== Listo =="
ls -la "$SALIDA"
