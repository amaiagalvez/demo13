# Code Review

**Date:** 2026-10-02
**Scope:** full repository, read-only
**Baseline:** `5cd03c9` (`t-07`), working tree clean at the time of writing
**Rules:** `.github/docs/review-rules.md`, `.github/docs/architecture/ARCHITECTURE.md`

## Executive Summary

The application is in good shape for its size. Static analysis is clean (PHPStan level 9),
formatting is clean (Pint), dependencies are free of known advisories, the layering rules are
enforced by the test suite rather than by convention, and the inactive-user security feature is
unusually well covered (nine tests across password, 2FA, passkey and remember-me paths).

**One confirmed blocking defect: the test suite is red.** `php artisan test` returns
**1 failed, 236 passed**. `tests/Feature/Epics/EpicCommentTest.php:77` calls
`$response->viewData('list')`, but all nine list `index()` actions return a *rendered string* rather
than a `View`, because `View::fragmentIf()` returns a string in both branches. This makes CI red on
any code change.

Three medium findings are worth acting on this sprint:

1. `phpunit.xml` declares `DB_DATABASE=laravel_test`, but PHPUnit silently ignores a `<env>` value
   that already exists in the environment. CI therefore runs the suite against `laravel` and nobody
   ever creates `laravel_test`. Local/CI parity is accidental and a fresh clone cannot run the tests.
2. `ARCHITECTURE.md` states that SQLite is the test engine. It is not, and the suite **fails** on
   SQLite (7 tests) because `ListQueryBase`'s `LIKE` escaping relies on MySQL's implicit `\` escape.
3. The parent-deletion guards are check-then-act, so a concurrent project creation can soft-delete a
   customer that has just gained a project — breaking an invariant stated in `ARCHITECTURE.md`.

Two hypotheses that looked like serious bugs were refuted with evidence during the review and are
**not** in this report: the per-epic comment limit works correctly (Laravel 13 uses a single
window-function query), and the list search does not lose focus across the Alpine morph.

Nothing CRITICAL was found. No exploitable vulnerability was found.

## Detected Stack

Verified from `composer.json`, `composer.lock`, `package.json`, `docker-compose.yml`,
`Dockerfile.dusk`, `phpunit.xml` and `.github/workflows/tests.yml` — nothing assumed.

| Layer | Actual |
|---|---|
| PHP | `^8.3` in `composer.json`; PHP **8.4** in Docker; PHP **8.3** in CI (deliberate, `ARCHITECTURE.md:129`) |
| Framework | Laravel **13.17** |
| Auth | Fortify **1.37.2** — registration, password reset, email verification, 2FA, passkeys (`@laravel/passkeys ^0.2.0`) |
| Frontend | Blade + Livewire **4.1** + Flux **2.13.1** + Blaze **1.0** (Volt-style `⚡` views); Alpine bundled |
| Build | Vite **8** + `vite-plus 0.3.0`, Tailwind **4**, jQuery 3.7 + select2 4 |
| Database | MariaDB **11.7** (Docker); SQLite/pgsql branch exists in migrations but is **not exercised** |
| Cache / session / queue | `database` driver for all three; **no jobs, no events, no listeners, no scheduled tasks** in app code |
| Redis / Horizon | **Not present.** Redis vars in `.env.example` are unused boilerplate |
| Tests | PHPUnit **12.5**, Paratest 7.20, Dusk **8.7** |
| Static analysis | Larastan **3.9** / PHPStan **level 9**, Pint **1.27** |
| CI/CD | GitHub Actions: `ci` (Pint + PHPStan + PHPUnit on PHP 8.3 / Node 22 / MariaDB 11.7) and `dusk`; Dependabot weekly for actions/composer/npm |
| API | **None.** One JSON endpoint branch (`CustomerController::store`) for select2 inline creation |
| Observability | Daily file log only; Pulse/Telescope/Nightwatch explicitly disabled |

## Checks Executed

All commands were run inside the project's own container
(`docker compose exec -T -e XDEBUG_MODE=off laravel13 …`), as required by `AGENTS.md`.
`composer ci:check` was deliberately **not** used (its prepare step clears the config cache).

| # | Command | Result |
|---|---|---|
| 1 | `./vendor/bin/pint --test` | **PASS** — 128 files, no fixes needed |
| 2 | `./vendor/bin/phpstan analyse --no-progress` | **[OK] No errors** — level 9 over `app/ bootstrap/ config/ database/ resources/ routes/ tests/` |
| 3 | `composer audit --no-interaction` | **No security vulnerability advisories found** |
| 4 | `php artisan test --compact` | **1 failed, 236 passed (1201 assertions), 22.27 s** |
| 5 | `php artisan test --compact --filter=EpicCommentTest` | **1 failed, 6 passed** — `The response is not a view.` at line 77 |
| 6 | `env DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --compact` | **7 failed, 230 passed** — see TEST-003 |
| 7 | `SHOW CREATE TABLE customers\|projects\|epics\|epic_comments\|users` | Captured verbatim in `database.md` |
| 8 | `EXPLAIN` on the project list and customer search queries | `type: ALL, key: NULL, Using temporary; Using filesort` |
| 9 | `npm ls --depth=0` | Dependency tree resolves cleanly |
| 10 | `npm audit --omit=dev` | **found 0 vulnerabilities** |
| 11 | `SELECT 'ACME' = 'acme' COLLATE utf8mb4_unicode_ci` | `1` (case-insensitive, consistent with `Rule::unique`) |
| 12 | Framework behaviour probe: 3 epics × 30 comments through `EpicListQuery::active()` | 20 comments loaded **per epic**, single window-function query (refuted a HIGH hypothesis) |
| 13 | `docker compose ps` | Confirmed `0.0.0.0:80->80/tcp` on the app vs `127.0.0.1` on db/phpMyAdmin/mailhog |

**Test isolation.** The host has no PHP/Composer, so all PHP ran in Docker. Because `RefreshDatabase`
performs `migrate:fresh`, an isolated MariaDB database named `laravel_review` was created for this
review and the suite was pointed at it with `-e DB_DATABASE=laravel_review` (verified effective: the
`UniqueConstraintViolationException` text reported `Database: laravel_review`). The project's
`laravel` and `laravel_test` databases were **not** written to by this review. `laravel_review` may
be dropped with
`docker compose exec -T db sh -c "mariadb -uroot -ptormenta -e 'DROP DATABASE laravel_review'"`.

**Dusk: NOT RUN.** `tests/Browser/*` uses the `DatabaseMigrations` trait, which runs
`migrate:fresh` + `migrate:rollback` against the **persistent** `laravel_test` database. Per the
read-only rules, destructive migrations against a persistent database are not executed. It also
cannot be redirected without editing `tests/DuskTestCase.php`, which hardcodes
`laravel_test` in both `setUp()` and `getEnvironmentSetUp()`. Chromium and chromedriver *are*
present in `laravel13-dusk` (`/usr/bin/chromium`, `/usr/bin/chromedriver`), so CI remains the
verification path for the browser suite.

**`npm run build`: NOT RUN.** The `laravel13-npm` service command begins with
`npm install -g npm@latest && … && npm install`, which can rewrite `package-lock.json`, and the build
itself overwrites the `public/build` assets currently being served to the running container (and to
any concurrent Dusk run). Building was judged not appropriate in a read-only review. Non-destructive
equivalents (`npm ls`, `npm audit`) were run instead and both are clean.

**Concurrency note.** The working tree was being edited by another session while this review ran
(`tests/Feature/ResourceActivationTest.php` changed at 21:32 and 21:34; the changes were committed as
`5cd03c9` mid-review). Early failures involving `ResourceActivationTest` were an artefact of that
concurrent edit and were **not** reported as findings; the final result above is from the committed
state. Likewise, `laravel_test` was found emptied (`migrations` table only, 0 rows) partway through
the review — the signature of a concurrent `DatabaseMigrations` Dusk run, not of any command issued
here.

## Critical Findings

None.

## High Findings

### BUG-001 — The test suite is red: list controllers never return a View

Severity: HIGH
Category: Bugs / Correctness
File: app/Http/Controllers/EpicController.php
Line: 30 (and the `index()` method of `CustomerController`, `ProjectController` and all six
`*InactiveController` / `*TrashController` classes)
Confidence: HIGH

Problem: every list `index()` declares `: View|string` but returns
`view(...)->fragmentIf($request->hasHeader('X-List-Fragment'), 'list-results')`, and
`View::fragmentIf()` returns a **string in both branches**. The `View` half of the union type is
unreachable, so `TestResponse::viewData()` and `assertViewHas()` can never succeed on a list route.

Evidence:

```php
// app/Http/Controllers/EpicController.php:26-30
return view('epics.list', [
    'epics' => $epics,
    'availableProjects' => Project::query()->with('customer')->orderBy('name')->get(['id', 'name', 'customer_id']),
    'list' => $transformer->active($epics, $search),
])->fragmentIf($request->hasHeader('X-List-Fragment'), 'list-results');
```

```php
// vendor/laravel/framework/src/Illuminate/View/View.php:114-121
public function fragmentIf($boolean, $fragment)
{
    if (value($boolean)) {
        return $this->fragment($fragment);   // string
    }

    return $this->render();                   // string
}
```

```console
$ php artisan test --compact --filter=EpicCommentTest
FAILED  Tests\Feature\Epics\EpicCommentTest > list embeds only the most recent comments…
  The response is not a view.
  at tests/Feature/Epics/EpicCommentTest.php:77
```

Impact: `composer ci:check` fails, so the pipeline is red on every code change. The failing test also
never reaches `assertCount($limit, $comments)` (line 119), leaving the comment-limit behaviour
unverified by the suite — it is in fact correct (see PERF "verified clean" in `performance.md`), but
nothing asserts it. The misleading return type also invites the next contributor to write another
view-data assertion that cannot work.

Recommendation (smallest change that keeps the fragment feature and restores testability):

```php
$view = view('epics.list', [/* … */]);

