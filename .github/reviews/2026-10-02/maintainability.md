# Maintainability Specialist Review — 2026-10-02

The two-year test: can a new developer maintain this, find the business rules, and change something without a
regression nobody notices?

Snapshot: HEAD `781ccbb` + working tree.

## MAINT-001 — The business rules live in five places, and only some of them are executable

Severity: MEDIUM
Category: Maintainability / Knowledge location
File: .github/docs/architecture/ARCHITECTURE.md
Line: 48-70
Confidence: HIGH

Problem:

For the single most important invariant in the app — "names are unique among non-deleted records, including
inactive ones, and soft-deleted names may be reused" — a developer must consult **five** distinct artefacts:

| Location | What it holds |
|---|---|
| `ARCHITECTURE.md:60-63` | the rule in prose |
| `database/migrations/2026_*.php:33-40` | the rule in DDL (generated column) |
| `app/Http/Requests/*Request.php` | `Rule::unique(...)->whereNull('deleted_at')` — 3 copies |
| `app/Support/Database/UniqueConstraintViolation.php` | the race absorber |
| `tests/Feature/*/{Customer,Project,Epic}CrudTest.php` + `*TrashTest.php` | 9 race tests |

The same fan-out applies to "cannot delete a parent that has children" (prose + 4 controllers + 2 tests) and to the
`active` flag (migration + 3 models + 2 queries + 6 controllers + 3 transformers + views + 2 tests).

Evidence: the tables above are built from the files read in this review; the nine race tests are listed in
`tests/` as `CustomerCrudTest.php:159,176`, `CustomerTrashTest.php:108`, `ProjectCrudTest.php:114,136`,
`ProjectTrashTest.php:145`, `EpicCrudTest.php:175,195`, `EpicTrashTest.php:159`.

Impact:

This is not a defect — it is the honest cost of putting rules in the database, the request layer and the tests, which
is the *right* design. The risk is drift: if someone changes one of the five, only some of the tests fail, and the
prose in `ARCHITECTURE.md` silently becomes wrong. The `active` flag has already drifted this way — see BUS-001,
where two of the five places (the two select queries) forgot the flag.

Recommendation:

The strongest existing guard is already in place: `tests/Unit/ArchitectureTest.php:29-59` requires every resource to
ship the complete set of nine classes, and `tests/Unit/ModelSchemaParityTest.php` ties model attributes to schema.
Add one more *declarative* guard rather than more documentation: a test that asserts, for each of the three domain
models, that the Form Request's unique rule carries `whereNull('deleted_at')` and that the migration contains a
generated column. That converts the invariant from prose into an assertion, which is what `ARCHITECTURE.md:117`
("Record patterns that should not be introduced without a concrete new requirement") already gestures at.

This is P2. Not urgent — the rule is currently correct in all five places.

## MAINT-002 — `ARCHITECTURE.md` is accurate and current except for the two drifts recorded below

Severity: INFO (positive finding, with two exceptions)
Category: Maintainability / Documentation
File: .github/docs/architecture/ARCHITECTURE.md
Line: 1-140
Confidence: HIGH

Problem:

None, apart from the two items below. This is an unusually good architecture document for a project this size:
concise (140 lines), with named sections for System Purpose, Domains, Auth, Database, Queues, Frontend, External
Services, Architectural Decisions, Deliberately Rejected Patterns, Production Assumptions and Known Technical Debt.

Evidence:

- Every one of the 12 business rules I extracted was implemented and tested (see `.github/reviews/2026-10-02/business.md`).
- The "Deliberately Rejected Patterns" section (`:118-124`) is actively load-bearing — it is why this review rejects
  repositories, services, DTOs, traits and base models instead of recommending them.
- "Known Technical Debt" (`:137-140`) honestly records that the Dusk CI job "must be observed once in GitHub Actions
  to confirm the runner's Chrome/ChromeDriver paths".

Two drifts:

1. `:50-51` claims SQLite is used for isolated in-memory tests; `phpunit.xml:22-25` pins MySQL. → **ARCH-001**.
2. `:26-27` describes epic comments as "added from the epic edit drawer" with no mention that they are now loaded
   over a JSON sub-resource (the working-tree change). → update when that change lands.

Impact:

Low. Both are documentation-only.

Recommendation:

Fix (1) now (one sentence). Fix (2) as part of the in-flight change.

## MAINT-003 — The zone instruction files are short, specific and enforced

Severity: INFO (positive finding)
Category: Maintainability / Developer experience
File: .github/instructions/
Line: —
Confidence: HIGH

Problem:

None.

Evidence:

