#!/usr/bin/env bash
# Instala los hooks de Git del proyecto:
#   pre-commit : ejecuta scripts/guards.sh
#   commit-msg : exige Conventional Commits (feat:, fix:, docs:, ...)
# Uso (una sola vez, desde la raíz del repo):  bash scripts/install-hooks.sh
set -eu
root="$(git rev-parse --show-toplevel)"
hooks="$root/.git/hooks"

cat > "$hooks/pre-commit" <<'EOF'
#!/usr/bin/env bash
exec bash "$(git rev-parse --show-toplevel)/scripts/guards.sh"
EOF

cat > "$hooks/commit-msg" <<'EOF'
#!/usr/bin/env bash
first_line="$(head -n1 "$1")"
# Permite commits de merge/revert generados por Git
case "$first_line" in Merge*|Revert*) exit 0 ;; esac
if ! echo "$first_line" | grep -qE '^(feat|fix|docs|chore|test|refactor|style|perf|ci|build)(\([a-z0-9_-]+\))?: .{3,}'; then
  echo "[X] Mensaje de commit inválido: '$first_line'" >&2
  echo "    Formato: tipo(alcance opcional): descripción   Ej: feat(api): crear ticket con consecutivo seguro" >&2
  exit 1
fi
EOF

chmod +x "$hooks/pre-commit" "$hooks/commit-msg"
echo "Hooks instalados en $hooks (pre-commit y commit-msg)."