return $request->hasHeader('X-List-Fragment')
    ? $view->fragment('list-results')
    : $view;
```

Apply to the nine `index()` methods; the declared `View|string` type then becomes accurate. Do **not**
rewrite `EpicCommentTest` to scrape HTML — the assertion is legitimate.

### TEST-002 — `phpunit.xml` and CI disagree on the test database, and nothing creates `laravel_test`

Severity: HIGH
Category: Testing / Production
File: phpunit.xml
Line: 19 (also `.github/workflows/tests.yml:39-51`)
Confidence: HIGH

Problem: `phpunit.xml` declares `<env name="DB_DATABASE" value="laravel_test"/>` without
`force="true"`, and PHPUnit **ignores** a `<env>` value that is already present in the environment:

```php
// vendor/phpunit/phpunit/src/TextUI/Configuration/PhpHandler.php:140
if ($force || getenv($name) === false) {
    putenv("{$name}={$value}");
}
```

The CI `ci` job exports `DB_DATABASE: laravel` at job level and its MariaDB service only creates
`laravel` (`MARIADB_DATABASE: laravel`). No step in that job creates `laravel_test`; only the `dusk`
job provisions it. So the suite runs against `laravel` in CI and against `laravel_test` locally,
purely through environment-variable precedence.

Impact:
- Local/CI parity is accidental. The moment `phpunit.xml` becomes authoritative — adding
  `force="true"`, switching to `vendor/bin/phpunit` with a different bootstrap order, or pinning a
  CI env var less specifically — CI breaks immediately with `Unknown database 'laravel_test'`.
- A fresh clone following `AGENTS.md` (`docker compose exec … php artisan test`) fails unless
  somebody manually creates `laravel_test`. `composer setup` migrates only `DB_DATABASE=laravel`.
- Nothing documents the requirement, so the first person to hit it will "fix" it by editing
  `phpunit.xml`, which then silently changes what CI tests.

Recommendation: pick one source of truth. Cheapest: remove `DB_DATABASE` (and the other `DB_*`
entries) from `phpunit.xml` and let `.env` / the CI job environment decide, then add an explicit
"create the test database" step to **both** CI jobs and one line to `AGENTS.md`. If you prefer the
file to stay authoritative, add `force="true"` **and** provision `laravel_test` in the `ci` job.

## Medium Findings

### BIZ-001 — Inactive customers and projects are selectable, contradicting the on-screen warning

Severity: MEDIUM
Category: Business logic / Correctness
File: app/Http/Controllers/ProjectController.php, app/Http/Controllers/EpicController.php
Line: 28 (both); copy at `resources/views/projects/list.blade.php:92` and
`resources/views/epics/list.blade.php:106`
Confidence: HIGH

Problem: both list pages warn "No **active** … are available", but the option lists that decide
whether to show the warning are not filtered on `active`.

Evidence:

```php
// app/Http/Controllers/ProjectController.php:28  — no active filter
'availableCustomers' => Customer::query()->orderBy('name')->get(['id', 'name']),

