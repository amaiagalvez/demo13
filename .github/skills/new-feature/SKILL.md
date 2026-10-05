---
name: new-feature
description: Implement a feature end to end with tests, Pint and build. Use for behavior that is not a whole CRUD resource (a new action, filter, column or email). For a complete resource with model, migration, controller, policy, views and tests, use new-resource instead.
disable-model-invocation: true
argument-hint: describe the feature
---
# NEW FEATURE

Implement the feature the user describes in this Laravel 13 · Livewire 4 · Flux · Tailwind app.

1. Read `AGENTS.md`. Read `.github/docs/architecture/ARCHITECTURE.md` first for structural changes. Read the `.github/instructions/*.instructions.md` files whose `applyTo` matches the paths you will touch.
2. Plan briefly: files to create/change, validation, authorization, i18n keys, test cases. Ask if the scope is ambiguous.
3. Write failing Feature tests first (`DX php artisan make:test --phpunit Name`), run the narrowest (`DX php artisan test --compact <path|--filter=name>`).
4. Implement the smallest change following sibling-file conventions: `XController` + `XTrashController`, Form Request, policy, `app/Queries/*/XListQuery`, `app/Transformers`. Reuse `resources/views/components/list/*` and use Flux components where one exists; keep controllers thin.
5. Every user-facing string goes in `lang/*.json` for all 4 locales (same key and `:placeholders`, default `eu`). Keep existing `data-test` names; row actions pass the edit payload via `data-payload`.
6. After PHP edits: `DX ./vendor/bin/pint --dirty --format agent`. Rerun the touched tests after each fix.
7. If views or `resources/js` changed: `docker compose run --rm --no-deps --entrypoint npm laravel13-npm run build`.
8. Finish with a short report: files changed, tests added, commands executed and their real results. Never claim a test passed unless it ran.

Before adding a dependency, base folder or new abstraction (repository, DTO, service layer), ask the user: the architecture rejects them without a concrete need. Use `search-docs` before version-specific Laravel APIs.
