---
description: Scaffold a complete CRUD resource following the canonical Customer pattern
argument-hint: Resource singular, StudlyCase (e.g. Invoice)
---

# NEW RESOURCE

Resource: $ARGUMENTS (ask if empty). Build the full CRUD set mirroring `Customer` — the canonical resource — never a different structure.

1. Read `AGENTS.md`, `.github/instructions/*.instructions.md` and `.github/docs/architecture/ARCHITECTURE.md`.
2. TDD first: write failing Feature tests mirroring `tests/Feature/Customers/{CustomerCrudTest,CustomerInputValidationTest,CustomerTrashTest,CustomerAccessTest}` (+ unit tests). Run them narrowest-first.
3. Generate the complete set:
   - migration (unique-among-active index, FKs) + `Database\Factories\XFactory` with existing states
   - `App\Models\X`: `#[Fillable]`, `#[UsePolicy]`, `casts()`, typed relationships, soft deletes
   - `XRequest` + `XListRequest` (rules + `authorize()` via policy), `XPolicy`
   - `XController` + `XTrashController`, `App\Queries\Xs\XListQuery`, `App\Transformers\XListTransformer`
   - routes; `resources/views/xs/{list,form}.blade.php` using `<x-list.table>` and `<x-forms.tracked-resource>`; `lang/*.json` in all 4 locales (default `eu`)
4. Validate everything the user sends: every store/update field has a FormRequest rule; catch `QueryException` → `UniqueConstraintViolation::rethrowAsValidationError()`; never read raw input in controllers.
5. Run and report real results: `DX ./vendor/bin/pint --dirty --format agent`, the new tests, `DX composer types:check`, and `DX php artisan test --compact tests/Unit/ArchitectureTest tests/Unit/ValidationCoverageTest tests/Unit/ModelSchemaParityTest`. Never claim a pass without running it.
6. Ask before adding dependencies, base folders or abstractions.
