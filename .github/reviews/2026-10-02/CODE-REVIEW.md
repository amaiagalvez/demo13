# Code Review

**Date:** 2026-10-02
**Repository:** `/home/amaia/Mahaigaina/l13/demo13`
**Snapshot:** HEAD `781ccbb` **plus an uncommitted working tree that was changing during the review** — see
[Working tree note](#working-tree-note).
**Mode:** READ-ONLY. No application code, tests, dependencies, lockfiles, configuration or database data were
modified. The only files written are this report and the specialist reports beside it.

## Executive Summary

This is a small, well-built Laravel 13 CRUD application with a genuinely strong test suite and unusually good
project documentation. The architecture matches the problem: no repository/service/DTO layers, thin controllers,
list querying and row shaping deliberately separated, and machine-enforced rules that catch the obvious mistakes.
`ARCHITECTURE.md` even records its own rejected patterns, which is why this review rejects several plausible-looking
refactors instead of recommending them.

**All twelve documented business rules are implemented and covered by tests.** I verified each one individually
(see `.github/reviews/2026-10-02/business.md`). The correctness of the concurrency design is the standout: name
uniqueness is enforced by a stored generated column plus a unique index, so the database — not application code —
is the arbiter, and the race-absorbing helper is tested for all nine resource/write-path combinations.

**The dominant problem is that CI is red.** Three independent defects break it, and they are ordered badly: PHPStan
fails first, so the four failing tests are invisible in CI and the pipeline stops before reporting them.

1. All nine list `index()` actions return a **rendered string** instead of a `View`, because
   `View::fragmentIf()` always renders. Four tests that assert on view data fail. (`BUG-001`)
2. PHPStan level 9 reports one error, which halts CI before the suite runs. (`BUG-002`)
3. A new JSON endpoint trips the project's own architecture test. (`BUG-003`)

Beyond that, findings are modest and mostly documentation or hardening. The one user-visible gap is that **inactive
parents are still offered in the create/edit dropdowns** while the UI copy claims otherwise (`BUS-001`) — already
recorded as a TODO by the author, and provably live in the current database.

No critical vulnerability was found. There is no SQL injection (all values are bound; the only `orderByRaw` calls are
constant literals), no XSS (all user data goes through `x-text` or escaped Blade), no mass-assignment path to the
`active` flag, and no authorization bypass — the open-permissions model is documented intent, and CI is hardened
(pinned SHAs, `contents: read`, `persist-credentials: false`).

## Detected Stack

Verified against `composer.json`, `composer.lock`, `package.json`, `package-lock.json`, `docker-compose.yml`,
`Dockerfile.dusk` and `.github/workflows/tests.yml`. Nothing below is assumed.

| Component | Version / status | Source |
|---|---|---|
| PHP | `^8.3` (CI uses 8.3, Docker image uses 8.4) | `composer.json:14`, `.github/workflows/tests.yml:76`, `Dockerfile.dusk:1` |
| Laravel Framework | `^13.17` (13.17 resolved) | `composer.json:17` |
| Livewire | `^4.1` + `livewire/flux` `^2.13.1` + `livewire/blaze` `^1.0` | `composer.json:18-20` |
| Fortify | `^1.37.2` | `composer.json:16` |
| Passkeys | `@laravel/passkeys` `^0.2.0` | `package.json:11` |
| Frontend | Blade + Alpine (bundled), Tailwind `^4.0.7`, Vite `^8.0.0` (8.3.2 resolved), `vite-plus` 0.3.0 | `package.json` |
| JS extras | jQuery 3.7 + Select2 4 for one dropdown | `package.json:12-13` |
| Database | **MariaDB 11.7.2** (docker `db`), `utf8mb4_unicode_ci` | `docker-compose.yml:200-201`, verified via `SELECT VERSION()` |
| Cache | `database` driver (`cache`, `cache_locks` tables) | `.env:32`, `config/cache.php:18` |
| Session | `database` driver (`sessions` table) | `.env:28`, `config/session.php:21` |
| Queues | `database` driver configured; **zero jobs dispatched** | `config/queue.php:16`, `ARCHITECTURE.md:77-81` |
| Redis / Horizon | **NOT PRESENT** | no package, no config usage |
| Inertia / Vue / React | **NOT PRESENT** | no package |
| API routes | **NONE** — web UI only | `routes/web.php`, `routes/settings.php` |
| Tests | PHPUnit `^12.5.23` (**not Pest**), Paratest `^7.20` available, Dusk `^8.7` | `composer.json:24-25,30` |
| Static analysis | Larastan `^3.9` + PHPStan, **level 9** | `phpstan.neon:15`, `AGENTS.md:43` |
| Formatting | Pint `^1.27`, preset `laravel` | `pint.json` |
| CI/CD | GitHub Actions, one workflow, three jobs (`changes`, `ci`, `dusk`) | `.github/workflows/tests.yml` |
| Docker | `webdevops/php-apache-dev:8.4` + `Dockerfile.dusk`; MariaDB, phpMyAdmin, MailHog | `docker-compose.yml` |

## Checks Executed

All checks were run inside the project's Docker environment, per `AGENTS.md`. `composer setup`, `migrate:fresh`,
`migrate:refresh` and seeders were **never** run. `database-query` was used read-only (SELECT/SHOW/EXPLAIN only).

| Check | Command | Result |
|---|---|---|
| PHPUnit | `docker compose exec -e XDEBUG_MODE=off laravel13 php artisan test --compact` | **FAIL** — `4 failed, 233 passed (1168 assertions)` at HEAD; `1 failed, 238 passed (1208 assertions)` on the working tree |
| PHPStan | `docker compose exec -e XDEBUG_MODE=off laravel13 ./vendor/bin/phpstan analyse --no-progress` | **FAIL** — `1 error` |
| Pint | `docker compose exec -e XDEBUG_MODE=off laravel13 ./vendor/bin/pint --parallel --test` | **PASS** — 128 files |
| Composer audit | `docker compose exec -e XDEBUG_MODE=off laravel13 composer audit` | **PASS** — no advisories |
| npm audit | `docker compose run --rm --no-deps --entrypoint npm laravel13-npm audit --omit=dev` | **PASS** — 0 vulnerabilities |
| Schema inspection | Boost `database-schema`, `SHOW CREATE TABLE`, `SHOW INDEX`, `EXPLAIN` | Executed, read-only |
| Blade compilation | `blade.compiler->compileString()` in-container | Executed (used to test CLEAN-002, which it falsified) |
| Dusk | — | **NOT RUN** — no browser driver availability verified in this environment. Per the orchestrator's safety rules, not attempted. |
| `npm run build` | — | **NOT RUN** — regenerates `public/build`, i.e. modifies generated assets. |
| `npm run lint` / `npm run typecheck` | — | **NOT APPLICABLE** — no such scripts exist (`package.json:6-9` defines only `build` and `dev`). |

### A caveat on `composer ci:check`

`composer.json:63-66` defines `ci:check` → `test` → `test:prepare` (`config:clear`, `pint --test`,
`types:check`) **then** `php artisan test`. Because PHPStan runs first and fails, CI never reaches the suite. So the
four failing tests reported above are real but currently *masked* by `BUG-002`.

### Working tree note

Between 22:05 and 22:19 the repository gained 14 modified tracked files implementing a lazy-loaded epic-comments
JSON endpoint (`epics.comments.index`). All results above are labelled with which state they describe. `git show
HEAD:<path>` was used to confirm that `BUG-001` exists in the **committed** code, not only in the WIP:

```
$ git show HEAD:app/Http/Controllers/CustomerController.php | grep -n fragmentIf
29:        ])->fragmentIf($request->hasHeader('X-List-Fragment'), 'list-results');
```

and that the WIP fix (`Controller::listView()`) is the correct remedy. **Re-run the checks before acting** — the
tree is in flux.

## Critical Findings

None.

## High Findings

### BUG-001 — All nine list actions return a rendered string instead of a `View`, breaking four tests

Severity: HIGH
Category: Bugs / Correctness
File: app/Http/Controllers/CustomerController.php
Line: 29
Confidence: HIGH

Problem:

Every list `index()` action ends with `->fragmentIf($request->hasHeader('X-List-Fragment'), 'list-results')` and
declares its return type as `View|string`. `Illuminate\View\View::fragmentIf()` **always returns a rendered string**,
so the `View` arm of the union is unreachable and the response never carries view data.

Evidence:

```php
// vendor/laravel/framework/src/Illuminate/View/View.php:114-121  (framework source, immutable)
public function fragmentIf($boolean, $fragment)
{
    if (value($boolean)) {
        return $this->fragment($fragment);   // string
    }
    return $this->render();                  // string
}
```

Present in committed HEAD across all nine list actions — `git show HEAD:app/Http/Controllers/CustomerController.php`
line 29, and the same line in `CustomerInactiveController`, `CustomerTrashController`, `ProjectController`,
`ProjectInactiveController`, `ProjectTrashController`, `EpicController`, `EpicInactiveController`,
`EpicTrashController`.

Reproduced failure:

```
FAILED Tests\Feature\Epics\EpicCommentTest > ... : AssertionFailedError The response is not a view.
  at tests/Feature/Epics/EpicCommentTest.php:77
FAILED Tests\Feature\ResourceActivationTest > inac... : AssertionFailedError The response is not a view.
  at tests/Feature/ResourceActivationTest.php:372     (x3 data sets)

Tests: 4 failed, 233 passed (1168 assertions)
```

Both call sites use standard helpers that require a view: `TestResponse::viewData()` and
`TestResponse::assertViewHas()` → `assertView()`, which throws when the original content is not a view.

Impact:

- CI is red (and currently masked by `BUG-002`).
- `ResourceActivationTest.php:372-378` is the **only** place the inactive-list pagination invariant is asserted, so
  losing it removes real coverage.
- Blast radius exceeds the four tests: any future `assertViewHas`/`assertViewHasErrors` on a list route fails with a
  message that does not point at the controller.
- Rendering inside the controller means a view exception is thrown from the action rather than during response
  preparation, bypassing the framework's view-exception path.

Recommendation:

Return the `View` and only render the fragment when the header is present:

```php
$view = view('customers.list', $data);

return $request->hasHeader('X-List-Fragment')
    ? $view->fragment('list-results')
    : $view;
```

The WIP already introduces `Controller::listView()` doing exactly this — keep that shape, and pair it with `BUG-002`.
Do **not** "fix" the tests by asserting on response bodies; the view-data assertions are the correct contract.

## Medium Findings

### BUG-002 — PHPStan level 9 fails, halting CI before the test suite runs

Severity: MEDIUM
Category: Bugs / Correctness
File: app/Http/Controllers/Controller.php
Line: 20
Confidence: HIGH

Problem:

The new `Controller::listView(Request $request, string $view, array $data): View|string` types `$view` as plain
`string`, but `view()` expects `view-string|null`.

Evidence:

```
$ docker compose exec -e XDEBUG_MODE=off laravel13 ./vendor/bin/phpstan analyse --no-progress
  Line   app/Http/Controllers/Controller.php
  20     Parameter #1 $view of function view expects view-string|null, string given.
         🪪  argument.type
 [ERROR] Found 1 error
```

`composer.json:63-66` chains `ci:check` → `test:prepare` (which includes `@types:check`) → `@test`, so the pipeline
stops here.

Impact:

CI red, and it masks `BUG-001` and `BUG-003`.

Recommendation:

PHPDoc-only change, no runtime effect:

```php
/**
 * @param  view-string  $view
 * @param  array<string, mixed>  $data
 */
```

### BUG-003 — `EpicCommentController::index` violates the project's own architecture rule

Severity: MEDIUM
Category: Architecture conformance
File: app/Http/Controllers/EpicCommentController.php
Line: 14
Confidence: HIGH

Problem:

`tests/Unit/ArchitectureTest.php:27` lists `'index'` in `FORM_REQUEST_ENDPOINTS` and requires every controller
`index()` to declare a `FormRequest`. The new `EpicCommentController::index(Epic $epic): JsonResponse` reads **no**
user input — no query string, no body — so it declares none.

Evidence:

```
FAILED Tests\Unit\ArchitectureTest > input endpoints declare a form request
  App\Http\Controllers\EpicCommentController::index must declare a FormRequest
  at tests/Unit/ArchitectureTest.php:115
Tests: 1 failed, 238 passed (1208 assertions)
```

`ARCHITECTURE.md:145` (`http.instructions.md`) states the rule's purpose: "every input endpoint needs a FormRequest".
This endpoint is not an input endpoint, so the rule is over-broad rather than the code being wrong.

Impact:

CI red. And the architecture rule — a genuinely valuable guard — is now encoding an incorrect invariant.

Recommendation:

Rename the action to `show` and keep the route name, so the URL and every template reference stay stable:

```php
Route::get('epics/{epic}/comments', [EpicCommentController::class, 'show'])
    ->whereNumber('epic')
    ->name('epics.comments.index');
```

Do **not** add an empty Form Request to satisfy the test.

### BUS-001 — Inactive parents are offered in create/edit selects, contradicting the on-screen copy

Severity: MEDIUM
Category: Business Logic
File: app/Http/Controllers/ProjectController.php
Line: 28
Confidence: HIGH

Problem:

`ProjectController::index()` offers **every** non-deleted customer, `EpicController::index()` every non-deleted
project. Neither applies `where('active', true)`. The list views simultaneously display a callout promising the
opposite.

Evidence:

```php
// ProjectController.php:28
'availableCustomers' => Customer::query()->orderBy('name')->get(['id', 'name']),
// EpicController.php:28
'availableProjects' => Project::query()->with('customer')->orderBy('name')->get(['id','name','customer_id']),
```

```blade
{{-- resources/views/projects/list.blade.php:90-94 --}}
@if ($list['create'] && $availableCustomers->isEmpty())
    {{ __('No active customers are available. Create one from the project form.') }}
@endif
```

Proven live: `SELECT id, name, active FROM customers` returns `id=2, name='Bezero 2', active=0`, and
`projects.id=1` has `customer_id=2` — that inactive customer is selectable right now.

The list queries *do* filter (`ProjectListQuery.php:29` `where('projects.active', true)`), so lists and selects
disagree. Server-side validation does not close the gap either: `ProjectRequest.php:50` and `EpicRequest.php:51` use
`Rule::exists(...)->whereNull('deleted_at')`, which excludes trashed parents but says nothing about `active`.

Impact:

A user can attach a project to a customer they just deactivated, or an epic to an inactive project. Already recorded
as an accepted TODO in `todo.md`, which is why this is MEDIUM rather than HIGH.

Recommendation:

1. Filter the selects: `->where('active', true)`. Two lines; this alone fixes the copy/data mismatch.
2. For the edit case, merge the currently-selected parent back in so an inactive parent already attached stays
   visible — that is step 2 of the existing `todo.md` item.

Do not add pagination to these selects; at 5 rows that is pure cost.

### DB-001 — The MariaDB and SQLite uniqueness strategies are not semantically equivalent

Severity: MEDIUM
Category: Database
File: database/migrations/2026_09_25_000000_create_customers_table.php
Line: 27-40
Confidence: HIGH

Problem:

Each create-table migration implements "unique among non-deleted rows" twice, and the two mechanisms disagree on
collation:

```php
// MariaDB / MySQL branch
$table->string('active_name')->nullable()->storedAs('IF(deleted_at IS NULL, name, NULL)');
$table->unique('active_name');

// SQLite / PostgreSQL branch
DB::statement('CREATE UNIQUE INDEX customers_active_name_unique ON customers (name) WHERE deleted_at IS NULL');
```

The MariaDB index inherits the column collation — `utf8mb4_unicode_ci`, **case-insensitive**. The SQLite partial
index uses BINARY comparison.

Evidence:

```
SHOW CREATE TABLE customers;
-> `active_name` varchar(255) GENERATED ALWAYS AS (if(`deleted_at` is null,`name`,NULL)) STORED,
   UNIQUE KEY `customers_active_name_unique` (`active_name`)   ... COLLATE=utf8mb4_unicode_ci
```

Identical dual-branch structure in `2026_09_28_173607_create_projects_table.php:26-40` and
`2026_09_30_175706_create_epics_table.php:24-38`. The Form Request layer inherits the same asymmetry via
`Rule::unique` (`CustomerRequest.php:40-43`).

The two sibling models agree on the DB-enforcement half of the rule and differ only here. Note also
`ARCHITECTURE.md:50-51` claims SQLite is used for isolated tests, which is why this divergence is invisible —
`phpunit.xml:22` pins MySQL, so **the SQLite branch is never executed by any test**.

Impact:

`'Acme'` and `'acme'` collide on MariaDB and do not on SQLite. Since the suite runs only against MariaDB, a change to
the SQLite branch produces no test signal at all.

Recommendation:

Make both branches implement the same rule by adding `COLLATE NOCASE` to the SQLite partial index:

```sql
CREATE UNIQUE INDEX customers_active_name_unique ON customers (name COLLATE NOCASE) WHERE deleted_at IS NULL;
```

and correct `ARCHITECTURE.md:50-51` (see `ARCH-001`).

### ARCH-001 — `ARCHITECTURE.md` contradicts `phpunit.xml` about the test database

Severity: MEDIUM
Category: Architecture / Documentation drift
File: .github/docs/architecture/ARCHITECTURE.md
Line: 50-51
Confidence: HIGH

Problem:

```
Engine: MySQL-compatible MariaDB in Docker; SQLite is used for isolated in-memory tests.
```

The suite uses MySQL. `phpunit.xml:22-25` pins `DB_CONNECTION=mysql`, `DB_HOST=db`,
`DB_DATABASE=laravel_test`, and `docker/mysql/init/01-create-test-database.sql` creates that MariaDB database.

Evidence: quoted above; `SELECT VERSION()` on the live connection returns `11.7.2-MariaDB-ubu2404`.

Impact:

This is the documentation that makes `DB-001` invisible: a reader believes the SQLite branch is covered. It is the
root cause of a whole class of "untested driver branch" mistakes.

Recommendation:

Replace the sentence with:

> Engine: MySQL-compatible MariaDB in Docker, for development **and** tests (`laravel_test`). The migrations keep a
> SQLite/PostgreSQL branch for the unique-name indexes, but the suite runs against MariaDB only.

### OPS-002 — `phpunit.xml` env vars are not forced, so an ambient `DB_*` variable silently redirects the suite

Severity: MEDIUM
Category: Production / Test isolation
File: phpunit.xml
Line: 17-27
Confidence: HIGH

Problem:

```xml
<env name="DB_CONNECTION" value="mysql"/>
<env name="DB_HOST" value="db"/>
<env name="DB_DATABASE" value="laravel_test"/>
```

None carry `force="true"`. PHPUnit only sets an `<env>` when the variable is not already present.

Evidence — I executed the framework's own resolution logic
(`vendor/phpunit/phpunit/src/TextUI/Configuration/PhpHandler.php:134-149`) in-container:

```php
// CI sets DB_DATABASE=laravel at job level; phpunit.xml says laravel_test
foreach ([["DB_DATABASE","laravel_test"],["DB_HOST","db"],["DB_PORT","3306"]] as [$name,$value]) {
    $force = false;
    if ($force || getenv($name) === false) { putenv("{$name}={$value}"); }
    $value = getenv($name);
    if ($force || !isset($_ENV[$name])) { $_ENV[$name] = $value; }
}
echo getenv("DB_DATABASE");
// => laravel      (NOT laravel_test)
```

Impact:

A developer who exports `DB_DATABASE` and runs `php artisan test` will have `RefreshDatabase` migrate **that**
database. This is a data-loss footgun in the most destructive test path in the project. It also means local and CI
databases can silently differ — which is precisely what the dedicated `laravel_test` database exists to prevent.

Recommendation:

Add `force="true"` to the `DB_*` entries. No CI change is needed (the values are identical), and the footgun closes.

### DEP-005 — The compose helper services delete `composer.lock` and `package-lock.json`

Severity: MEDIUM
Category: Production / Developer safety
File: docker-compose.yml
Line: 70-76
Confidence: HIGH

Problem:

```yaml
laravel13-composer:
  profiles: ["composer"]
  command: bash -c "
      rm -Rf vendor
      && rm -f composer.lock
      && composer install --no-progress
      ..."
```

`./:/docker` is a bind mount of the repository, so `rm -f composer.lock` deletes the committed lockfile and
`composer install` regenerates it from whatever resolves that day. The npm services do the same at lines 107-108 and
129-130 (`rm -f package-lock.json && rm -Rf node_modules`).

Evidence: quoted above.

Impact:

Running `docker compose --profile composer run laravel13-composer` silently produces a different lockfile, which
surfaces as a huge unrelated diff and can move dependency versions unnoticed. This directly contradicts `AGENTS.md`:
"Do not add base folders or change dependencies without approval."

Recommendation:

Delete the two `rm -f *.lock` commands. If a clean regeneration is wanted, make it an explicit separate command.
Zero risk.

### OPS-001 — The `ci` job runs the suite against the application database, not `laravel_test`

Severity: MEDIUM
Category: Production / CI
File: .github/workflows/tests.yml
Line: 118
Confidence: HIGH

Problem:

The `ci` job runs `composer setup`, whose last step is `php artisan migrate --force`
(`composer.json:50-56`), against job-level `DB_DATABASE: laravel` — the *application* database. The suite then runs
`RefreshDatabase` against whatever it is pointed at. The MariaDB service only creates `laravel`
(`MYSQL_DATABASE: laravel`, line 87); only the `dusk` job creates `laravel_test` (line 209).

Evidence: `.github/workflows/tests.yml:118-120`, `composer.json:50-56`, `:65-70`, `:87`, `:209`.

Impact:

No production risk — ephemeral runner, throwaway service. But the non-Dusk job mutates the schema of the database it
just migrated, and a test that escapes its transaction would corrupt the next test rather than being isolated. It
also means `phpunit.xml`'s declared `laravel_test` is silently overridden (see `OPS-002`).

Recommendation:

Create `laravel_test` in the `ci` job's service (one `mysql -e` step, exactly as `dusk` does at lines 206-209) and set
`DB_DATABASE: laravel_test` in the job env. If the current behaviour is deliberate instead, remove the
`migrate --force` from that job and document why.

### SEC-003 — `authenticateUsing()` re-implements credential lookup instead of using the provider

Severity: MEDIUM
Category: Security
File: app/Providers/FortifyServiceProvider.php
Line: 44
Confidence: MEDIUM

Problem:

```php
Fortify::authenticateUsing(function (Request $request): ?User {
    $user = User::where(Fortify::username(), $request->input(Fortify::username()))
        ->where('active', true)->first();
    $provider = Auth::guard(config()->string('fortify.guard'))->getProvider();
    $credentials = $request->only('password');

    if (! $user || ! $provider->validateCredentials($user, $credentials)) {
        return null;
    }
    ...
```

The intent — blocking inactive users at login — is legitimate and documented (`ARCHITECTURE.md:36-38`). The callback
now owns *all* credential semantics and duplicates what the provider already provides.

Evidence:

- `vendor/laravel/fortify/src/Http/Controllers/AuthenticatedSessionController.php:73-89` — the pipeline still runs
  `EnsureLoginIsNotThrottled` (because `fortify.limiters.login` is set), so **rate limiting is not bypassed**
  (5/min per email+IP, `FortifyServiceProvider.php:126-132`).
- Returning `null` for both "unknown user" and "wrong password" does **not** leak user existence.
- The `Login` listener (`:62-81`) re-checks `active` and logs out + invalidates the session if the user was
  deactivated between credential check and session write — correct belt-and-braces, covered by
  `tests/Feature/Auth/InactiveUserTest.php:61,95`.
- The bypass is not optional: removing the closure would let inactive users log in, since the middleware only runs on
  *subsequent* web requests.

Impact:

No demonstrated bypass. This is maintenance risk: the closure silently bypasses `retrieveByCredentials()`, so a
custom user provider override would stop working.

Recommendation:

Route through the provider so credential retrieval stays overridable — behaviour-preserving, no new abstraction:

```php
$provider = Auth::guard(config()->string('fortify.guard'))->getProvider();
$user = $provider->retrieveByCredentials([Fortify::username() => $request->input(Fortify::username())]);

if (! $user instanceof User || ! $user->active || ! $provider->validateCredentials($user, $request->only('password'))) {
    return null;
}
```

Confidence is MEDIUM because I did not execute a modified provider (read-only review).

### SEC-004 — Policies read as if they enforce ownership but enforce nothing, and no test would catch a role change

Severity: MEDIUM
Category: Security
File: app/Policies/CustomerPolicy.php
Line: 10-59
Confidence: HIGH

Problem:

Every ability in all three policies returns `true` unconditionally:

```php
// app/Policies/CustomerPolicy.php — identical shape in ProjectPolicy.php and EpicPolicy.php
public function viewAny(User $user): bool { return true; }
public function delete(User $user, Customer $customer): bool { return true; }
public function forceDelete(User $user, Customer $customer): bool { return true; }
```

`routes/web.php:16` puts the whole resource surface behind `auth` + `verified`. So any verified user can read, edit,
deactivate, trash and permanently delete every record, and read every epic comment.

This is **documented intent**, not an oversight: `ARCHITECTURE.md:40-44` — "`CustomerPolicy` is the authorization
boundary ... The current product scope permits every authenticated verified user to perform these actions" — and
`http.instructions.md` says "do not add roles unasked".

Impact:

No vulnerability against the documented design. The risk is that `ARCHITECTURE.md` reads as though `CustomerPolicy`
is a meaningful boundary. If the product ever gains a second class of user, every policy silently permits everything
and **no test fails**.

Recommendation:

**Do not add roles** — explicitly out of scope. Make the risk visible instead: add a comment in each policy class
pointing at the architecture document, e.g.

```php
// Every authenticated verified user may act on any record.
// See ARCHITECTURE.md#authentication--authorization
```

Zero cost, and it prevents the assumption that ownership is enforced.

### CLEAN-001 — The three list views are ~90% identical

Severity: MEDIUM
Category: Maintainability
File: resources/views/epics/list.blade.php
Line: 1 (whole file)
Confidence: HIGH

Problem:

The Alpine state block is duplicated verbatim in three files, and ~80 lines of surrounding markup is copy-pasted per
resource — the `@php $initialForm` block, the whole `x-data`, and the modal wiring.

Evidence — the same block at `customers/list.blade.php:23-67`, `projects/list.blade.php:28-79`,
`epics/list.blade.php:40-94`:

```js
x-data="{
    form: @js($initialForm),
    confirmation: { action: '', method: 'DELETE', title: '', text: '', label: '', danger: false },
    storeUrl: @js(route('customers.store')),
    updateUrl: @js(route('customers.update', '__CUSTOMER__')),
    createCustomer() { ... }, editCustomer(customer) { ... }, confirmAction(action) { ... },
}"
```

Impact:

Every UX change to the drawer or dirty-tracking wiring must be made three times, and it has already drifted
(the parent-payload shape differs per resource).

Recommendation:

Extract **only the byte-identical parts**, following the precedent already set by `.github/instructions/views.instructions.md`
("Do not copy shared markup back into a view"). The shared pieces are already components
(`x-list.header`, `x-list.flash`, `x-list.search`, `x-list.table`, `x-list.row-actions`, `x-list.confirm-modal`,
`x-forms.tracked-resource`).

Do **not** build a generic list-page component — the column sets genuinely differ (3 / 5 / 7 columns) and
`ARCHITECTURE.md:122-123` rejects abstractions without a concrete problem.

### MAINT-001 — The uniqueness invariant lives in five places and is prose in two of them

Severity: MEDIUM
Category: Maintainability
File: .github/docs/architecture/ARCHITECTURE.md
Line: 60-63
Confidence: HIGH

Problem:

The app's most important invariant — "names unique among non-deleted records, including inactive ones; soft-deleted
names may be reused" — is expressed in five artefacts:

| Location | Form |
|---|---|
| `ARCHITECTURE.md:60-63` | prose |
| `database/migrations/2026_*.php:33-40` | generated column + unique index |
| `app/Http/Requests/*Request.php` | `Rule::unique(...)->whereNull('deleted_at')` — 3 copies |
| `app/Support/Database/UniqueConstraintViolation.php` | race absorber |
| `tests/Feature/**` | 9 race tests (3 resources × store/update/restore) |

The same fan-out applies to the parent-delete guard (prose + 4 controllers + 2 tests) and the `active` flag
(migration + 3 models + 2 queries + 6 controllers + 3 transformers + views + tests).

Evidence: the race tests are at `CustomerCrudTest.php:159,176`, `CustomerTrashTest.php:108`,
`ProjectCrudTest.php:114,136`, `ProjectTrashTest.php:145`, `EpicCrudTest.php:175,195`, `EpicTrashTest.php:159`.

Impact:

Not a defect — it is the honest cost of putting rules in the database, the requests and the tests, which is the right
design. The risk is **drift**, and it has already happened once: in `BUS-001`, two of the five places (the two select
queries) forgot the `active` flag.

Recommendation:

Convert the invariant from prose into an assertion. `tests/Unit/ArchitectureTest.php:29-59` already requires every
resource to ship a complete class set, and `tests/Unit/ModelSchemaParityTest.php` ties model attributes to schema —
add a sibling test asserting, for each domain model, that the Form Request's unique rule carries `whereNull('deleted_at')`
and that the migration defines a generated column.

### BUS-005 — Restore-conflict copy says "active" but the guard blocks on inactive records too

Severity: LOW
Category: Business Logic
File: app/Http/Controllers/CustomerTrashController.php
Line: 73-74
Confidence: HIGH

Problem:

```php
return to_route('customers.trash.index')
    ->with('error', __('Customer cannot be restored while another active customer uses this name.'));
```

The guard above it (`CustomerTrashController.php:35`) is `Customer::query()->where('name', $customer->name)->exists()`,
which excludes soft-deleted rows but **includes inactive** ones.

Evidence: lines 35 vs 74. Same wording at `ProjectTrashController.php:75` and `EpicTrashController.php:74`.
`ARCHITECTURE.md:61` confirms the code is right and the copy is wrong ("unique among non-deleted records, **including
inactive records**").

Impact:

A real scenario: an inactive customer (which the app can create — see `BUS-001`) blocks a restore, and the user is
told to look for an active one. All three resources share the wording, so all three mislead.

Recommendation:

Change to "another existing customer" in all three trash controllers and add the key to all four `lang/*.json`
files — `tests/Unit/Translations/TranslationFilesTest.php` enforces locale parity and will fail if one is missed.

## Low Findings

### CLEAN-005 — `EpicListQuery` repeats a nine-line ordering chain twice

Severity: LOW
Category: Maintainability
File: app/Queries/Epics/EpicListQuery.php
Line: 36-43
Confidence: HIGH

Problem:

`active()` (36-43) and `inactive()` (55-62) repeat the same nine `orderBy`/`orderByRaw` calls verbatim.
`ProjectListQuery.php:31-35,48-52` has the same shape.

Impact: Low — ordering is a stable UI decision and the copies are adjacent in the same class.

Recommendation: if extracted, a single `private function orderByDatesThenNames(Builder $query): Builder` on the
existing class. No new type.

### CONC-001 — `deactivate` / `reactivate` are read-modify-write with no guard

Severity: LOW
Category: Concurrency
File: app/Http/Controllers/CustomerInactiveController.php
Line: 31-32
Confidence: HIGH

Problem:

```php
$customer->active = false;
$customer->save();
```

`save()` issues `UPDATE ... SET active = ?, updated_at = ? WHERE id = ?` with no `active` predicate, so a concurrent
`reactivate` is silently overwritten. Present in all six `deactivate`/`reactivate` methods
(`CustomerInactiveController.php:31,40`, `ProjectInactiveController.php:32,41`, `EpicInactiveController.php:32,41`).

Impact: bounded — `active` has two reachable states, so a lost update always lands on a valid state. No data
corruption; only a possibly-surprising UI. No test covers it.

Recommendation: `Customer::whereKey($customer->getKey())->update(['active' => false]);` — atomic, no locks, no new
abstraction.

### CONC-002 — "Cannot delete a parent that has children" is check-then-act

Severity: LOW
Category: Concurrency
File: app/Http/Controllers/CustomerController.php
Line: 86-91
Confidence: HIGH

Problem:

```php
if ($customer->projects()->withTrashed()->exists()) { ...return...; }
$customer->delete();
```

A project created between the `EXISTS` and the `DELETE` breaks the invariant in `ARCHITECTURE.md:58,65`. The database
cannot save it: `delete()` is an `UPDATE`, and `projects_customer_id_foreign` is `RESTRICT` on *delete*, which an
`UPDATE` never triggers.

Evidence: `CustomerController.php:86`, `ProjectController.php:74`, `CustomerTrashController.php:61`,
`ProjectTrashController.php:62`. The force-delete path *is* FK-protected — the worst case there is an unhandled
`QueryException` (HTTP 500), not corruption.

Impact: requires two simultaneous requests on one record. Narrow.

Recommendation: wrap guard and delete in one `DB::transaction()` in the four controllers. Note
`tests/Unit/ArchitectureTest.php:67` currently forbids `DB::` in controllers — widen that rule to permit
`DB::transaction(` when this lands, deliberately rather than by workaround.

### BUS-006 — Comment redirect uses `back()`, leaving an untested fallback path

Severity: LOW
Category: Business Logic
File: app/Http/Controllers/EpicCommentController.php
Line: 17
Confidence: MEDIUM

Problem:

`return back(fallback: route('epics.index'))->with('commented_epic_id', $epic->id);` — the drawer only exists on the
epics index, so `back()`'s flexibility buys nothing, and the fallback branch is never exercised
(`test_comment_body_is_required` always supplies `->from(route('epics.index'))`).

Impact: low. A comment submitted without a referrer still saves; only the drawer fails to re-open
(`epics/list.blade.php:8-12,95`).

Recommendation: `return to_route('epics.index')` with the same session keys. Coordinate with the in-flight refactor.

### CLEAN-002 — `x-list.header` renders a raw Alpine expression from a prop

Severity: LOW
Category: Maintainability
File: resources/views/components/list/header.blade.php
Line: 33
Confidence: HIGH

Problem: `x-on:click="{{ $createClick }}"` — a prop that is an executable expression, unlike every other prop in the
component (which carries data). All three call sites pass literals (`create-click="createCustomer()"` etc.) and
Blade escapes the attribute, so there is no injection today.

Impact: none now; the risk is that the next caller passes a variable without realising this prop differs in kind.

Recommendation: document it in the PHPDoc (`@param string $createClick Alpine expression — must be a literal`) or
rename it to `$createClickExpression`. No behaviour change.

### DEP-001 — Linux-x64 native binaries pinned as direct `optionalDependencies`

Severity: MEDIUM
Category: Dependencies
File: package.json
Line: 21-25
Confidence: HIGH

Problem:

```json
"optionalDependencies": {
    "@laravel/multiplex": "^0.4.1",
    "@rollup/rollup-linux-x64-gnu": "4.9.5",
    "@tailwindcss/oxide-linux-x64-gnu": "^4.0.1",
    "lightningcss-linux-x64-gnu": "^1.29.1"
}
```

Three platform-specific binaries for linux-x64 declared as direct dependencies, one pinned to an exact version.
The lockfile already contains the full multi-platform matrix upstream publishes (`@tailwindcss/oxide` has
linux-{x64,x64-musl,arm64,arm64-musl,arm,arm-gnueabihf} and win32-{x64,arm64}; `lightningcss` likewise), verified by
enumerating `packages` keys.

Impact: `npm install` on macOS/Windows skips them harmlessly (they carry `os`/`cpu` constraints), but the exact pin on
`@rollup/rollup-linux-x64-gnu` will fight Vite's own resolution when Vite bumps its Rollup requirement. The lockfile
currently has **zero** occurrences of the string `rollup`, so this pin is the only source of that binary.

Recommendation: drop the three binaries and let the toolchains pull their own. Keep `@laravel/multiplex` — it is
genuinely optional and correctly declared.

### OBS-001 — List-refresh failures become unhandled promise rejections

Severity: LOW
Category: Observability
File: resources/js/app.js
Line: 478-481
Confidence: HIGH

Problem: `refreshList` re-throws a real failure from an `async` method whose four call sites (`:393,412,420,429`) never
`await` or `.catch()`. The user sees the list silently stop updating — no message, no retry.

Contrast the same file, which *does* surface errors: `initializeProjectCustomerSelect` sets
`form.customerCreateError` (`:344`), and the epic form has `data-test="epic-comments-error"`
(`epics/form.blade.php:89-90`). The list refresh is the outlier.

Impact: a failing refresh is undiagnosable for a non-developer. The happy path has a Dusk test
(`test_customer_list_can_be_searched_and_cleared`); the failure path has none.

Recommendation: dispatch a `list-error` event and render it in the existing `x-list.flash` region. ~5 lines.

### OPS-003 — MariaDB runs with an empty `sql_mode` while the app declares `'strict' => true`

Severity: LOW
Category: Production
File: docker-compose.yml
Line: 201
Confidence: HIGH

Problem: `command: --collation-server=utf8mb4_unicode_ci --default-authentication-plugin=mysql_native_password --sql-mode=""`
disables `STRICT_TRANS_TABLES`, while `config/database.php:60,80` set `'strict' => true`. Effective behaviour is the
lenient one: over-length strings truncate silently, invalid dates become zero-dates.

Impact: dev and production can disagree on data integrity. Realistic risk here is low — `name` is `varchar(255)`
capped at 255 by every Form Request, `body` is `text` capped at 5000.

Recommendation: drop `--sql-mode=""`, or set it to
`STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION`. One line.

### OPS-004 — App container publishes port 80 on all interfaces with `APP_DEBUG=true`

Severity: LOW
Category: Production
File: docker-compose.yml
Line: 12
Confidence: HIGH

Problem: `- "80:80"` publishes to `0.0.0.0` while `.env:6` sets `APP_DEBUG=true`, exposing the debug error page
(frames, paths, config values, app key) to anyone on the same network. The other services are correctly loopback-bound:
`db` → `127.0.0.1:13306` (line 219), `myadmin` → `127.0.0.1:4000` (line 224), `mailhog` → `127.0.0.1` (line 235).

Impact: local/shared-network exposure only. `.env` is gitignored, so this is not a committed-secret issue.

Recommendation: `"127.0.0.1:80:80"` to match the rest of the file.

### ARCH-002 — The `active` flag's meaning is stated only in query method names

Severity: MEDIUM
Category: Architecture / Cohesion
File: app/Models/Customer.php
Line: 26-40
Confidence: MEDIUM

Problem: `active` touches at least seven layers — migration, 3 models, 2 query objects, 6 controllers, 3 transformers,
views — and there is no single statement of what it means. Crucially, it is absent from **every** Form Request's
rules, because it is correctly non-mass-assignable (`#[Fillable]` excludes it everywhere; `ModelSchemaParityTest.php:28-42`
lists it in `SYSTEM_COLUMNS`).

Evidence: `2026_10_02_180040_add_active...php:16-28`; `Customer.php:26-40`, `Project.php:28-43`, `Epic.php:27-42`;
`CustomerListQuery.php:22,36`, `ProjectListQuery.php:29,47`; and — the gap — `ProjectController.php:28`,
`EpicController.php:28`, which handle the flag not at all.

Impact: the design is **safe** (mass assignment cannot reach it) but **implicit**. That implicitness is exactly why
`BUS-001` was possible.

Recommendation: no abstraction — `ARCHITECTURE.md:122-123` forbids a `HasActive` trait for three models and one
boolean. Add one sentence to the Database section stating that `active` is orthogonal to `deleted_at` and is never
mass-assignable, then fix `BUS-001`.

### FE-001 — Inline customer creation trusts a server error shape it never validates

Severity: LOW
Category: Frontend
File: resources/js/app.js
Line: 330-344
Confidence: MEDIUM

Problem: after the `fetch`, the code assumes the body is JSON and that failures carry a usable message. Both hold
today (`CustomerController.php:41-48` returns `{message, errors:{name:[…]}}` with 409; a `ValidationException` under
`Accept: application/json` does the same via `bootstrap/app.php:24-26`). The happy path has a Dusk test
(`tests/Browser/Projects/ProjectCustomerSelectTest.php`).

Impact: if the endpoint ever returned HTML (a 500 page, a redirect), `response.json()` rejects with a non-user-facing
`SyntaxError` and the user sees the `"Unable to create customer."` fallback — graceful, not broken. No XSS: the
message renders via `x-text` (`projects/form.blade.php:49-50`).

Note the asymmetry that makes this worth flagging: only `CustomerController::store` has a JSON branch
(`ProjectController.php:33` and `EpicController.php:33` return `RedirectResponse` only). Reusing this Select2 pattern
for a project would break with a JSON parse error.

Recommendation: keep the fallback; optionally distinguish transport from validation failure so the UI can say which.

### MAINT-005 — No seed data, so a fresh clone shows an empty application

Severity: LOW
Category: Maintainability
File: database/seeders/DatabaseSeeder.php
Line: —
Confidence: MEDIUM

Problem: `DatabaseSeeder` is the stock skeleton seeder. The live database's rows (`Bezero1`, `Bezero 2`, `galvez`,
`itarte`, `galvez itarte`, `project1`, `project2`, one deactivated, one project attached) were produced by clicking
through the UI, not by a seeder — and `migrate:fresh` destroys that state.

Impact: a new developer gets no example of a customer→project→epic chain, and no epic comments, which is the feature
with the most moving parts.

Recommendation: optionally extend the existing `CustomerSeeder` using the existing factories. Optional — `AGENTS.md`
says not to add things unasked.

## Informational Findings

Recorded so they are not re-investigated. None require action.

- **No security vulnerabilities.** `composer audit` and `npm audit` are clean (both executed).
  No SQL injection (all values bound; the only `orderByRaw` calls are constant literals —
  `EpicListQuery.php:40,42,53,54`), no XSS (no `v-html`, no `{!! !!}`, no `innerHTML` anywhere in `resources/views`;
  all user data via `x-text` or escaped Blade), no CSRF gap (every mutating form has `@csrf`; the inline-create
  `fetch` sends `X-CSRF-TOKEN`, `app.js:323`), no command injection (`chisel.php` uses
  `Symfony\Component\Process\Process` with an **array** command, so no shell is invoked), no path traversal or file
  upload (no file handling in `app/`), no SSRF (no outbound HTTP).
- **`.env` is correctly gitignored.** `git ls-files | grep -i env` returns only `.env.example`;
  `.gitignore:8-10` covers `.env`, `.env.backup`, `.env.production`. The `APP_KEY` at `.env:4` is a live local
  development key and has not leaked.
- **Session and cookie hardening is correct.** `config/session.php:172` `'secure' => env('SESSION_SECURE_COOKIE',
  env('APP_ENV') === 'production')`, `:185` `http_only => true`, `:202` `same_site => 'lax'`.
- **Mass assignment cannot reach `active`.** `#[Fillable]` excludes it on all five models; proven by
  `tests/Feature/Auth/InactiveUserTest.php:16-32` and `ModelSchemaParityTest.php:28-42`.
- **CI is hardened.** `permissions: contents: read`, `persist-credentials: false`, all four actions pinned to full
  commit SHAs, no `pull_request_target`, no secret interpolation into `run:` blocks.
- **Destructive DB commands blocked in production.** `AppServiceProvider.php:36-38`
  `DB::prohibitDestructiveCommands(app()->isProduction())`, alongside `Date::use(CarbonImmutable::class)` (line 34)
  and a production `Password::defaults()` policy (lines 40-48).
- **Health endpoint used correctly.** `bootstrap/app.php:14` registers `/up`; `tests.yml:222-229` polls it in a
  30-attempt readiness loop.
- **Duplicate-name races are tested for all 9 combinations.** `CustomerCrudTest.php:159,176`,
  `CustomerTrashTest.php:108`, `ProjectCrudTest.php:114,136`, `ProjectTrashTest.php:145`,
  `EpicCrudTest.php:175,195`, `EpicTrashTest.php:159` — each injects a competing `INSERT` via `DB::listen`. Three
  tests additionally assert a *non*-unique `QueryException` is rethrown untouched
  (`CustomerCrudTest.php:198`, `ProjectCrudTest.php:94`, `EpicCrudTest.php:94`).
- **Four meta-tests make the architecture rules mechanical.** `ModelSchemaParityTest.php:52-110` (fillable ↔ schema ↔
  casts), `ArchitectureTest.php:62-86` (thin controllers), `:123-140` (view conventions),
  `ValidationCoverageTest.php:33-87` (fillable ↔ rules, and controller input reads ↔ rules). This is why
  `AGENTS.md`'s "a new column needs three things together" rule holds in practice.
- **`AGENTS.md:43` says phpstan level 9 and `phpstan.neon:15` is level 9.** Verified in agreement — recorded because it
  looked like a contradiction.
- **Documentation drift, `active_name` storage.** The generated columns are `STORED`
  (`SHOW CREATE TABLE customers`), duplicating up to 255 chars per row. This is not a free choice — a `VIRTUAL`
  column cannot carry a UNIQUE index in MySQL 8/MariaDB without an explicit prefix length. No action.
- **EXPLAIN evidence recorded, no action.** The epics list query is `type: ALL` with `filesort` (no index on
  `deleted_at`/`active`; only `PRIMARY` and the unique index exist), and `epic_comments` has no `(epic_id,
  created_at)` index. With `customers` = 5, `projects` = 2, `epics` = 1 rows this costs nothing. Recorded so the
  evidence exists if the data grows; adding indexes now would be speculative.
- **`cache`, `cache_locks`, `jobs`, `failed_jobs` tables are empty.** Zero `Cache::` calls and zero dispatched jobs
  in `app/`. Do not build dashboards or alerts on these tables today.
- **`.github/instructions/` is four short, specific, enforced files** (`http`, `models`, `tests`, `views`), each
  naming concrete classes, and each rule is backed by a test. Correct size and shape.
- **`ARCHITECTURE.md`'s "Deliberately Rejected Patterns" section is load-bearing.** It is why this review rejects
  repositories, services, DTOs, traits, base models, optimistic locking, caching, and observability stacks instead of
  recommending them.

## Security

Summary of the security posture, with the two accepted items called out.

**No critical or high vulnerability was found.** The full OWASP sweep produced no exploitable issue: the app has no
SQL injection surface, no XSS surface, no CSRF gap, no file upload, no outbound HTTP, and no deserialisation of
user input. Authorization is uniformly permissive, but that is documented product scope (`ARCHITECTURE.md:40-44`) and
`http.instructions.md` explicitly instructs not to add roles.

The two MEDIUM items are both "this will surprise a future maintainer" rather than "this is exploitable":

- `SEC-003` — the custom `authenticateUsing()` callback owns all credential semantics. Rate limiting is *not*
  bypassed (the Fortify pipeline still runs `EnsureLoginIsNotThrottled`), and no login bypass exists — the closure is
  in fact **required**, because the `active` middleware only runs on subsequent web requests. The recommendation is a
  behaviour-preserving change to route through the provider's `retrieveByCredentials()`.
- `SEC-004` — the policies enforce nothing while reading as though they enforce ownership, and **no test would fail**
  if the product later gained a second class of user. The recommendation is a comment, not a role system.

Hardening items (LOW, all environment-specific): bind the app port to loopback (`OPS-004`), align local `sql_mode`
with production (`OPS-003`), and remove the lockfile deletions (`DEP-005`).

## Bugs / Correctness

Three defects, all reproducible, all currently breaking CI.

| ID | Defect | Symptom | State |
|---|---|---|---|
| `BUG-001` | 9 list actions return a string, not a `View` | 4 failing tests; `assertViewHas`/`viewData` unusable on list routes | confirmed at HEAD `781ccbb` |
| `BUG-002` | `phpstan analyse` level 9 error | CI stops before running the suite, masking `BUG-001`/`BUG-003` | reproduced twice |
| `BUG-003` | `EpicCommentController::index` trips the architecture rule | 1 failing test | reproduced |

Plus two business-logic corrections (`BUS-001` inactive parents selectable, `BUS-005` misleading restore copy), both
provable against live data and against `ARCHITECTURE.md` itself.

Not bugs, verified and rejected: the two date rules differ between `ProjectRequest.php:46` (`after_or_equal`) and
`EpicRequest.php:47` (`after`) — `ARCHITECTURE.md:64` vs `:66-67` require exactly that. The three list views' identical
Form Requests are correct, because each must reference its own policy.

## Database

The schema is in good shape and the central decision — enforcing name uniqueness in the database via a stored
generated column — is the right one. It makes correctness independent of application code and is what allows
`UniqueConstraintViolation` to be a complete answer to a validation race rather than a best-effort patch.

Confirmed issues:

- `DB-001` (MEDIUM) — the MariaDB and SQLite branches of the uniqueness index disagree on collation
  (case-insensitive vs. binary), and the SQLite branch is never executed by the suite, so changes to it produce no
  test signal.
- `ARCH-001` (MEDIUM) — the documentation asserting SQLite is the test database is what makes `DB-001` invisible.

Verified-correct, recorded to prevent "improvement":

- `RESTRICT` on `projects.customer_id` and `epics.project_id`, `CASCADE` on `epic_comments.epic_id`, `SET NULL` on
  `epic_comments.user_id` — together these give exactly the documented behaviour
  (`ARCHITECTURE.md:69-70`), verified by `SHOW CREATE TABLE`.
- `EpicListQuery`'s `INNER` joins are safe **only** because the parent FKs are `RESTRICT`; the joins do not apply the
  SoftDeletes scope, which is intentional and consistent with `Epic::project()->withTrashed()`. Worth one line of
  documentation so nobody relaxes the FK later.
- No destructive migrations; the only `down()` that loses data is the `active` column rollback, which is inherent to
  the feature.

## Performance

**No performance finding rises above LOW, and none is worth acting on now.** That is a deliberate conclusion, not an
omission.

Measured scale: `customers` = 5 rows, `projects` = 2, `epics` = 1, `PER_PAGE` = 5. The one query with a genuine
scaling cliff is `ProjectController.php:28` / `EpicController.php:28` loading whole tables for dropdowns — but its
actual defect is the missing `active` filter, which is `BUS-001`, not the row count.

Verified-clean, recorded so they are not "optimised":

- **No N+1** on any list page. `EpicListQuery::withProjectAndCustomer()` eager-loads `project.customer`;
  `ProjectListQuery::withCustomer()` eager-loads `customer`; the transformers touch only those.
  `CustomerListQuery.php:23` and `ProjectListQuery.php:30` use `withExists([... withoutGlobalScope(SoftDeletingScope)])`
  — one correlated subquery, not a lazy collection.
- **The eager-loaded comment limit is per-epic, not global.** `HasOneOrMany::limit()` (vendor, line 557) branches on
  `$this->parent->exists`; during eager loading `Builder::getRelation()` uses `newInstance()` (line 1004), so the
  `groupLimit` windowed path is taken. Correct, and tested.
- **Search escaping is correct** — `ListQueryBase.php:31` `addcslashes($search, '%_\\')` neutralises user-supplied
  wildcards, and all values are bound.
- **No caching is warranted.** Zero `Cache::` calls in `app/`; the `cache` table is empty. Adding cache for a
  5-row table is pure cost.
- **The list-fragment refresh aborts in-flight requests** (`app.js:447,449`) so keystroke-driven fetches do not pile up.

Recorded as evidence-only (no action): `EXPLAIN` shows the epics list as `type: ALL` with `filesort`, and
`epic_comments` lacks a `(epic_id, created_at)` index.

## Architecture

Proportional and mostly correct. The `Controller → FormRequest → Policy → ListQuery → ListTransformer → View` shape is
consistent across all three resources, and it is *machine-enforced* — `ArchitectureTest.php:29-59` fails if a new
resource does not ship all nine classes.

What is right, and should be defended:

- `app/Queries` + `app/Transformers` separation from controllers (`ARCHITECTURE.md:102-103`), enforced by
  `ArchitectureTest.php:62-86`, which forbids `DB::`, `Schema::`, `->paginate(`, raw `$request->input()` and base
  `Request` type-hints in controllers.
- The "Deliberately Rejected Patterns" section (`ARCHITECTURE.md:118-124`) actively shapes this review — it is the
  documented reason to reject repositories, services, DTOs, traits and base models.
- Minimal `config/` (11 files, no `view.php`/`hashing.php`/`broadcasting.php`) with the two files that matter —
  `fortify.php` and `session.php` — published and customised. Correct.

Where it leaks: `BUG-003` shows the architecture rule set is slightly over-broad (it guards a method name rather than
the presence of input), and `ARCH-002` shows a cross-cutting flag with no single owner.

## Testing

Strong for the size of the application, and currently red.

Coverage worth calling out: 9/9 duplicate-name race combinations (each injecting a real competing `INSERT` through
`DB::listen`, which is harder to fake than most mocks), 3 tests asserting a *non*-unique `QueryException` propagates
untouched, and four meta-tests that turn architecture rules into assertions. `InactiveUserTest` covers all five
inactive-user paths including passkeys and remember-me.

Gaps, in order of value:

- `BUG-001` currently costs the inactive-list pagination assertions (`ResourceActivationTest.php:372-378`).
- No test asserts that a *failing* list refresh surfaces anything to the user (`OBS-001`).
- `EpicCommentController::index` has no test of its own until the WIP refactor lands.
- `phpunit.xml` does not force its `DB_*` vars (`OPS-002`), so the suite can silently run against the wrong database.

Deliberately not findings: Dusk is excluded from `phpunit.xml` and run in a separate job (documented at
`ARCHITECTURE.md:113-114`) — I did **not** run Dusk, so it is recorded as NOT RUN rather than passing. There is no
coverage threshold, which is appropriate when the behaviour, not the line count, is what is under test. Paratest is
available but unused in CI because the database is shared — correct.

## Production

Nothing is deployed: no Kubernetes/Helm/Fly/Railway/Forge config, no `Procfile`, no reverse-proxy config.
`ARCHITECTURE.md:127-133` mentions only environment and credentials. Findings are therefore scoped to CI and the
local Docker environment.

CI: **red**, for three reasons (`BUG-001`, `BUG-002`, `BUG-003`), and the ordering hides two of them. The `ci` job also
runs the suite against the application database rather than `laravel_test` (`OPS-001`), and `phpunit.xml` does not
force its environment (`OPS-002`). On the positive side, the workflow is well hardened — read-only permissions,
no persisted credentials, SHA-pinned actions.

Local Docker: the app port is published on all interfaces with `APP_DEBUG=true` (`OPS-004`); MariaDB runs with an
empty `sql_mode` that diverges from production defaults (`OPS-003`); and the `composer`/`npm` profile services delete
the committed lockfiles (`DEP-005`) — the last one directly contradicts `AGENTS.md`.

## Maintainability

The project's documentation and zone instructions are unusually good — four short `applyTo`-scoped instruction files
whose every rule is backed by a test, and a `ARCHITECTURE.md` that records both rejected patterns and known technical
debt.

The main friction is duplication in the views (`CLEAN-001`) and the fact that the app's core invariant is expressed
five times with two of those being prose (`MAINT-001`) — and drift has already occurred once, in `BUS-001`. The two
documentation drifts (`ARCH-001`, and the epic-comments description in `ARCHITECTURE.md:26-27` once the WIP lands)
are the cheapest items on the list.

One process gap worth naming: there is **no frontend linter or typechecker**. `package.json` defines only `build` and
`dev`, and `pint` is PHP-only. Nothing catches a JS or Blade mistake at commit time.

## Rejected Findings

Every item below was raised by a specialist and dropped during the devil's advocate pass. Recorded so they are not
re-investigated. Full reasoning in `.github/reviews/2026-10-02/devils-advocate.md`.

**Falsified by experiment**

- *"`x-list.header`'s multi-line PHP concatenation embeds a newline in the Flux modal name, breaking the create
  button."* I compiled the template, misread the output, and inferred a literal newline. **Wrong.** PHP's `.` ignores
  whitespace between operands. Executed proof: `var_dump($name)` → `string(13) "customer-form"`, and
  `$name === "customer-form"` → `bool(true)`. The formatting is ugly; the behaviour is correct.

**Duplicates (merged)**

- `LAR-002` / `TEST-001` → one finding (`BUG-002`).
- `LAR-001` / `TEST-002` → one finding (`BUG-001`).
- `PERF-002` / `BUS-001` → merged into `BUS-001`; the unbounded-load half was dropped as irrelevant at 5 rows.

**Style / personal preference**

- *Extract a generic list-page component.* The column sets genuinely differ (3/5/7); the shared parts are already
  components; `ARCHITECTURE.md:122-123` forbids abstractions without a concrete problem. `CLEAN-001` was rewritten to
  recommend extracting **only** the byte-identical Alpine block.
- *Consolidate the 9 `UniqueConstraintViolation` call sites.* The two styles are not duplication — restore needs a
  different response than store/update. A trait or abstract controller is explicitly forbidden. Demoted MEDIUM → LOW
  and removed from the action plan.
- *Missing `config/view.php`, `config/hashing.php`.* Optional in Laravel 11+ with framework defaults. Pure opinion.
- *`LAR-004`: only `CustomerController::store` has a JSON branch.* Real asymmetry, but only Customers are created
  inline from the UI, so it is harmless today. Kept as INFO, no action; the risk is recorded in `FE-001`.

**Pattern-driven (rejected outright)**

- *Add Sentry/Bugsnag/OpenTelemetry.* No deployment target is declared anywhere in the repository.
- *Add structured JSON logging.* No log consumer to emit JSON for.
- *Add uptime monitoring / alerting.* `failed_jobs` and `cache` are provably empty — nothing to alert on, and an
  empty table is not a health signal.
- *Add distributed caching / Redis.* Redis appears only as unused `.env` boilerplate; there is no `Cache::` call.
- *Add optimistic locking on `updated_at`.* Would surface conflicts to users in an app where records are edited by
  one person at a time — reducing usability without solving a real problem.
- *Add a `HasActive` trait / base model.* Three models, one boolean; forbidden by `ARCHITECTURE.md:122-123`.
- *Add trigram/full-text index for search.* Five rows.

**Severity challenged and reduced**

- `SEC-001` (`.env` APP_KEY), HIGH → **dropped to a hardening note**. `git ls-files` proves `.env` is untracked; a
  developer's local dev key is not a HIGH finding, and the security agent's own rules forbid exaggerating severity.
  The actionable half is `OPS-004`.
- `DB-002` (`STORED` generated column wastes space) → INFO. Not even a free choice: a `VIRTUAL` column cannot carry a
  UNIQUE index in MySQL 8/MariaDB without a prefix length.
- `PERF-005` (leading-wildcard `LIKE`) → INFO, no action.
- `FE-005` (jQuery/Select2 in the main bundle) → kept LOW with "do not do this now", and **excluded from the action
  plan** because I did not measure bundle size.
- `OBS-002` (no request correlation), `MAINT-005` (no seed data), `DEP-004` ("verify dependabot covers both
  ecosystems" — I did not read the file) → removed as unsupported or non-defects.

## Action Plan

Ordered by impact. No aesthetic items.

### P0 — CI is red; nothing else can be verified until these land

1. **`BUG-001`** — return the `View`, not a rendered string, from the nine list `index()` actions. Keep the WIP's
   `Controller::listView()` shape. *Restores 4 tests and restores the inactive-list pagination coverage.*
2. **`BUG-002`** — add `@param view-string $view` to `Controller::listView()`. *One line; unblocks PHPStan, which
   currently stops CI before the suite runs.*
3. **`BUG-003`** — rename `EpicCommentController::index` to `show`, keeping the route name `epics.comments.index`.
   *Keeps the architecture rule meaningful instead of weakening it.*

Order matters: 2 must land with 1 or CI stays red for a different reason.

### P1 — correctness gaps a user can hit today

4. **`BUS-001`** — filter the parent dropdowns by `active`; the empty-state copy already promises it. Live today:
   customer `Bezero 2` (inactive) is selectable on the project form.
5. **`OPS-002`** — add `force="true"` to the `DB_*` entries in `phpunit.xml`, so an ambient variable cannot redirect
   `RefreshDatabase` at a database the developer cares about.
6. **`BUS-005`** — correct the "another active X uses this name" copy in the three trash controllers, in all four
   locales (the parity test will enforce that).
7. **`OPS-001`** — create `laravel_test` in the `ci` job and point it there, so the non-Dusk suite is genuinely
   isolated from the application database.

### P2 — keep it maintainable

8. **`DEP-005`** — stop deleting `composer.lock` / `package-lock.json` in the compose helper services. Zero risk, and
   it protects the lockfiles `AGENTS.md` says not to touch.
9. **`CLEAN-001`** — extract the duplicated Alpine `x-data` block from the three list views. Narrow scope only; no
   generic list-page component.
10. **`MAINT-001`** — turn the uniqueness invariant into an assertion instead of prose.
11. **`ARCH-001`** — fix the test-database sentence in `ARCHITECTURE.md` (this is what hides `DB-001`).
    Then **`DB-001`** — add `COLLATE NOCASE` to the SQLite partial index so both branches implement the same rule.
12. **`ARCH-002`** — one sentence in `ARCHITECTURE.md` stating that `active` is orthogonal to `deleted_at` and is
    never mass-assignable.
13. **`SEC-004`** — add the "enforces nothing by design" comment to the three policy classes, with a link to
    `ARCHITECTURE.md`. Prevents a future role change from passing every test.
14. **`OBS-001`** — surface list-refresh failures in the existing flash region instead of throwing into an unhandled
    rejection.
15. **`SEC-003`** — route the `authenticateUsing` lookup through `$provider->retrieveByCredentials()` so credential
    retrieval stays overridable. Behaviour-preserving.
16. **`BUS-006`** — make the comment redirect unconditional, removing an untested fallback. Coordinate with the
    in-flight refactor.

### P3 — when convenient

17. **`OPS-004`** — bind the app port to `127.0.0.1:80:80`, matching the other services.
18. **`OPS-003`** — drop `--sql-mode=""` from the MariaDB service so local matches production defaults.
19. **`DEP-001`** — remove the three pinned linux-x64 binaries from `optionalDependencies`.
20. **`CONC-001`** — make `deactivate`/`reactivate` atomic single-query updates.
21. **`CONC-002`** — wrap the parent-delete guard and the delete in one transaction; widen
    `ArchitectureTest.php:67` to permit `DB::transaction(` deliberately.
22. **`CLEAN-005`** — extract the duplicated ordering chain in `EpicListQuery`.
23. **`FE-001`** — distinguish a transport failure from a validation failure in the inline-create error path.
24. **`CLEAN-002`** — document that `$createClick` is an Alpine expression, not data.
25. **`MAINT-005`** — optionally seed one customer → project → epic chain with a comment.

### Explicitly not recommended

Adding an error tracker, structured logging, monitoring/alerting, Redis or caching; introducing repositories,
services, DTOs, traits or base models; a generic list-page component; optimistic locking; trigram or full-text search
indexes; refactoring the three duplicate-name call sites. Each was raised, challenged and rejected — reasons in
[Rejected Findings](#rejected-findings) and in `.github/reviews/2026-10-02/devils-advocate.md`.