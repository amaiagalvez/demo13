# Project guidelines

Laravel 13 · PHP 8.4 · Livewire 4 · Flux (free) · Tailwind 4 · PHPUnit · Dusk · Pint.
Be concise. Create docs only if explicitly asked. Follow sibling files' structure, naming and conventions; reuse existing components. Do not add base folders or change dependencies without approval. Use descriptive names.

## Commands
Everything runs in Docker. `DX` = `docker compose exec -e XDEBUG_MODE=off laravel13`.
- Start: `docker compose up -d laravel13`
- Artisan/Composer/PHPUnit: `DX php artisan ...`, `DX composer ...`. Always pass `--no-interaction`; create files with `DX php artisan make:*`.
- Dusk: `docker compose exec -e XDEBUG_MODE=off laravel13-dusk php artisan dusk [tests/Browser/...php]`. Run Dusk in its dedicated service so both the test runner and browser server use `laravel_test` and an isolated config cache.
- npm: `docker compose run --rm --no-deps --entrypoint npm laravel13-npm <cmd>` (e.g. `run build`). Vite error "Unable to locate file in Vite manifest" or UI not updating: run build, or ask the user to start dev (`-p 5173:5173 ... run dev -- --host 0.0.0.0`).
- Package versions: `DX composer show --direct`, or package.json. Use APIs of the installed major version.

## Project map
Customers → Projects → Epics (+ epic comments). Soft deletes, trash list, restore, force delete. `active` flag with its own list plus deactivate/reactivate. Names unique among active records (DB-enforced).
- Per resource: `XController` + `XTrashController` + `XInactiveController` (extends the shared `InactiveController`), `app/Http/Requests`, `app/Policies`, `app/Queries/*/XListQuery`, `app/Transformers`.
- Views: `resources/views/{customers,projects,epics}/{list,form}.blade.php`; shared parts in `resources/views/components/list/`.
- Duplicate-name races: `App\Support\Database\UniqueConstraintViolation`.
- i18n: `lang/*.json`, 4 locales, parity checked by a unit test.
- Tests: `tests/Feature`, `tests/Unit`, Dusk in `tests/Browser`.
- Structural changes: read `.github/docs/architecture/ARCHITECTURE.md` first.

## Boost / docs
- Prefer Boost MCP tools over shell: `database-query` (read-only), `database-schema`, `get-absolute-url`, `browser-logs`, `search-docs`.
- `search-docs` before version-specific Laravel-ecosystem APIs (skip for copy/styling). Broad topic queries, no package names in the query, scope with `packages`. Reuse earlier results.
- Zone rules live in `.github/instructions/*.instructions.md` (`models`, `http`, `views`, `tests`): Copilot injects them automatically by `applyTo`; any other agent reads the one matching the paths it edits before touching them.
- Reviewer agents have a single copy in `.github/agents/`; `.opencode/agents/` are gitignored symlinks to them. Edit the source, never the symlink. Never add a `tools:` key there (OpenCode drops the whole file). Rebuild the links with `docker/link-opencode-assets.sh`.
- Guided flows (new-feature, new-resource, full-review, fix-review, fix-tests, consistency-review, ux-implement) are skills in `.github/skills/<name>/SKILL.md`, not `/commands`: activate them by skill, not with a slash command.
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
- Run the narrowest: `DX php artisan test --compact <path|--filter=name>`; add `--parallel` for local speed (never in CI — shared DB) with flags before the path: `DX php artisan test --parallel --compact <path>`. Rerun after each fix.
- CI enforces at least 80% total code coverage and uploads a Clover report; run `docker compose run --rm laravel13-phpunit` locally to check the same threshold.
- Static analysis: run `DX ./vendor/bin/phpstan analyse` (level 9).
