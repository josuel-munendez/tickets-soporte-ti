#!/usr/bin/env bash
# =====================================================================
# guards.sh — barreras automáticas del proyecto (ver AGENTS.md §11)
# Uso:   bash scripts/guards.sh            (rápido, se usa en pre-commit)
#        GUARDS_FULL=1 bash scripts/guards.sh   (además valida OpenAPI)
# Sale con código 1 si algún guard falla. Es una red de seguridad:
# no reemplaza leer el código. Para ignorar una línea revisada a mano,
# agrega el comentario  guard:allow  en esa línea.
# =====================================================================
set -u
cd "$(git rev-parse --show-toplevel 2>/dev/null || pwd)"

fail=0
err() { echo "  [X] GUARD: $*" >&2; fail=1; }
ok()  { echo "  [ok] $*"; }
skip(){ echo "  [--] $* (omitido)"; }

echo "== Guards =="

# 1. Secretos: config.php nunca versionado -----------------------------
if git ls-files --error-unmatch config/config.php >/dev/null 2>&1; then
  err "config/config.php está versionado; quítalo con: git rm --cached config/config.php"
else
  ok "config/config.php no está versionado"
fi
if [ -f .gitignore ] && grep -qE '(^|/)config/config\.php$|^config\.php$' .gitignore; then
  ok ".gitignore protege config.php"
else
  err ".gitignore no incluye config/config.php"
fi

# 2. Secretos: búsqueda básica (gitleaks si existe) ---------------------
if command -v gitleaks >/dev/null 2>&1; then
  gitleaks detect --no-banner --redact >/dev/null 2>&1 && ok "gitleaks sin hallazgos" || err "gitleaks encontró posibles secretos"
else
  hits=$(grep -rnIE "(API_KEY|api_key|password|passwd|DB_PASS)['\"]?[[:space:]]*(=>|=|:)[[:space:]]*['\"][^'\"]{8,}['\"]" \
        --include='*.php' --include='*.js' --include='*.json' --include='*.http' --include='*.md' --include='*.yml' --include='*.yaml' \
        --exclude-dir=vendor --exclude-dir=node_modules --exclude-dir=.git --exclude=config.php . 2>/dev/null \
        | grep -viE "example|changeme|tu_|your_|xxxx|guard:allow|placeholder|<[a-z_ -]+>|=>[[:space:]]*\\$|=[[:space:]]*\\$" || true)
  if [ -n "$hits" ]; then err "posible secreto en el código:"; echo "$hits" >&2; else ok "sin secretos evidentes (instala gitleaks para mejor cobertura)"; fi
fi

# 3. PHP: sintaxis ---------------------------------------------------------
if command -v php >/dev/null 2>&1; then
  bad=0
  while IFS= read -r f; do
    php -l "$f" >/dev/null 2>&1 || { err "sintaxis PHP inválida: $f"; bad=1; }
  done < <(find src public config tests -name '*.php' 2>/dev/null)
  [ "$bad" -eq 0 ] && ok "sintaxis PHP correcta"
else
  skip "php no instalado"
fi

# 4. PHP: análisis estático y estilo (si Composer ya instaló las herramientas)
if [ -x vendor/bin/phpstan ] && [ -f phpstan.neon ]; then
  vendor/bin/phpstan analyse --no-progress --memory-limit=512M >/dev/null 2>&1 && ok "phpstan sin errores" || err "phpstan reporta errores (ejecuta: vendor/bin/phpstan analyse)"
else
  skip "phpstan (aún no configurado)"
fi
if [ -x vendor/bin/php-cs-fixer ]; then
  vendor/bin/php-cs-fixer fix --dry-run --diff --using-cache=no >/dev/null 2>&1 && ok "estilo PSR-12 correcto" || err "estilo: ejecuta vendor/bin/php-cs-fixer fix"
else
  skip "php-cs-fixer (aún no instalado)"
fi

# 5. XSS: innerHTML prohibido salvo líneas revisadas ----------------------
if [ -d public ]; then
  hits=$(grep -rnI "innerHTML" public --include='*.js' --include='*.html' 2>/dev/null | grep -v "guard:allow" || true)
  if [ -n "$hits" ]; then err "innerHTML detectado (usa textContent o escapeHtml):"; echo "$hits" >&2; else ok "sin innerHTML sin revisar"; fi
fi

# 6. SQL: sin variables interpoladas ni concatenadas en src/ --------------
if [ -d src ]; then
  hits=$( { grep -rnE "\"[^\"]*(SELECT|INSERT|UPDATE|DELETE)[^\"]*\\$" src --include='*.php' ;
            grep -rnE "(SELECT|INSERT|UPDATE|DELETE)[^;]*['\"][[:space:]]*\\.[[:space:]]*\\$" src --include='*.php' ; } 2>/dev/null \
          | grep -v "guard:allow" | sort -u || true)
  if [ -n "$hits" ]; then err "posible SQL con variables (usa sentencias preparadas):"; echo "$hits" >&2; else ok "sin SQL interpolado"; fi
fi

# 7. Capas: SQL/PDO solo en Repositories y Support ------------------------
if [ -d src ]; then
  hits=$(grep -rnE "(->prepare\(|->query\(|new PDO|->exec\()" src --include='*.php' \
         | grep -vE "^src/(Repositories|Support)/" | grep -v "guard:allow" || true)
  if [ -n "$hits" ]; then err "acceso a datos fuera de Repositories/Support:"; echo "$hits" >&2; else ok "SQL confinado a Repositories"; fi
fi

# 8. Errores: nada de var_dump/print_r/dd en código --------------------------
if [ -d src ] || [ -d public ]; then
  hits=$(grep -rnE "\b(var_dump|print_r|dd)\(" src public --include='*.php' 2>/dev/null | grep -v "guard:allow" || true)
  if [ -n "$hits" ]; then err "depuración olvidada:"; echo "$hits" >&2; else ok "sin depuración olvidada"; fi
fi

# 9. OpenAPI (solo con GUARDS_FULL=1) --------------------------------------
if [ "${GUARDS_FULL:-0}" = "1" ] && [ -f docs/openapi.yaml ]; then
  if command -v npx >/dev/null 2>&1; then
    npx --yes @stoplight/spectral-cli lint docs/openapi.yaml >/dev/null 2>&1 && ok "openapi.yaml válido" || err "openapi.yaml con errores (npx @stoplight/spectral-cli lint docs/openapi.yaml)"
  else
    skip "npx no instalado"
  fi
fi

echo "=============="
if [ "$fail" -eq 0 ]; then echo "Guards: TODO OK"; else echo "Guards: FALLÓ (corrige y vuelve a intentar)" >&2; fi
exit "$fail"
