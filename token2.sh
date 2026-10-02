#!/usr/bin/env bash
# Ejecutar desde la raíz del repo DESPUÉS de token-diet.sh: bash agent-config-extras.sh
set -euo pipefail

# 1) Xdebug apagado en los comandos del agente
sed -i 's#`DX` = `docker compose exec laravel13`#`DX` = `docker compose exec -e XDEBUG_MODE=off laravel13`#' AGENTS.md
grep -q 'XDEBUG_MODE=off' AGENTS.md || { echo "No se pudo editar el prefijo DX en AGENTS.md"; exit 1; }

# 2) Mapa del proyecto en AGENTS.md (antes de la sección Boost / docs)
cat > /tmp/project-map.md <<'EOF'
## Project map
Customers → Projects → Epics (+ epic comments). Soft deletes, trash list, restore, force delete. Names unique among active records (DB-enforced).
- Per resource: `XController` + `XTrashController`, `app/Http/Requests`, `app/Policies`, `app/Queries/*/XListQuery`, `app/Transformers`.
- Views: `resources/views/{customers,projects,epics}/{list,form}.blade.php`; shared parts in `resources/views/components/list/`.
- Duplicate-name races: `App\Support\Database\UniqueConstraintViolation`.
- i18n: `lang/*.json`, 4 locales, parity checked by a unit test.
- Tests: `tests/Feature`, `tests/Unit`, Dusk in `tests/Browser`.
- Structural changes: read `.github/docs/architecture/ARCHITECTURE.md` first.

EOF
awk -v f=/tmp/project-map.md '/^## Boost \/ docs/ { while ((getline l < f) > 0) print l } { print }' AGENTS.md > AGENTS.md.new
mv AGENTS.md.new AGENTS.md

# 3) .github/rules (se cargan solo al tocar esas rutas)
mkdir -p .github/rules

cat > .github/rules/index.md <<'EOF'
# Rules index
Read the file whose glob matches the path you edit.

| Glob | File |
| --- | --- |
| `app/Http/**`, `app/Policies/**`, `app/Queries/**`, `app/Transformers/**` | http.md |
| `resources/views/**`, `lang/**`, `resources/js/**` | views.md |
| `tests/**` | tests.md |
EOF

cat > .github/rules/http.md <<'EOF'
# HTTP / domain rules
- Store/update: catch `QueryException` and call `UniqueConstraintViolation::rethrowAsValidationError()`. Restore flows return their own conflict response instead.
- Deleting or force-deleting a parent is blocked if children exist, including trashed ones (`withTrashed()->exists()`). Customer→Project and Project→Epic work the same way.
- Form Requests authorize through the resource policy. Policies currently allow every verified user; this is documented in ARCHITECTURE.md, do not add roles unasked.
- List querying lives in `app/Queries`, row shaping in `app/Transformers`; keep controllers thin.
EOF

cat > .github/rules/views.md <<'EOF'
# View / i18n rules
- List views reuse `resources/views/components/list/*`; do not copy their markup back into a view. Keep existing `data-test` names; row actions pass the edit payload via `data-payload`.
- Use Flux components where one exists.
- Any new user-facing string goes in `lang/*.json` for all 4 locales (same key and `:placeholders`); a unit test checks parity. Default locale is `eu`.
EOF

cat > .github/rules/tests.md <<'EOF'
# Test rules
- Prefer Feature tests; Dusk (`tests/Browser`) only for JS behavior.
- Duplicate-insert races are tested per resource for store, update and restore by simulating a duplicate-key `QueryException`.
- Dusk runs against `laravel_test` with a non-English default locale, so assert on translated text.
EOF

# 4) Búsqueda del editor sin carpetas pesadas
cat > .vscode/settings.json <<'EOF'
{
    "php.validate.executablePath": "/home/amaia/Mahaigaina/l13/demo13/.vscode/php-docker",
    "Laravel.phpEnvironment": "docker",
    "Laravel.phpCommand": [
        "/usr/bin/docker",
        "compose",
        "exec",
        "-T",
        "laravel13",
        "php"
    ],
    "LaravelExtraIntellisense.phpCommand": "/usr/bin/docker compose exec -T -e XDEBUG_MODE=off laravel13 php -r \"{code}\"",
    "LaravelExtraIntellisense.basePathForCode": "/docker",
    "chat.tools.terminal.autoApprove": {
        "/^docker compose exec -T laravel13 vendor/bin/pint --dirty --format agent$/": {
            "approve": true,
            "matchCommandLine": true
        }
    },
    "search.exclude": {
        "**/dc-data": true,
        "**/node_modules": true,
        "**/vendor": true,
        "public/build": true,
        "storage/framework": true,
        "storage/logs": true
    },
    "files.watcherExclude": {
        "**/dc-data/**": true,
        "**/node_modules/**": true,
        "**/vendor/**": true
    }
}
EOF
