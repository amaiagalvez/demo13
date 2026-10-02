# Testing Review — 2026-10-02

## Checks actually executed

```
docker compose exec -T -e XDEBUG_MODE=off -e DB_DATABASE=laravel_review laravel13 php artisan test --compact
  → Tests: 1 failed, 236 passed (1201 assertions), 22.27s

docker compose exec -T -e XDEBUG_MODE=off laravel13 ./vendor/bin/pint --test
  → PASS 128 files

docker compose exec -T -e XDEBUG_MODE=off laravel13 ./vendor/bin/phpstan analyse --no-progress
  → [OK] No errors   (level 9, larastan)

docker compose exec -T -e XDEBUG_MODE=off laravel13 composer audit --no-interaction
  → No security vulnerability advisories found.

npm audit --omit=dev   → found 0 vulnerabilities
npm ls --depth=0       → dependency tree OK
```

`php artisan test` was pointed at an isolated MariaDB database (`laravel_review`) created
specifically for this review, so the project's `laravel` and `laravel_test` data was untouched.
Dusk was **not** run — see the consolidated report.

## Findings

### TEST-001 — The suite is red: `EpicCommentTest` fails because list controllers never return a View

Severity: HIGH
Category: Testing / Bugs
File: tests/Feature/Epics/EpicCommentTest.php
Line: 77
Confidence: HIGH

Problem: `$response->viewData('list')` throws because the response body is a rendered string.

Evidence:

```console
$ php artisan test --compact --filter=EpicCommentTest
FAILED  Tests\Feature\Epics\EpicCommentTest > list embeds only the most recent comments…
  The response is not a view.
  at tests/Feature/Epics/EpicCommentTest.php:77
```

Root cause and fix are in `laravel.md` LAR-001: `View::fragmentIf()` returns a string in both
branches, so `viewData()`/`assertViewHas()` can never work on a list route.

Impact: `composer ci:check` (the CI entry point) fails, so the pipeline is red on any code change.
The test also never reaches `assertCount($limit, $comments)` at line 119, so the comment-limit
behaviour is currently unverified (it *is* correct — verified manually, see `database.md`).
Recommendation: restore the `View` return for non-fragment requests (LAR-001). Do **not** rewrite
the test to scrape HTML; the assertion is a legitimate view-data assertion.

### TEST-002 — `phpunit.xml` and CI disagree about the test database, and no command creates `laravel_test`

Severity: MEDIUM
Category: Testing / CI
File: phpunit.xml, .github/workflows/tests.yml
Line: 19 / 39-51
Confidence: HIGH

Problem: `phpunit.xml` declares `<env name="DB_DATABASE" value="laravel_test"/>`, but PHPUnit
**ignores** a `<env>` value that already exists in the environment unless `force="true"` is set:

```php
// vendor/phpunit/phpunit/src/TextUI/Configuration/PhpHandler.php:140
if ($force || getenv($name) === false) {
    putenv("{$name}={$value}");
}
```

The CI `ci` job sets `DB_DATABASE: laravel` at job level and its MariaDB service only creates the
`laravel` database (`MARIADB_DATABASE: laravel`). It never creates `laravel_test`. The suite
therefore runs against `laravel` in CI and against `laravel_test` locally, purely because of
environment-variable precedence.

Only the `dusk` job provisions `laravel_test`
(`.github/workflows/tests.yml`, step "Create Dusk database").

Impact:
- Local/CI parity is accidental. Any change that makes `phpunit.xml` authoritative (e.g. adding
  `force="true"`) breaks CI immediately with "Unknown database 'laravel_test'".
- A fresh clone following `AGENTS.md` (`docker compose exec … php artisan test`) fails unless
  somebody manually creates `laravel_test`; `composer setup` only migrates `DB_DATABASE=laravel`.
- The Dusk suite and the PHPUnit suite can silently run against the same database when the local
  environment happens to export `DB_DATABASE`.

