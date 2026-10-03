# Architecture Specialist Review — 2026-10-02

Specification of record: `.github/docs/architecture/ARCHITECTURE.md`.
Snapshot: HEAD `781ccbb` + working tree.

The architecture here is small, explicit and mostly well matched to the problem. The findings below are about the
boundary between code and specification, plus one place where the layering is genuinely being crossed.

## ARCH-001 — `ARCHITECTURE.md` contradicts `phpunit.xml` about the test database

Severity: MEDIUM
Category: Architecture / Documentation drift
File: .github/docs/architecture/ARCHITECTURE.md
Line: 50-51
Confidence: HIGH

Problem:

The architecture document states the SQLite/MySQL split backwards:

```
## Database

Engine: MySQL-compatible MariaDB in Docker; SQLite is used for isolated in-memory tests.
```

The test suite does **not** use SQLite. `phpunit.xml:22-25` pins the MySQL connection explicitly:

```xml
<env name="DB_CONNECTION" value="mysql"/>
<env name="DB_HOST" value="db"/>
<env name="DB_PORT" value="3306"/>
<env name="DB_DATABASE" value="laravel_test"/>
```

and `docker/mysql/init/01-create-test-database.sql` creates that MariaDB database:
`CREATE DATABASE IF NOT EXISTS laravel_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`

Evidence: quoted above. Verified by running the suite: `docker compose exec laravel13 php artisan test --compact`
passes 238 tests against MariaDB 11.7.2, and `SELECT VERSION()` returns `11.7.2-MariaDB-ubu2404`.

Impact:

The SQLite branch in the three create-table migrations
(`2026_09_25_000000_create_customers_table.php:26`, and the two siblings) is therefore **never executed by the test
suite** — which is exactly the divergence reported in DB-001 (case-insensitive vs. binary collation). The
documentation is what makes that divergence invisible: a reader believes the SQLite path is covered.

Impact, concretely: someone changing the SQLite branch gets no test signal at all, and believes they do.

Recommendation:

Fix the sentence to match reality:

> Engine: MySQL-compatible MariaDB in Docker, for both development and tests (`laravel_test`). The migrations keep a
> SQLite/PostgreSQL branch for the unique-name indexes, but the suite runs against MariaDB only.

## ARCH-002 — The `active` flag is a soft-delete-adjacent concern spread across models, controllers, queries, transformers, requests and views with no single owner

Severity: MEDIUM
Category: Architecture / Cohesion
File: app/Models/Customer.php
Line: 26-40
Confidence: MEDIUM

Problem:

The `active` flag touches at least seven layers and there is no single place that states what it means:

| Layer | Location | What it does |
|---|---|---|
| Migration | `2026_10_02_180040_add_active...php:16-28` | adds the column to 4 tables |
| Model | `Customer.php:26-40`, `Project.php:28-43`, `Epic.php:27-42` | `$attributes = ['active' => true]` + cast |
| Query | `CustomerListQuery.php:22,36` | `where('active', true)` / `where('active', false)` |
| Query | `ProjectController.php:28`, `EpicController.php:28` | **does not filter on it** (see BUS-001) |
| Controller | `*InactiveController.php` | `deactivate` / `reactivate` |
| Transformer | `*ListTransformer.php` | `inactiveUrl`, `extraDate`, restore copy |
| Request | none — `active` is absent from every Form Request's rules | |

Evidence: the table above is built from the files read; note the last row is empty. Because `active` is not in
`#[Fillable]` on any model (`Customer.php:18`, `Project.php:19`, `Epic.php:18`) and not in `User.php:32`, mass
assignment cannot reach it — verified by `tests/Feature/Auth/InactiveUserTest.php:16-32` and by
`tests/Unit/ModelSchemaParityTest.php:28-42` listing `'active'` in `SYSTEM_COLUMNS`.

Impact:

The design is *safe* (the flag cannot be tampered with) but *implicit*: the meaning "active" versus "not soft-deleted"
is expressed only in query methods named `active()`/`inactive()`/`trashed()`. That is why BUS-001 was possible — the
controller that builds the parent dropdown simply did not think of the flag.

Recommendation:

No abstraction. The minimal fix is documentation: add one sentence to `ARCHITECTURE.md`'s Database section stating
the invariant that `active` is orthogonal to `deleted_at` (a record can be inactive and not deleted, inactive and
deleted, etc.) and that `active` is never mass-assignable. Then fix BUS-001. Resist the temptation to introduce a
`HasActive` trait — three models and one boolean do not justify it, and the instructions forbid speculative
abstractions.