// app/Http/Controllers/EpicController.php:28     — no active filter
'availableProjects' => Project::query()->with('customer')->orderBy('name')->get(['id', 'name', 'customer_id']),
```

```php
// app/Http/Requests/ProjectRequest.php:47-51, EpicRequest.php:48-52 — validated against deleted only
'customer_id' => ['required', 'integer', Rule::exists(Customer::class, 'id')->whereNull('deleted_at')],
'project_id'  => ['required', 'integer', Rule::exists(Project::class, 'id')->whereNull('deleted_at')],
```

Impact: a project can be created under a deactivated customer and an epic under a deactivated
project. Because deactivation does not cascade, the result is a child that its parent's active list
hides — and the user is reading a warning about "active" records while the form offers inactive
ones.

Note: **already tracked.** `todo.md` carries this exact requirement
(`en los select … solo se mostrarán los que tengan active=1`, with an exception for the currently
selected record in edit mode). Do not re-plan it; implement that entry and align the two callout
strings with whatever it decides.

### CONC-001 — Check-then-act deletion guards can soft-delete a parent that just gained children

Severity: MEDIUM
Category: Concurrency / Data integrity
File: app/Http/Controllers/CustomerController.php, app/Http/Controllers/ProjectController.php,
      app/Http/Controllers/CustomerTrashController.php, app/Http/Controllers/ProjectTrashController.php
Line: 86-91 / 74-79 / 61-66 / 62-67
Confidence: MEDIUM

Problem: the guard is a `SELECT EXISTS` followed by a `DELETE`, with no transaction and no row lock.
The foreign keys are `restrictOnDelete`, which only protects *hard* deletes — a soft delete never
touches the FK.

Evidence:

```php
// app/Http/Controllers/CustomerController.php:86-91
if ($customer->projects()->withTrashed()->exists()) {
    return to_route('customers.index')
        ->with('error', __('Customer cannot be deleted while it has projects.'));
}

$customer->delete();
```

Reachable interleaving:

1. Request A (delete customer) — `exists()` returns `false`.
2. Request B (create project) — `Rule::exists(Customer::class, 'id')->whereNull('deleted_at')` still
   passes (not yet deleted) and the row is inserted.
3. Request A — `delete()` soft-deletes the customer.

Result: a project whose customer is in the trash, contradicting
`.github/docs/architecture/ARCHITECTURE.md:65` ("Customers with projects cannot be moved to the
trash or permanently deleted").

Impact: narrow window (two requests interleaving within milliseconds), no data loss, and the UI
tolerates the state (`withTrashed()` on the relations and joins). But a documented business rule is
not actually enforced, and recovery is manual.

Recommendation (minimal, no new layers): perform the guard and the write inside `DB::transaction()`
with the parent row locked, and add one test per resource that creates the child between the check
and the write — `tests/Feature/Projects/ProjectTrashTest.php:139-171` already demonstrates the exact
technique for the restore path.

### PERF-001 — Every list render loads unbounded option lists, including on every search keystroke

Severity: MEDIUM
Category: Performance
File: app/Http/Controllers/ProjectController.php, app/Http/Controllers/EpicController.php
Line: 28 (both)
Confidence: HIGH

Problem: the forms are rendered inline inside the list view, so the option lists are re-queried on
every request, including each debounced search fragment refresh.

Evidence:

```php
// app/Http/Controllers/ProjectController.php:28
'availableCustomers' => Customer::query()->orderBy('name')->get(['id', 'name']),

