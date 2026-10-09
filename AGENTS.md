# Project guidelines

Laravel 13 · PHP 8.4 · Livewire 4 · Flux (free) · Tailwind 4 · PHPUnit · Dusk · Pint.
Be concise. Create docs only if explicitly asked. Follow sibling files' structure, naming and conventions; reuse existing components. Do not add base folders or change dependencies without approval. Use descriptive names.
No speculative architecture: no repository, DTO, interface, service or domain layer, no new abstraction and no extra indirection without a concrete need; reuse what the resources already have. Ask before any of those. Full list in `.github/docs/review-rules.md` and `ARCHITECTURE.md`.

## Commands
Everything runs in Docker. `DX` = `docker compose exec -e XDEBUG_MODE=off laravel13`.
- Start: `docker compose up -d laravel13`. If `docker compose exec laravel13 ...` fails because the container will not stay up (a cron in the base image cannot drop privileges), run the command in a throwaway container instead: `docker compose run --rm --no-deps -e XDEBUG_MODE=off --entrypoint ./vendor/bin/phpunit laravel13 --testsuite Unit`.
- Artisan/Composer/PHPUnit: `DX php artisan ...`, `DX composer ...`. Always pass `--no-interaction`; create files with `DX php artisan make:*`.
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
- Reviewer agents have a single copy in `.github/agents/`; `.opencode/agents/` are gitignored symlinks to them. Edit the source, never the symlink. Never add a `tools:` key there (OpenCode drops the whole file). Rebuild the links with `bash /packages/basics13/tooling/link-opencode-assets.sh` from the application root.
- Guided flows (new-feature, new-resource, full-review, fix-review, fix-tests, consistency-review, ux-implement) are skills in `.github/skills/<name>/SKILL.md`, not `/commands`: activate them by skill, not with a slash command.
- Activate the matching skill in `.github/skills` for its domain. No verification scripts/tinker when tests cover it.

## PHP
- Chisel: `chisel.php` / `chisel-paths.php` strip unused code marked with `/* @chisel-... */` … `/* @end-chisel-... */`. Those markers are machine-read: never remove or reformat them, and never regenerate `chisel.php` unless asked.
- Curly braces always; constructor property promotion; explicit return and parameter types; Enum keys TitleCase.
- PHPDoc over inline comments (array shapes in PHPDoc).
- Named routes with `route()`. Models get factories (use existing states). Faker: follow existing style.
- After editing PHP: `DX ./vendor/bin/pint --dirty --format agent`. Never `--test` while writing code: it only reports. A read-only review may use `--test`, which is the correct check there because it must not rewrite files.

## Livewire
State server-side; validate and authorize in actions. Alpine is already bundled.

## Tests (PHPUnit)
- Add/update tests for behavior changes (not for copy/styling). Cover the change and its key failures only. Read the `testing-best-practices` skill first.
- Create: `DX php artisan make:test --phpunit Name` (feature; `--unit` for unit; no suite dir in name).
- Run the narrowest: `DX php artisan test --compact <path|--filter=name>`; add `--parallel` for local speed (never in CI — shared DB) with flags before the path: `DX php artisan test --parallel --compact <path>`. Rerun after each fix.
- Before you call a change done, run the 4 architecture tests: `DX php artisan test --compact tests/Unit/ArchitectureTest tests/Unit/ValidationCoverageTest tests/Unit/ModelSchemaParityTest tests/Unit/ResourceUniformityTest`. They guard the per-resource set, validated input, model↔schema parity and cross-resource uniformity.
- CI enforces at least 80% total code coverage and uploads a Clover report; run `docker compose run --rm laravel13-phpunit` locally to check the same threshold.
- Static analysis: run `DX ./vendor/bin/phpstan analyse` (level 9).

### Test Location Rules (apply on EVERY test creation/modification)
**Decide where the test belongs BEFORE writing it:**

| Test Type | Location | DB? | Trait |
|-----------|----------|-----|-------|
| Pure logic (rules, policies, helpers, middleware config, query structure, translations) | `tests/Unit/...` | ❌ No | none |
| Validation rules (FormRequest `rules()`, `authorize()`, `prepareForValidation()`) | `tests/Unit/Validation/*RequestTest.php` | ❌ No | none |
| Policy methods (`viewAny`, `create`, `update`, etc.) | `tests/Unit/Policies/*PolicyTest.php` | ❌ No | none |
| Query builders (`ListQueryBase`, search patterns, state counts) | `tests/Unit/Queries/*ListQueryTest.php` | ❌ No | none |
| HTTP integration (full request/response, view rendering) | `tests/Feature/...` | ✅ Yes | `RefreshDatabase` |
| Read-light Feature tests (auth pages, query tests with 1 create, middleware) | `tests/Feature/...` | ✅ Yes (lazy) | `LazilyRefreshDatabase` |

**Decision checklist for new tests:**
1. Can it run without touching the database? → `tests/Unit/`
2. Does it only validate rules/authorize/prepareForValidation? → `tests/Unit/Validation/`
3. Does it test a Policy method directly? → `tests/Unit/Policies/`
4. Does it test query structure (constants, method signatures, search patterns)? → `tests/Unit/Queries/`
4. Does it need HTTP + DB (CRUD, Trash, ListQuery with real data)? → `tests/Feature/` + `RefreshDatabase`
5. Does it only create 0-1 records and mostly reads? → `tests/Feature/` + `LazilyRefreshDatabase`

**Refactoring existing tests:** When touching a Feature test that only does pure logic, move it to Unit. When touching a Feature test with few creates, consider `LazilyRefreshDatabase`.