## ARCH-003 — `app/Queries` + `app/Transformers` split is a real boundary and is respected

Severity: INFO (positive finding — recorded so a future "simplification" does not collapse it)
Category: Architecture
File: app/Queries/Customers/CustomerListQuery.php
Line: 14
Confidence: HIGH

Problem:

None. This is the architectural decision that works best in the codebase and should be defended.

Evidence:

- `ARCHITECTURE.md:102-103` — "Keep customer and project list querying and presentation transformation separate from
  controllers."
- Enforced in practice: `CustomerController.php:18-30` is 13 lines that call `$query->active($search)` and
  `$transformer->active($customers, $search)` and hand the result to a view. No `where`, no `orderBy`, no array
  shaping in the controller.
- `tests/Unit/ArchitectureTest.php:62-86` machine-enforces it: controllers must not contain `DB::`, `Schema::`,
  `->paginate(`, raw `$request->input()`, or a base-`Request` type-hint.
- `tests/Unit/ArchitectureTest.php:29-59` additionally enforces that every resource ships the complete set
  (Controller, TrashController, Request, ListRequest, RestoreRequest, Policy, ListQuery, ListTransformer, Factory),
  so a new resource cannot half-copy the structure.

Impact:

None. The test suite is what makes this durable rather than aspirational.

Recommendation:

None. Keep as-is.

## ARCH-004 — The `ARCHITECTURE.md` "Deliberately Rejected Patterns" section is doing its job and should stay

Severity: INFO (positive finding)
Category: Architecture
File: .github/docs/architecture/ARCHITECTURE.md
Line: 118-124
Confidence: HIGH

Problem:

None.

Evidence:

```
## Deliberately Rejected Patterns
- No repository, service, DTO, domain-layer, CQRS, or event-sourcing abstraction is
  justified by the current application size.
```

This is consistent with `.github/docs/review-rules.md:29-42`, which lists the same prohibitions, and with the actual
code: `app/` contains no repository interface, no service class, no DTO and no event.

Impact:

None. It is why this review rejects several plausible-looking refactors (see CLEAN-003, ARCH-002).

Recommendation:

None. This is the most valuable paragraph in the document — keep it updated when a rejected pattern is actually
declined in a code review.

## ARCH-005 — `config/` omits `view.php`, `hashing.php`, `auth.php` password config, `cors.php`, `session.php` partials

Severity: INFO
Category: Architecture / Configuration
File: config/
Line: —
Confidence: HIGH

Problem:

None. Recorded so the absence is not read as an oversight. This project ships a deliberately minimal `config/`
directory.

Evidence: `ls config/` returns exactly `app.php auth.php cache.php database.php filesystems.php fortify.php
logging.php mail.php queue.php services.php session.php` — no `view.php`, `hashing.php`, `broadcasting.php`,
`cors.php`, `sanctum.php`. All are optional in Laravel 11+ and have framework defaults. Notably `fortify.php`
**is** published (because it is heavily customised: features, limiters, passkeys) and `session.php` **is** published
(cookie hardening).

Impact:

None.

Recommendation:

None. Do not add config files "for completeness".

## Notes / not findings (rejected after devil's-advocate challenge)

- **`app/Queries/ListQueryBase` as an abstract base class.** A shared abstract parent for three query objects is a
  reasonable, minimal reuse (one `paginate()` with search escaping). It is not the "layered architecture" the rules
  prohibit. **Rejected.**
- **`app/Transformers` naming.** "Transformer" suggests a different pattern (e.g. Fractal/league transformers), but
  these are plain classes returning arrays, injected via the container, with no external dependency. The name is a
  mild misnomer but the design is sound and renaming it would churn 9 files for no benefit. **Rejected.**
- **Fortify's `Login` event listener in `FortifyServiceProvider.php:62-81` doing auth work.** Unconventional place for
  it, but Fortify offers no dedicated hook for "reject a login by post-check", and moving it to a listener class
  would be an unnecessary refactor. **Rejected.**
- **`config/database.php` uses `'strict' => true` while the docker db runs `--sql-mode=""`.** A genuine inconsistency
  but it is DevOps configuration, not architecture; recorded in devops.md.