// app/Http/Controllers/EpicController.php:28
'availableProjects' => Project::query()->with('customer')->orderBy('name')->get(['id', 'name', 'customer_id']),
```

Neither is paginated, searchable, nor filtered on `active`. The epic query additionally eager-loads
every customer of every project.

Trigger frequency: `resources/js/app.js:384-401` fires a request on every search input after 400 ms
of inactivity, plus every pagination link (`app.js:415-431`) and every `popstate` (`app.js:375`).

Likely bottleneck: two full-table scans plus one `customers` scan per list request, with O(n) rows
serialised into every response payload.

Expected impact: negligible at 5 customers / 67 projects; linear degradation. At a few thousand
projects the epic list response would carry thousands of `<option>` elements and search-as-you-type
would visibly stall.

Solution and cost: make the selects remotely searchable. The project form already integrates select2
(`resources/views/projects/form.blade.php:34-47`), so the epic select can reuse that pattern with a
small `GET epics/project-options?search=` endpoint — medium cost (endpoint + Form Request +
authorization + tests). **Do not build it now**; the trigger is "more than a few hundred
customers/projects".

### TEST-003 — The documented SQLite test path does not work

Severity: MEDIUM
Category: Testing / Portability
File: app/Queries/ListQueryBase.php
Line: 27 (claim at `.github/docs/architecture/ARCHITECTURE.md:50-51`)
Confidence: HIGH

Problem: `ARCHITECTURE.md` states "SQLite is used for isolated in-memory tests". It is not, and it
does not work.

Evidence:

```console
$ env DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --compact
  → Tests: 7 failed, 230 passed (1196 assertions)

FAILED CustomerListQueryTest > search treats percent as a literal character
FAILED CustomerListQueryTest > search treats underscore as a literal character
FAILED CustomerListQueryTest > search treats backslash as a literal character
FAILED ProjectCrudTest > authenticated users can …
FAILED ProjectInputValidationTest > project can b… (×2)
```

Root cause of the three search failures:

```php
// app/Queries/ListQueryBase.php:27
$escapedSearch = addcslashes($search, '%_\\');
```

MySQL honours `\` as the implicit `LIKE` escape character; **SQLite does not** — it requires an
explicit `ESCAPE '\'` clause. The three tests that assert exactly this behaviour
(`tests/Feature/Customers/CustomerListQueryTest.php:71-99`) pass on MariaDB and fail on SQLite. The
four `Project*` failures are a test-side artefact, not an application bug: `assertDatabaseHas`
compares `'start_date' => '2026-10-15'` against SQLite's `'2026-10-15 00:00:00'`.

Impact: the `sqlite`/`pgsql` branch of all three domain migrations
(`CREATE UNIQUE INDEX … WHERE deleted_at IS NULL`) is unverified code, and the architecture document
misleads every agent that reads it — including this one.

Recommendation: make the code and the document agree. Cheapest: correct the `ARCHITECTURE.md`
sentence to name MariaDB as the only supported engine. If SQLite support is actually wanted, add the
explicit `ESCAPE` clause and normalise the date assertions, then add SQLite to the CI matrix.

### ARCH-001 — `ARCHITECTURE.md` claims a test setup that does not exist

Severity: MEDIUM
Category: Architecture / Documentation drift
File: .github/docs/architecture/ARCHITECTURE.md
Line: 50-51
Confidence: HIGH

Problem: the same claim as TEST-003, viewed from the documentation side. This file is the first thing
`AGENTS.md`, `.github/docs/review-rules.md:304-310` and every reviewer agent tells you to read, so a
false statement here propagates.

Impact: a reader who trusts it assumes the non-MySQL migration branch is covered and will not notice
that `LIKE` escaping is MySQL-only. The "Known Technical Debt" section (`:137-140`) does not mention
this.

Recommendation: one-line correction, folded into the TEST-003 fix. Reported separately because the
owner of the fix is different (docs vs code).

### SEC-001 — The development application is published on all interfaces

Severity: MEDIUM
Category: Security / DevOps exposure
File: docker-compose.yml
Line: 18-20
Confidence: HIGH

Problem: the `laravel13` service publishes `"80:80"` and `"${VITE_PORT:-5173}:${VITE_PORT:-5173}"`,
which bind `0.0.0.0`. Every other service is explicitly loopback-bound.

Evidence:

```console
$ docker compose ps
demo13-db-1                 ... 127.0.0.1:13306->3306/tcp
demo13-laravel13-1          ... 0.0.0.0:80->80/tcp, 0.0.0.0:5173->5173/tcp
demo13-laravel13-myadmin-1  ... 127.0.0.1:4000->80/tcp
demo13-mailhog-1            ... 127.0.0.1:8025->1025/tcp
```

The reachable service carries development credentials (`DB laravel/laravel` in `.env.example`), a
seeded `test@example.com` user (`database/seeders/DatabaseSeeder.php:37-40`) and
`MYSQL_ROOT_PASSWORD: tormenta` committed to the repository.

Impact: anyone on the same network can reach the dev app and attempt authentication.
`.github/docs/architecture/ARCHITECTURE.md:130-131` already states administrative ports and
development credentials are local-only, so the config does not match the documented intent.

Recommendation: bind the app to loopback like its siblings —
`"127.0.0.1:80:80"` and `"127.0.0.1:${VITE_PORT:-5173}:${VITE_PORT:-5173}"` — and move the MariaDB
root password into an env var with a local-only default.

## Low Findings

### DB-001 / PERF-002 — No index supports any list filter or sort column

Severity: LOW
Category: Database / Performance
File: database/migrations/2026_09_25_000000_create_customers_table.php,
      database/migrations/2026_09_28_173607_create_projects_table.php,
      database/migrations/2026_09_30_175706_create_epics_table.php
Line: 21-22 / 28-29 / 27-28
Confidence: HIGH

Problem: the only secondary indexes are the generated-column unique indexes and the implicit FK
indexes. Every list query filters or orders on `active`, `deleted_at`, `name`, `start_date`,
`end_date` or `updated_at` — none indexed.

Evidence:

```console
$ EXPLAIN SELECT * FROM projects p JOIN customers c ON c.id=p.customer_id
          WHERE p.deleted_at IS NULL AND p.active=1 ORDER BY p.start_date, p.end_date, c.name, p.name LIMIT 5;
type: ALL   key: NULL   Extra: Using where; Using temporary; Using filesort
```

Plus `LIKE '%term%'` in `app/Queries/ListQueryBase.php:30-34`, which cannot use an index by
construction, and `orderByRaw('epics.start_date IS NULL')` (`EpicListQuery:43,45`).