Recommendation: pick one source of truth. Cheapest correct fix: drop `DB_DATABASE`/`DB_HOST`/`DB_*`
from `phpunit.xml` and let `.env`/CI decide, or add an explicit "create test database" step to
both CI jobs. Also document the required local setup in `AGENTS.md`.

### TEST-003 — The documented SQLite test path does not work

Severity: MEDIUM
Category: Testing / Portability
File: app/Queries/ListQueryBase.php, .github/docs/architecture/ARCHITECTURE.md
Line: 27 / 50-51
Confidence: HIGH

Problem: `ARCHITECTURE.md` claims SQLite is used for isolated in-memory tests. It is not, and it
does not work.

Evidence:

```console
$ env DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --compact
  → Tests: 7 failed, 230 passed (1196 assertions)

FAILED CustomerListQueryTest > search treats percent as a literal character
FAILED CustomerListQueryTest > search treats underscore as a literal character
FAILED CustomerListQueryTest > search treats backslash as a literal character
FAILED ProjectCrudTest        > authenticated users can …
FAILED ProjectInputValidationTest > project can b…   (×2)
```

Root cause of the three search failures:

```php
// app/Queries/ListQueryBase.php:27
$escapedSearch = addcslashes($search, '%_\\');
```

MySQL honours `\` as the default `LIKE` escape character; **SQLite does not** — it requires an
explicit `ESCAPE '\'` clause. The three `CustomerListQueryTest` tests that assert exactly this
behaviour (`CustomerListQueryTest.php:71-99`) fail on SQLite and pass on MariaDB.

The four `Project*` failures are a test-side artifact, not an application bug: `assertDatabaseHas`
compares `'start_date' => '2026-10-15'` against SQLite's `'2026-10-15 00:00:00'`.

Impact: the non-MySQL branch of all three domain migrations is unverified dead code, and the
architecture document misleads every agent that reads it.
Recommendation: either fix the claim in `ARCHITECTURE.md` (one line) or fix the code
(`->whereRaw(..., 'like', ...)` plus an explicit escape) and normalise the date assertions. Do not
leave the two contradicting each other.

### TEST-004 — `ProjectAccessTest` / `EpicAccessTest` do not exist

Severity: LOW
Category: Testing / Coverage
File: tests/Feature/Customers/CustomerAccessTest.php
Line: 1-36
Confidence: HIGH

Problem: guest / unverified / invalid-input access coverage exists only for customers. Projects and
epics rely on the shared `auth` + `verified` group in `routes/web.php:17` and are untested for it.

Impact: a future route added outside that group would ship without a failing test.
Recommendation: data-providerise `CustomerAccessTest` across the three resources, exactly as
`ResourceActivationTest` already does. Small, high-value.

## Coverage strengths (no action)

- **Race conditions are tested for real.** `ProjectTrashTest:139-171` and the equivalent epic test
  use `DB::listen` to create the competing row between the pre-check and the write, then assert the
  conflict flash. This is the correct technique and closes the gap flagged in the 2026-09-30 review.
- **Structural invariants are enforced by the suite**, not by convention:
  `ArchitectureTest`, `ValidationCoverageTest`, `ModelSchemaParityTest` and
  `TranslationFilesTest` fail the build on layering, validation, schema and i18n drift.
- **Auth/inactive-user behaviour has 9 dedicated tests** covering password, 2FA (including
  mid-challenge deactivation), passkey, remember-me and JSON requests.
- **`list_search` fragment isolation is tested for all six list routes**
  (`ListSearchFragmentTest`), including the `<!DOCTYPE html>` negative assertion.
- **Dusk covers the browser-only surface**: CRUD with confirmations, search + clear with Alpine
  state preservation across the morph, select2 inline customer creation, dirty-form protection.
- `RefreshDatabase` transaction wrapping keeps the suite fast (22 s for 237 tests).