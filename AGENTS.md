# Project guidelines

Laravel 13 · PHP 8.4 · Livewire 4 · Flux (free) · Tailwind 4 · PHPUnit · Dusk · Pint.
Be concise. Create docs only if explicitly asked. Follow sibling files' structure, naming and conventions; reuse existing components. Do not add base folders or change dependencies without approval. Use descriptive names.

## Commands
Everything runs in Docker. `DX` = `docker compose exec -e XDEBUG_MODE=off laravel13`.
- Start: `docker compose up -d laravel13`
- Artisan/Composer/PHPUnit: `DX php artisan ...`, `DX composer ...`. Always pass `--no-interaction`; create files with `DX php artisan make:*`.
- npm: `docker compose run --rm --no-deps --entrypoint npm laravel13-npm <cmd>` (e.g. `run build`). Vite error "Unable to locate file in Vite manifest" or UI not updating: run build, or ask the user to start dev (`-p 5173:5173 ... run dev -- --host 0.0.0.0`).
- Package versions: `DX composer show --direct`, or package.json. Use APIs of the installed major version.

## Project map
Customers → Projects → Epics (+ epic comments). Soft deletes, trash list, restore, force delete. Names unique among active records (DB-enforced).
- Per resource: `XController` + `XTrashController`, `app/Http/Requests`, `app/Policies`, `app/Queries/*/XListQuery`, `app/Transformers`.
- Views: `resources/views/{customers,projects,epics}/{list,form}.blade.php`; shared parts in `resources/views/components/list/`.
- Duplicate-name races: `App\Support\Database\UniqueConstraintViolation`.
- i18n: `lang/*.json`, 4 locales, parity checked by a unit test.
- Tests: `tests/Feature`, `tests/Unit`, Dusk in `tests/Browser`.
- Structural changes: read `.github/docs/architecture/ARCHITECTURE.md` first.

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