Impact: not measurable at current row counts (5 customers / 67 projects / 35 epics). Relevant past
tens of thousands of rows.
Recommendation: **do not add indexes now.** Record the trigger (domain tables past ~50k rows); at
that point add one composite index per list query.

### CONC-002 — `deactivate` / `reactivate` are unguarded read-modify-write

Severity: LOW
Category: Concurrency
File: app/Http/Controllers/CustomerInactiveController.php, ProjectInactiveController.php, EpicInactiveController.php
Line: 28-43 / 29-45 / 29-45
Confidence: MEDIUM

Problem: `$model->active = false; $model->save();` never reads the current value, so two concurrent
requests can both flash success while only the last write survives.
Impact: cosmetic — no invariant depends on `active`, and the operation is idempotent.
Recommendation: optional. A real fix needs `lockForUpdate()` or a version column, which is not worth
it for a boolean display flag. Recorded so the choice is deliberate.

### TEST-004 — Access coverage exists only for customers

Severity: LOW
Category: Testing / Coverage
File: tests/Feature/Customers/CustomerAccessTest.php
Line: 1-36
Confidence: HIGH

Problem: guest redirect, unverified redirect and invalid-input coverage exist for customers only.
Projects and epics rely on the shared `auth` + `verified` group in `routes/web.php:17`.
Impact: a future route added outside that group ships without a failing test.
Recommendation: data-providerise `CustomerAccessTest` across the three resources, exactly as
`ResourceActivationTest` already does. Small and high value.

### PERF-003 — `EnsureUserIsActive` adds one query per authenticated request

Severity: LOW
Category: Performance
File: app/Http/Middleware/EnsureUserIsActive.php
Line: 24
Confidence: HIGH

Problem: every authenticated web request runs an extra
`select exists(select * from users where id = ? and active = 1)`.
Impact: one primary-key lookup — negligible.
Recommendation: **keep it.** This is the deliberate trade-off that revokes sessions on deactivation
(`tests/Feature/Auth/InactiveUserTest.php:95-110`); caching it would weaken a documented security
behaviour.

### PERF-004 — Page size is hardcoded to 5

Severity: LOW
Category: Performance / UX
File: app/Queries/ListQueryBase.php
Line: 14
Confidence: HIGH

Problem: `public const PER_PAGE = 5;` — not configurable, not overridable per request.
Impact: 100 epics → 20 pages. Also amplifies PERF-001.
Recommendation: promote to a config value when the UX asks for it. Not a correctness problem.

### MAINT-001 — Orphan translation key in all four locales

Severity: LOW
Category: Maintainability / i18n
File: lang/en.json, lang/es.json, lang/eu.json, lang/fr.json
Line: 47 in each file
Confidence: HIGH

Problem: `"No active customers available."` exists in every locale but is referenced nowhere; the
string actually used is `"No active customers are available. Create one from the project form."`
(line 74).
Impact: four dead lines, and `tests/Unit/Translations/TranslationFilesTest.php` structurally cannot
detect orphans because it only compares keys *across* locales.
Recommendation: delete the four entries, then add a test asserting every key in `lang/en.json` is
referenced by `resources/` or `app/`.

### FE-001 — The three list views are near-identical copies

Severity: LOW
Category: Frontend / Maintainability
File: resources/views/customers/list.blade.php, resources/views/projects/list.blade.php, resources/views/epics/list.blade.php
Line: whole files (~150 / ~180 / ~197 lines)
Confidence: HIGH

Problem: the Alpine `x-data` object, the `@fragment('list-results')` wrapper, the
`x-list.search` + `x-list.table` composition, the error/conflict-modal bootstrap and the trailing
`<x-list.confirm-modal>` are duplicated three times with only the prefix and columns changing. The
two "no records available" callouts and the `commented_epic_id` rehydration are the only real
divergences — and the callouts have already drifted (BIZ-001).
Impact: any UX or accessibility change to the search/confirm/row-action flow must be applied and
re-verified three times.
Recommendation: extract only the duplicated Alpine component and fragment wrapper as one anonymous
Blade component with a `prefix` prop, following the existing `x-list.*` precedent. Do **not** build
a generic list-view component — the columns and search forms genuinely differ, and
`.github/docs/review-rules.md:27-42` forbids that. Schedule it if a fourth resource ever arrives.

### DEP-001 — `laravel13-composer` deletes and regenerates `composer.lock`

Severity: LOW
Category: Dependencies / Reproducibility
File: docker-compose.yml
Line: 60-74
Confidence: HIGH

Problem: the documented "install dependencies" profile runs
`rm -Rf vendor && rm -f composer.lock && composer install --no-progress`.
Impact: a routine-looking command silently rewrites a committed lockfile and can upgrade every
package to its newest allowed version, so dependency drift enters without appearing in a diff.
Recommendation: make it `composer install` only; use `composer update` explicitly. Rename the
service if the destructive behaviour is intentional.

### DEP-002 — `laravel13-npm` installs `npm@latest` on every invocation

Severity: LOW
Category: Dependencies / Reproducibility
File: docker-compose.yml
Line: 88-90, 111-113
Confidence: HIGH

Problem: both npm services run `npm install -g npm@latest && … && npm install && npm run build`, so
the toolchain floats with wall-clock time and `package-lock.json` can be rewritten.
Impact: a build that worked yesterday can fail today with no repository change. CI uses `npm ci` and
is unaffected, so the flakiness is local-only — exactly where it costs the most debugging time.
Recommendation: drop the `npm@latest` step (the image already pins Node via `${DC_NODE:-24-bullseye}`)
and use `npm ci` locally.

### DEP-003 — `.env.example` commits a machine-specific UID/GID

