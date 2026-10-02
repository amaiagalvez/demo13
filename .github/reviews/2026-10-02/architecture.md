# Architecture Review — 2026-10-02

Architecture must stay proportional to the application (`.github/docs/review-rules.md:269-283`).

## Findings

### ARCH-001 — `ARCHITECTURE.md` describes a test setup that does not exist

Severity: MEDIUM
Category: Architecture / Documentation drift
File: .github/docs/architecture/ARCHITECTURE.md
Line: 50-51
Confidence: HIGH

Problem: the document states the database section as:

> Engine: MySQL-compatible MariaDB in Docker; SQLite is used for isolated in-memory tests.

The suite does not run on SQLite. `phpunit.xml:19-20` pins `DB_CONNECTION=mysql` /
`DB_DATABASE=laravel_test`, and running it on SQLite fails (7 tests — see `testing.md` TEST-001).

Impact: this is the document the project tells every agent to read first. A reader (human or
agent) who trusts the SQLite claim will (a) assume the `sqlite`/`pgsql` migration branch in the
three domain migrations is covered, and (b) not notice that `ListQueryBase`'s `LIKE` escaping is
MySQL-only.
Recommendation: correct the sentence to name MariaDB as the only supported test engine, or make
the claim true (fix the SQLite branch and add a job). The first is a one-line documentation fix.

### ARCH-002 — The `active` flag is a display-only concern modelled as a schema-wide column

Severity: INFO
Category: Architecture
File: database/migrations/2026_10_02_180040_add_active_to_domain_and_users_tables.php
Line: 15-21
Confidence: HIGH

Observation, not a defect: `active` is added to `customers`, `projects`, `epics` **and** `users`,
yet it means two different things — "this login is allowed" (users) and "this record is shown in
the active list" (domain records). The `users` column is load-bearing for authentication
(`FortifyServiceProvider`, `EnsureUserIsActive`); the domain columns only drive list filtering and
the deactivate/reactivate UI, and ARCHITECTURE.md explicitly says deactivation does not cascade.

Impact: none today. Flagged so that a future feature which starts treating "inactive" as
"unassignable" knows that the two columns have independent semantics and that the current
implementation deliberately lets a customer be deactivated while its projects stay active
(`ResourceActivationTest:409-424`). No change recommended.

## Verified clean (no findings)

- **Layering is correct and enforced.** Controllers never touch `DB::`, `Schema::`, `dd()`,
  `->paginate()` or raw input — `tests/Unit/ArchitectureTest.php:62-86` fails the build otherwise,
  and it passes.
- **Every resource ships the same set of classes**, verified by
  `ArchitectureTest::test_every_resource_ships_the_complete_set`.
- **Authorization boundary is a policy, not a controller check.** All three policies exist and are
  bound via `#[UsePolicy]`.
- **Listing is split into Query + Transformer**, exactly as
  `ARCHITECTURE.md:100-103` requires, and enforced by `ArchitectureTest::test_views_follow_the_canonical_patterns`.
- **Deliberately rejected patterns are respected**: no repository/service/DTO/CQRS/event-sourcing
  layer anywhere in `app/`.
- **Business logic is findable**: uniqueness, deletion guards and the trash-name conflict flow all
  live in Form Requests + controllers + one small support class, discoverable from
  `.github/instructions/http.instructions.md`.
- **`bootstrap/app.php` is minimal** — one middleware, one exception-rendering tweak.
- **No service container abuse.** Providers are the stock Laravel/Fortify wiring.