Four files (`http`, `models`, `tests`, `views`), each 5-9 bullets, each naming concrete classes:

- `models.instructions.md`: "A new column needs three things together: migration, `#[Fillable]`/`casts()` entry and
  Request rules — architecture tests enforce it." — and the tests do enforce it (`ModelSchemaParityTest`,
  `ValidationCoverageTest`).
- `http.instructions.md`: "Store/update: catch `QueryException` and call
  `UniqueConstraintViolation::rethrowAsValidationError()`." — a rule that maps 1:1 onto the code.
- `views.instructions.md`: "Every resource form MUST wrap its content in `<x-forms.tracked-resource>`" — and
  `ArchitectureTest.php:123-140` asserts exactly that for all three resources.

Impact:

None. New contributors get the rules automatically (they are `applyTo`-scoped), and the tests catch violations.

Recommendation:

None. Keep them this short. They are the right size.

## MAINT-004 — `AGENTS.md` states the phpstan level as 9, which matches `phpstan.neon`

Severity: INFO (verified, recorded because it looked like a contradiction)
Category: Maintainability / Documentation
File: AGENTS.md
Line: 43
Confidence: HIGH

Problem:

None — I initially suspected a drift and checked.

Evidence:

```
AGENTS.md:43   - Static analysis: run `DX ./vendor/bin/phpstan analyse` (level 9).
phpstan.neon:15  level: 9
```

They agree. `phpstan.neon` includes `vendor/larastan/larastan/extension.neon` and
`vendor/nesbot/carbon/extension.neon`, and analyses `app/ bootstrap/app.php config/ database/ resources/ routes/ tests/`.

Impact:

None.

Recommendation:

None. **Rejected as a finding.**

## MAINT-005 — The test suite has no seed data / demo fixtures, so a fresh clone has an empty UI

Severity: LOW
Category: Maintainability / Onboarding
File: database/seeders/DatabaseSeeder.php
Line: —
Confidence: MEDIUM

Problem:

`DatabaseSeeder` is the stock skeleton seeder (creates one test user), and the live development database contains
hand-typed rows (`Bezero1`, `Bezero 2`, `galvez`, `itarte`, `galvez itarte`, `project1`, `project2`) that exist only
because someone typed them into the running container.

Evidence:

- `ls database/seeders/` → `CustomerSeeder.php`, `DatabaseSeeder.php`.
- Live data: `SELECT id, name FROM customers` returns exactly those five hand-entered names, one of them
  (`Bezero 2`) deactivated and one project attached to it — i.e. the state was produced by clicking through the UI,
  not by a seeder.
- `.env:20` points the app at `DB_DATABASE=laravel`, and `docker-compose.yml:208` persists `./dc-data/mysql`, so this
  state survives container restarts — but not a `migrate:fresh`.

Impact:

A new developer (or a wiped volume) gets an empty application with no example of a customer→project→epic chain, and
no epic comments, which is the feature with the most moving parts. `CustomerSeeder` exists but is not referenced by
`DatabaseSeeder` in a way that produces this.

Recommendation:

Optionally extend the existing `CustomerSeeder` to create one customer with a project and an epic (factories already
exist and are well-formed: `CustomerFactory`, `ProjectFactory`, `EpicFactory`, `EpicCommentFactory`). This is optional —
the factories are the real documentation for the schema, and `AGENTS.md` says not to add things unasked. Recorded as
P3 / optional.

## Notes / not findings (rejected after devil's-advocate challenge)

- **No `.env.example` guidance for the test database.** `.env.example:19-23` documents `laravel`; the test database is
  created by `docker/mysql/init/01-create-test-database.sql` and referenced only in `phpunit.xml:25`. A comment would
  help, but the init SQL is self-documenting. **Rejected.**
- **No CONTRIBUTING.md / changelog.** Single-developer project with `AGENTS.md` + `.github/instructions/` + a review
  pack. Adding process documents is not warranted. **Rejected.**
- **`chisel.php` / `chisel-paths.php` (274 + ~40 lines) are starter-kit scaffolding shipped in the repo.** They are
  build-time only and `composer.json:74-80` deletes them in the `apply` step. Slightly odd to keep them, but they
  are the documented mechanism for toggling auth features. **Rejected.**
- **`composer.json` has no `dev` safety net for the `dev` script's `queue:listen`.** `composer.json:59-63` starts
  `php artisan queue:listen --tries=1 --timeout=0` even though no jobs exist. Harmless. **Rejected.**
- **The list-view duplication (MAINT-001 in the 2026-09-30 review).** Reported as CLEAN-001, not duplicated here.