Severity: LOW
Category: Dependencies / Repo hygiene
File: .env.example
Line: 44-45
Confidence: HIGH

Problem: `DC_UID=1002` / `DC_GID=1002` are committed and consumed by `docker-compose.yml`
(`user: "${DC_UID:-1000}:${DC_GID:-1000}"` on three services). Any other machine runs as the wrong
uid unless overridden.
Impact: silent `storage/` and `public/build` permission errors for new contributors.
Recommendation: comment the lines out or document the override in `AGENTS.md`.

### DEV-002 — PHP version drifts between CI (8.3) and local Docker (8.4)

Severity: LOW
Category: DevOps / Consistency
File: .github/workflows/tests.yml, docker-compose.yml
Line: 78 / 6 and 20 (multiple services)
Confidence: HIGH

Problem: `composer.json` requires `^8.3`, CI pins `8.3`, every Docker service uses
`webdevops/php-apache-dev:${DC_PHP:-8.4}`. Recorded in `ARCHITECTURE.md:129`, so it is a known gap.
Impact: 8.4-only syntax would pass Pint and PHPStan locally and fail CI. Nothing in `app/` uses it
today.
Recommendation: pin the local image to 8.3, or echo `PHP_VERSION` as a CI step so the difference is
visible in the log.

### DEV-003 — The CI `ci` job does not cache npm

Severity: LOW
Category: DevOps / CI cost
File: .github/workflows/tests.yml
Line: 88-91
Confidence: HIGH

Problem: `Setup Node` in the `ci` job has no `cache: 'npm'`; the `dusk` job does.
Impact: negligible — the `ci` job never installs or builds assets. Reported for consistency only.

### CLEAN-001 — Two idioms for the same uniqueness-violation check

Severity: LOW
Category: Clean code / Consistency
File: app/Http/Controllers/CustomerTrashController.php, ProjectTrashController.php, EpicTrashController.php
Line: 41-47 / 42-48 / 47-53
Confidence: HIGH

Problem: all six `store()`/`update()` methods use
`UniqueConstraintViolation::rethrowAsValidationError()`, while the three `restore()` methods inline
a byte-identical `catch (QueryException)` block because they return a redirect instead of throwing.
Impact: two idioms for one decision; a change to the detection logic must be reviewed in four
places.
Recommendation: acceptable as is (explicit > implicit, and a base trash controller is forbidden).
If you want one place, the inline form reduces to a single `causedBy()` call, which already exists
on the support class.

### OBS-001 — No application-level telemetry

Severity: LOW
Category: Observability
File: phpunit.xml, .env.example
Line: 17-19 / 20-21
Confidence: HIGH

Problem: no error tracker, no APM, no metrics. The only signal is `LOG_CHANNEL=daily`.
`phpunit.xml:17-19` disables Pulse, Telescope and Nightwatch, and `boost.json` sets
`"nightwatch": false`.
Impact: proportionate for the current scope; a production deployment would have no visibility into
its own failures.
Recommendation: **no change now.** Add to `ARCHITECTURE.md:127-134`'s production list when a
production environment is actually provisioned.

## Informational Findings

- **API-001** — No write endpoint is rate limited. Only Fortify's `login`, `two-factor` and
  `passkeys` limiters exist; no route uses `throttle`. Acceptable for a first-party authenticated
  app; recorded so the omission is a decision.
