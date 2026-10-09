---
name: new-resource
description: Scaffold a full CRUD resource following the canonical Customer pattern. Use when the request is a whole new entity (a model, migration, controllers, policy, list, form, trash and tests, e.g. "CRUD de facturas"). For a single change inside an existing resource, use new-feature instead.
disable-model-invocation: true
argument-hint: Resource singular, StudlyCase (e.g. Invoice)
---
# NEW RESOURCE

Resource: $ARGUMENTS (ask if empty). Build the full CRUD set mirroring `Customer` — the canonical resource — never a different structure.

1. Read `AGENTS.md`, `.github/instructions/*.instructions.md` and `.github/docs/architecture/ARCHITECTURE.md`.
2. TDD first: write failing Feature tests mirroring `tests/Feature/Customers/{CustomerCrudTest,CustomerInputValidationTest,CustomerTrashTest}.php` and the shared `tests/Feature/{ResourceAccessTest,ResourceWriteAccessTest,ResourceActivationTest}.php` (+ unit tests). Run them narrowest-first.
3. Generate the complete set:
   - migration (unique-among-active index, FKs) + `Database\Factories\XFactory` with existing states
   - `App\Models\X`: `#[Fillable]`, `#[UsePolicy]`, `casts()`, typed relationships, soft deletes
   - `XRequest` + `XListRequest` (rules + `authorize()` via policy), `XPolicy`
   - `XController` + `XTrashController` + `XInactiveController` (extends `App\Http\Controllers\InactiveController`), `App\Queries\Xs\XListQuery`, `App\Transformers\XListTransformer`
   - routes: the resource routes plus the trash ones and `xs/archived`, `x/deactivate`, `xs/archived/{x}/reactivate`; `resources/views/xs/{list,form}.blade.php` using `<x-list.table>` and `<x-forms.tracked-resource>` (each list renders its tabs through `<x-list.tabs>`, fed by `XListTransformer`); `lang/*.json` in all 4 locales (default `eu`)
4. Validate everything the user sends: every store/update field has a FormRequest rule; catch `QueryException` → `UniqueConstraintViolation::rethrowAsValidationError()`; never read raw input in controllers.
5. Run and report real results: `DX ./vendor/bin/pint --dirty --format agent`, the new tests, `DX composer types:check`, and the 4 architecture tests from `AGENTS.md` ("Before you call a change done"). They are the ones that catch a resource that drifts from the canonical set, so a green new test suite is not enough. Never claim a pass without running it.
6. Ask before adding dependencies, base folders or abstractions.
