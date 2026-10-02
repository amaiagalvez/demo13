#!/usr/bin/env bash
# Ejecutar desde la raíz del repo: bash token-diet.sh
set -euo pipefail

git switch -c chore/reduce-agent-tokens

# 1) Las reglas de revisión salen del fichero que Copilot carga siempre
git mv .github/copilot-instructions.md .github/docs/review-rules.md
grep -rl '\.github/copilot-instructions\.md' .github \
  | xargs sed -i 's#\.github/copilot-instructions\.md#.github/docs/review-rules.md#g'

cat > .github/copilot-instructions.md <<'EOF'
Follow `AGENTS.md`.
Only when reviewing code or fixing review findings, read `.github/docs/review-rules.md`.
EOF

# 2) AGENTS.md compacto (prefijo docker definido una sola vez)
cat > AGENTS.md <<'EOF'
# Project guidelines

Laravel 13 · PHP 8.4 · Livewire 4 · Flux (free) · Tailwind 4 · PHPUnit · Dusk · Pint.
Be concise. Create docs only if explicitly asked. Follow sibling files' structure, naming and conventions; reuse existing components. Do not add base folders or change dependencies without approval. Use descriptive names.

## Commands
Everything runs in Docker. `DX` = `docker compose exec laravel13`.
- Start: `docker compose up -d laravel13`
- Artisan/Composer/PHPUnit: `DX php artisan ...`, `DX composer ...`. Always pass `--no-interaction`; create files with `DX php artisan make:*`.
- npm: `docker compose run --rm --no-deps --entrypoint npm laravel13-npm <cmd>` (e.g. `run build`). Vite error "Unable to locate file in Vite manifest" or UI not updating: run build, or ask the user to start dev (`-p 5173:5173 ... run dev -- --host 0.0.0.0`).
- Package versions: `DX composer show --direct`, or package.json. Use APIs of the installed major version.

## Boost / docs
- Prefer Boost MCP tools over shell: `database-query` (read-only), `database-schema`, `get-absolute-url`, `browser-logs`, `search-docs`.
- `search-docs` before version-specific Laravel-ecosystem APIs (skip for copy/styling). Broad topic queries, no package names in the query, scope with `packages`. Reuse earlier results.
- If `.github/rules/index.md` exists, read the rule files matching the paths you edit first. Use `record-rule` only when the user explicitly asks.
- Activate the matching skill in `.github/skills` for its domain. No verification scripts/tinker when tests cover it.

## PHP
- Curly braces always; constructor property promotion; explicit return and parameter types; Enum keys TitleCase.
- PHPDoc over inline comments (array shapes in PHPDoc).
- Named routes with `route()`. Models get factories (use existing states). Faker: follow existing style.
- After editing PHP: `DX ./vendor/bin/pint --dirty --format agent` (never `--test`).

## Livewire
State server-side; validate and authorize in actions. Alpine is already bundled.

## Tests (PHPUnit)
- Add/update tests for behavior changes (not for copy/styling). Cover the change and its key failures only. Read the `testing-best-practices` skill first.
- Create: `DX php artisan make:test --phpunit Name` (feature; `--unit` for unit; no suite dir in name).
- Run the narrowest: `DX php artisan test --compact <path|--filter=name>`; rerun after each fix.
EOF

# 3) boost.json: AGENTS.md pasa a ser manual y se quitan skills de uso puntual
cat > boost.json <<'EOF'
{
    "agents": [
        "copilot"
    ],
    "cloud": false,
    "guidelines": false,
    "mcp": true,
    "nightwatch": false,
    "packages": [
        "livewire/blaze"
    ],
    "sail": false,
    "skills": [
        "fortify-development",
        "laravel-best-practices",
        "testing-best-practices",
        "fluxui-development",
        "livewire-development",
        "tailwindcss-development"
    ]
}
EOF

git rm -r -q .github/skills/infer-conventions .github/skills/blaze-optimize

git add AGENTS.md boost.json .github
git commit -q -m "chore: reduce agent context tokens

- Compact AGENTS.md (docker prefix defined once, drop unused sections)
- Move review rules out of always-loaded copilot-instructions.md
- Stop regenerating AGENTS.md via Boost (guidelines=false)
- Remove one-off skills (infer-conventions, blaze-optimize)"

git --no-pager show --stat HEAD