- **API-002** — `bootstrap/app.php:24-26`'s `shouldRenderJsonWhen` clause `$request->is('api/*')` is
  unreachable; no `api/*` route exists. Harmless and future-proof.
- **ARCH-002** — The `active` column on `users` means "login allowed", while on domain records it
  means "shown in the active list". The two semantics are independent and deactivation deliberately
  does not cascade (`ARCHITECTURE.md:57-58`). No change recommended; flagged so a future
  "unassignable" rule knows the columns are not interchangeable.
- **DEV-004** — CI is skipped entirely for `.md`-only changes. Intentional and correct. Recorded so
  nobody "fixes" it.
- **OBS-002** — `/up` (`bootstrap/app.php:14`) is unauthenticated and checks nothing but that PHP
  is serving. Fine as a liveness probe; do not assume it verifies the database.
- **MAINT-003** — `todo.md` mixes shipped work, ideas and review assignments with no state. When
  BIZ-001 is scheduled, move that requirement into `ARCHITECTURE.md` next to the other business
  rules.

## Security

No exploitable vulnerability was found. The full OWASP checklist and the evidence for each "not
exploitable" verdict are in `.github/reviews/2026-10-02/security.md`. Summary of what was checked
and cleared:

| Vector | Verdict |
|---|---|
| XSS (Blade) | Only one `{!! !!}` in the tree: Fortify's server-generated `$qrCodeSvg`. All user values use `{{ }}` or `@js()`. |
| XSS (search fragment `innerHTML`) | `app.js:462-477` injects a same-origin, server-rendered, fully escaped Blade fragment. No injection point. |
| XSS (`data-payload` JSON) | `row-actions.blade.php:16,27` — `{{ json_encode(...) }}` is escaped by `e()`; the DOM decodes it back to valid JSON. |
| Mass assignment | `#[Fillable(['name'])` on `Customer`; `active` written server-side only. `InactiveUserTest:16-32` proves `active => false` at registration is ignored. |
| SQL injection | All Eloquent/Query Builder with bound parameters. Raw SQL is limited to two constants. |
| CSRF | All routes in the `web` group; the one JSON client sends `X-CSRF-TOKEN`. |
| IDOR / BOLA | Documented decision: every verified user manages every resource. No route escapes that boundary. |
| Session hardening | `secure` defaults to `APP_ENV === 'production'`, `http_only => true`, `same_site => 'lax'`. |
| Rate limiting | Fortify defines `login` (5/min per email+IP), `two-factor`, `passkeys`. CRUD writes unthrottled — see API-001. |
| Inactive-user lockout | Password, 2FA (incl. mid-challenge), passkey and remember-me paths all covered by 9 tests. |
| Secrets | `.env` gitignored; only `.env.example` tracked with an empty `APP_KEY`. The MariaDB root password is committed — see SEC-001. |
| SSRF / command injection / path traversal / file upload | No such code paths exist. |

Accepted risk: **SEC-001** (dev app on `0.0.0.0`).

## Bugs / Correctness

- **BUG-001 (HIGH)** — `View::fragmentIf()` returns a string in both branches, so no list route
  ever returns a View. This is the cause of the single failing test.
- Nothing else confirmed. Hypotheses about eager-load limits, search focus, the
  `reuse_deleted_name` / `resolve_name_conflict` flags, comment-drawer rehydration, case-insensitive
  uniqueness, middleware ordering on login, and SQL strict mode were all raised and **refuted with
  evidence** — see `.github/reviews/2026-10-02/devils-advocate.md`.

## Database

- Schema, foreign keys and `onDelete` semantics are correct and match `ARCHITECTURE.md`.
  `epic_comments.epic_id ON DELETE CASCADE` and `epic_comments.user_id ON DELETE SET NULL` deliver
  the two documented comment rules; `restrictOnDelete` correctly blocks hard deletes of referenced
  parents.
- The generated-column uniqueness trick works and is the final arbiter for every name race.
  `UniqueConstraintViolation::causedBy()` correctly recognises `23505`, `23000`+`1062` and
  `23000`+`19`.
- `paginate()` totals are not inflated by the joins (many-to-one only).
- Gaps: **DB-001** (no list indexes) and **TEST-003** (the `sqlite`/`pgsql` branch is unexercised).
- **CONC-001** is a database-adjacent integrity gap the FKs structurally cannot cover, because it
  involves soft deletes.

## Performance

- No N+1 in the list queries. In particular, the per-epic comment limit is resolved by a **single**
  window-function query — verified at 3 epics × 30 comments → 20 each, sum 60, one statement.
- **PERF-001** (unbounded option lists re-fetched on every search keystroke) is the only finding
  with a realistic trigger today.
- **DB-001 / PERF-002** (unindexed filters and sorts, leading-wildcard `LIKE`) is honest
  non-optimisation: irrelevant at current row counts, documented with a trigger instead.
- **PERF-003** and **PERF-004** are recorded for completeness; neither should be changed yet.

## Architecture

- Layering is correct and **enforced by the suite**: `tests/Unit/ArchitectureTest.php` fails the
  build if a controller touches `DB::`/`Schema::`/`dd()`/`->paginate()`/raw input, if an input
  endpoint lacks a Form Request, or if a resource is missing any of its eight classes. It passes.
- No repository/service/DTO/CQRS/event-sourcing layer, matching
  `ARCHITECTURE.md:118-124` and `.github/docs/review-rules.md:27-42`.
- Business logic is findable: uniqueness scopes, deletion guards and the trash-name conflict flow
  live in Form Requests, controllers and one small support class, and are named in
  `.github/instructions/http.instructions.md`.
- The only architecture problem is documentation drift (**ARCH-001**) plus the `active`-semantics
  note (**ARCH-002**).

## Testing

- 237 tests, 1 201 assertions, 22 s. Strong behaviour coverage of auth, deactivation, trash
  conflicts, search escaping and fragment isolation.
- Race conditions are tested with a real technique (`DB::listen` injecting the competing row), which
  closes the gap flagged in the 2026-09-30 review.
- Structural invariants are test-enforced, not convention-enforced — unusual and valuable.
- Gaps: **BUG-001** (suite red), **TEST-002** (database divergence), **TEST-003** (SQLite claim),
  **TEST-004** (project/epic access coverage).
- Dusk was **not run** by this review (destructive `DatabaseMigrations` against the persistent
  `laravel_test` database). Chromium and chromedriver are present, so CI remains the verification
  path.

## Production

- `APP_DEBUG=false` in `.env.example` — the safe default, opposite to Laravel's boilerplate.
- `AppServiceProvider::configureDefaults()` raises `Password::min(12)…->uncompromised()` and enables
  `DB::prohibitDestructiveCommands()` in production only. Both are correct.
- Session cookies default to `secure` in production.
- CI is SHA-pinned, least-privilege (`contents: read`), concurrency-cancelled, with
  `persist-credentials: false`.
- Gaps: **SEC-001** / **DEV-001** (dev exposure, only), **OBS-001** (no telemetry),
  **TEST-002** (which database CI actually tests), **DEV-002** (PHP version drift).
- `ARCHITECTURE.md:137-140` still lists "observe the Dusk CI job once" as unverified technical debt.
  This review could not verify it (no CI access) and does not claim otherwise.

## Maintainability

- A new developer can understand this project quickly: `AGENTS.md` (stack, commands, project map),
  `ARCHITECTURE.md` (business invariants), `.github/instructions/*.instructions.md` (auto-injected
  per-zone rules). Above average for this size.
- Adding a **fourth** resource is the highest-risk change, because the three existing ones are
  independent copies rather than a shared base. The mitigation already in place
  (`ArchitectureTest::test_every_resource_ships_the_complete_set` + the mirror rule in
  `http.instructions.md`) is the right trade-off. **FE-001** is the cleanup to schedule if that
  happens.
- Hidden assumptions worth documenting: MySQL/MariaDB is the only supported engine; `PER_PAGE = 5`
  is assumed small enough that loading all option rows is acceptable; `resolve_name_conflict` and
  `reuse_deleted_name` only affect messages/branching despite their names.
- Debt is explicitly tracked in `todo.md` and `ARCHITECTURE.md:137-140`, which is good practice.
  **MAINT-003** notes the two lists should not overlap.

## Rejected Findings

Full reasoning in `.github/reviews/2026-10-02/devils-advocate.md`.

| Rejected hypothesis | Why it was dropped |
|---|---|
| The `limit(20)` comment eager load is global across the page (candidate HIGH) | Laravel 13 uses one window-function query; measured 20 **per epic**. |
| The search input loses focus/caret after the fragment morph | Alpine morph patches in place; `CustomerCrudTest:87` asserts state survives. |
| Case-insensitive uniqueness is a defect | `Rule::unique()` uses the same collation, so validation and DB always agree. |
| `resolve_name_conflict` / `reuse_deleted_name` are dead flags | They are the payload of the two-button conflict modal. |
| The comment drawer cannot re-open after posting | `back()` restores the page, so the epic is always in the rows. |
| Local Docker's `--sql-mode=""` weakens validation | `config/database.php` forces strict mode per connection. |
| `EnsureUserIsActive` breaks `POST /login` | `$request->user()` is null pre-authentication; 9 tests cover it. |
| Merge the three list views into one generic component | Forbidden by `review-rules.md:27-42`; downgraded to a scoped cleanup (FE-001). |
| `data-payload="{{ json_encode(...) }}"` is XSS | `e()` escapes; the DOM decodes back to valid JSON. |
| PHP 8.3 CI vs 8.4 local is a defect | Already documented; reported only as contributor friction. |

Severity downgrades applied after challenge: `deactivate/reactivate` race MEDIUM→LOW, missing
indexes MEDIUM→LOW, middleware query MEDIUM→LOW, duplicated views MEDIUM→LOW, orphan key
MEDIUM→LOW, no telemetry MEDIUM→LOW. Six duplicate findings were merged into one each (LAR-001 +
TEST-001, DB-001 + PERF-002, SEC-001 + DEV-001, TEST-003 + ARCH-001, FE-002 + MAINT-001).

## Action Plan

Ordered by impact. Each item is independent.

### P0 — the build is red

1. **BUG-001** — Return the `View` for non-fragment requests in the nine list `index()` methods
   (`if ($request->hasHeader('X-List-Fragment')) return $view->fragment('list-results'); return
   $view;`). Then re-run `php artisan test --compact`. Do not touch `EpicCommentTest`.
   *Files:* `app/Http/Controllers/{Customer,Project,Epic}{,Inactive,Trash}Controller.php`.
   *Verify:* `Tests: 237 passed`.

### P1 — make the test environment trustworthy

2. **TEST-002** — Give the test database one source of truth. Remove the `DB_*` entries from
   `phpunit.xml` **or** add `force="true"` plus a "create the test database" step in the `ci` job.
   Document the local bootstrap in `AGENTS.md`.
   *Files:* `phpunit.xml`, `.github/workflows/tests.yml`, `AGENTS.md`.

3. **ARCH-001 + TEST-003** — Make the code and `ARCHITECTURE.md:50-51` agree. One-line doc fix if
   MySQL-only is the intent; otherwise add `ESCAPE '\'` to `ListQueryBase:30-34` and normalise the
   SQLite date assertions. Report as **one** task.
   *Files:* `.github/docs/architecture/ARCHITECTURE.md`, `app/Queries/ListQueryBase.php`.

### P2 — enforce a documented business invariant

4. **CONC-001** — Wrap the four parent-deletion guards in `DB::transaction()` with
   `lockForUpdate()` on the parent, and add the interleaving test per resource, reusing the
   `DB::listen` pattern from `ProjectTrashTest:139-171`.
   *Files:* `app/Http/Controllers/{Customer,Project}Controller.php`,
   `app/Http/Controllers/{Customer,Project}TrashController.php`,
   `tests/Feature/{Customers,Projects}/*TrashTest.php`.

### P3 — close the dev-surface gap

5. **SEC-001** — Bind the app ports to `127.0.0.1` in `docker-compose.yml` and move
   `MYSQL_ROOT_PASSWORD` to an env var with a local-only default.
   *File:* `docker-compose.yml`.

6. **BIZ-001** — Implement the already-written `todo.md` select rule (create ⇒ only `active = 1`;
   edit ⇒ keep the current selection even if inactive, plus the active ones) and align the two
   callout strings with it. Plan it, do not improvise a narrower fix.
   *Files:* `app/Http/Controllers/{Project,Epic}Controller.php`,
   `resources/views/{projects,epics}/list.blade.php`, `todo.md` → `ARCHITECTURE.md`.

### P4 — cheap hardening and consistency

7. **TEST-004** — Data-providerise `CustomerAccessTest` across the three resources.
8. **DEP-001 / DEP-002** — `composer install` (not `rm composer.lock`) in `laravel13-composer`;
   drop `npm install -g npm@latest` and use `npm ci` in the npm services.
9. **DEP-003** — Comment out `DC_UID`/`DC_GID` in `.env.example`.
10. **MAINT-001** — Delete the orphan key from the four `lang/*.json` files and add a test that
    every `lang/en.json` key is referenced.

### Deferred — do not act without a trigger

- **PERF-001** (remote-searchable selects) — trigger: more than a few hundred customers/projects.
- **DB-001 / PERF-002** (indexes) — trigger: domain tables past ~50k rows.
- **FE-001** (extract the duplicated Alpine block) — trigger: a fourth resource.
- **OBS-001** (telemetry) — trigger: first production environment.
- **CONC-002, PERF-003, PERF-004, CLEAN-001, API-001, API-002, ARCH-002, DEV-002, DEV-003, DEV-004,
  OBS-002, MAINT-003** — recorded; no change justified today.

### Suggested task file

Written to `.github/tasks/07.full-code-review-2026-10-02.md`.