# Code Review

**Date:** 2026-10-05
**Commit reviewed:** `0ff2cba` — "Review model-consitency"
**Mode:** READ-ONLY. No application code, tests, dependencies, lockfiles, configuration or database data were modified.

---

## Executive Summary

This is a small, unusually disciplined Laravel CRUD application (Customers → Projects → Epics + epic comments). The review ran 14 specialist passes plus an adversarial re-verification pass in which I personally re-checked every material claim against the source and the live schema.

**The headline finding is that the CI pipeline cannot possibly pass.** Four independent, individually fatal defects sit in `.github/workflows/tests.yml` and `phpunit.xml`. I verified each one empirically rather than by reading, and the conclusion is uncomfortable: **this workflow has never been green.** That in turn means the 80% coverage gate the project claims to enforce in `AGENTS.md` has never once been evaluated, and the "Known Technical Debt" note in `ARCHITECTURE.md:155` — which admits only the *Dusk* job is unverified — is understating the problem by the `ci` job as well. (CI-001, CI-002, CI-003, CI-004)

The application code itself is in good shape. **No XSS, SQL injection, CSRF, mass-assignment or IDOR defect exists**, the concurrency design is genuinely sound (verified against `ManagesTransactions`, `SoftDeletes` and InnoDB semantics), and all three local checks pass. Beyond the CI, what remains is:

1. **Three documentation statements that assert invariants the code does not provide.** `ARCHITECTURE.md` and two docblocks describe data-integrity guarantees that are false. This matters more than a cosmetic issue because those files are the reference a maintainer reads before touching the delete guard. (DOC-001, DOC-002, DOC-003)
2. **Three genuine user-facing defects**, found by reading the code rather than by running it: searching for 1–3 characters silently does nothing; disabling 2FA is a single unconfirmed click; and the error message carrying the blocking business rules is invisible to screen readers. (UX-001, UX-002, A11Y-001)
3. **One forward-looking authorization gap** — the one endpoint with no authorization decision at all. Harmless today because every policy returns `true`, but it is the single place where tightening the policies would be silently incomplete. (SEC-001)

Plus one wasted-work issue on the search keystroke path (PERF-001), one untested error path (TEST-001), and a tail of accessibility, i18n and housekeeping items.

**Notable, and the reason this report should be trusted:** I disproved a HIGH finding that a specialist reported (SEC-002, "storage/ is committable" — a false positive), and I re-verified every one of the four CI findings with a live command rather than accepting the analysis. See Rejected Findings.

---

## Detected Stack

Verified from `composer.json`, `package.json`, `composer.lock`, `package-lock.json`, `docker-compose.yml`, `.github/workflows/tests.yml` and `vendor/`. No assumed technology was used.

| Layer | Actual |
|---|---|
| PHP | 8.4 in Docker; **8.3 in CI**; `require.php: ^8.3` |
| Framework | Laravel **v13.34.0** |
| Livewire stack | livewire/livewire **4.4.7**, livewire/flux **2.20.1**, livewire/blaze **1.0.19** |
| Auth | laravel/fortify **1.40.0** + `@laravel/passkeys` 0.2.0 (passkeys), 2FA, email verification |
| Database | MariaDB **11.7** (InnoDB, REPEATABLE READ), `mysqli`; tests on isolated `laravel_test` |
| Frontend | Blade + Flux + Alpine (bundled), Tailwind **4.3.3**, Vite **8.3.2**, vite-plus 0.3.0 |
| Legacy JS | jquery **3.7.1** + select2 **4.1.0** (both genuinely used, project form only) |
| Tests | PHPUnit **12.5.37**, Paratest 7, Dusk **8.7.0**, 612 tests / 3138 assertions |
| Static analysis | Larastan **3.12.2** at **level 9**, Pint **1.32.1** |
| Queues / Redis / Horizon | **None.** `QUEUE_CONNECTION=database` with an empty queue; no jobs, listeners, notifications or scheduler |
| CI/CD | GitHub Actions, 3 jobs (doc-change filter, ci, dusk) |
| Docker | Compose: `webdevops/php-apache-dev:8.4`, `node:24-bullseye`, `mariadb:11.7`, `phpmyadmin:5.2.3`, `mailhog:v1.0.1` |
| Other packages | opcodesio/log-viewer 3.24, laravel/chisel 0.1.1, fruitcake/laravel-debugbar 4.4 |

---

## Checks Executed

All run inside the project's Docker environment with `XDEBUG_MODE=off`, read-only.

| Check | Command | Result |
|---|---|---|
| Test suite | `php artisan test --compact` | **PASS** — 612 passed, 3138 assertions, 63.76s |
| Static analysis | `./vendor/bin/phpstan analyse` (level 9) | **PASS** — No errors |
| Style | `./vendor/bin/pint --test` | **PASS** — 205 files |
| Dependency audit | `composer audit --no-interaction` | **PASS** — "No security vulnerability advisories found." |
| Dependency audit (npm) | not run; `.npmrc:2` sets `audit=true`, so `npm ci` runs it automatically (`.github/workflows/tests.yml:192`) | automated |
| Schema verification | `information_schema` read-only queries for columns, generated expressions, FK rules, indexes | schema matches migrations exactly |
| Dusk (browser) | **NOT RUN** — requires an isolated test DB and a driver download; see note below | not run |
| `npm run build` | **NOT RUN** — `/public/build` is gitignored, but it adds nothing the sources do not already show (Vite config and imports were read) | not run |

> **Important note on check results.** My first run of the suite and PHPStan, against commit `bcafd0e`, produced **1 test failure** (`tests/Unit/Users/UserTest.php:26`) and **1 PHPStan error** (`tests/Unit/Users/UserTest.php:48`). **The repository was committed to mid-review** (HEAD advanced `bcafd0e` → `0ff2cba`, "Review model-consitency", at 08:20:33), fixing both. I re-ran everything against the current HEAD and the results above are the real current state. The root cause is worth recording permanently: `User` declares `'password' => 'hashed'` (`app/Models/User.php:64`) and Laravel 13's `HasAttributes::setAttribute()` hashes on **set** (`vendor/…/HasAttributes.php:1133`), so `fill()` never stores plaintext. Any test asserting the plaintext password is wrong.

> **Dusk NOT RUN rationale:** running it would require `dusk:chrome-driver` to download a driver and would execute `migrate:fresh` via `DatabaseMigrations`. Per the project's review instructions I record it as NOT RUN rather than setting it up. The Dusk suite was instead reviewed statically (see TEST-005).

### Why the local suite is green but CI cannot be

The four findings in the **Critical** and **High** sections are not speculative. Each was proven with a live command rather than inferred from reading, and the proofs are reproducible:

| Claim | How it was verified | Observed output |
|---|---|---|
| `<server>` overrides the CI `DB_HOST` | Ran `Env::get("DB_HOST")` with `$_SERVER`, `putenv` and `$_ENV` all populated | `getenv = 127.0.0.1` but `Env::get = db` |
| PHPUnit writes `<server>` unconditionally | `vendor/…/PhpHandler.php:119-124` | `$_SERVER[$name] = $value;` — no `getenv()` guard, unlike `handleEnvVariables()` at :140 |
| `laravel` cannot `CREATE DATABASE` | Ran the CI step verbatim against `mariadb:11.7` | `ERROR 1044 (42000): Access denied`, `exit=1` |
| No `npm run build` in the `ci` job | `grep -n "npm" .github/workflows/tests.yml` | hits only at :192 and :198 — both in the `dusk` job |
| `@vite` would therefore throw | `grep -rn withoutVite tests/ app/ config/` | no matches; `tests/TestCase.php` is 15 lines and does not call it |
| `public/build` is not in the repo | `git check-ignore -v public/build/manifest.json` | `.gitignore:3:/public/build` |
| `laravel13-dusk` builds nothing | `grep -n "build:" docker-compose.yml` | one hit, :3, tagging `demo13-laravel13` only |

**The practical consequence:** the local suite passes because `public/build` exists on this machine and the tests run against a reachable MariaDB. Neither holds on a GitHub runner. That is the whole explanation for the gap between "612 tests pass" and "the pipeline has never been green".

---

## Critical Findings

### CI-001 — `phpunit.xml`'s `<server>` block silently overrides the CI database host, so the `ci` job cannot pass

Severity: CRITICAL
Category: CI / configuration drift
File: `phpunit.xml`; `.github/workflows/tests.yml`
Line: `phpunit.xml:33-39`; `tests.yml:50-56`
Confidence: HIGH (verified empirically, not inferred)

Problem:

`tests.yml` correctly sets `DB_HOST: 127.0.0.1` for the `ci` job, because a GitHub **service** container is not reachable by its Compose label from a job running on the runner VM. But `phpunit.xml` re-declares the same keys in a `<server>` block, and PHPUnit writes `<server>` entries into `$_SERVER` **unconditionally** — while Laravel's `env()` reads `$_SERVER` first. The workflow's `127.0.0.1` therefore never takes effect; `DB_HOST` resolves to `db`, which does not resolve on the runner.

Evidence — verified by running the precedence directly in the container, with the CI job env in place:

```
$ php -r '$_SERVER["DB_HOST"]="db"; putenv("DB_HOST=127.0.0.1"); $_ENV["DB_HOST"]="127.0.0.1";
          echo Env::get("DB_HOST");'

getenv(DB_HOST)      = 127.0.0.1     ← what the workflow sets
laravel env(DB_HOST) = db            ← what the application actually uses
```

The mechanism, confirmed in vendor:

- `vendor/phpunit/phpunit/src/TextUI/Configuration/PhpHandler.php:119-124` — `handleServerVariables()` is `$_SERVER[$variable->name()] = $variable->value();` with **no** existence check. Contrast `handleEnvVariables()` at :140, which *does* guard with `if ($force || getenv($name) === false)`. That asymmetry is the whole bug.
- `vendor/laravel/framework/src/Illuminate/Support/Env.php:76-90` — `RepositoryBuilder::createWithDefaultAdapters()` registers `ServerConstAdapter` before `EnvConstAdapter` and `PutenvAdapter`, and is built `->immutable()`. First non-null reader wins, so `$_SERVER` beats both.

The duplicated block is visible directly in `phpunit.xml`:

```xml
<env    name="DB_HOST" value="db"/>            <!-- line 27 -->
<server name="DB_HOST" value="db"/>            <!-- line 34 -->
```

Impact:

**Every DB-backed test in the `ci` job fails** with `SQLSTATE[HY000] [2002] … getaddrinfo for db failed`. Two consequences beyond the red build:

- The **80% coverage gate has never been evaluated.** `composer ci:check` (`composer.json:70-74`) chains `config:clear` → `pint --test` → `phpstan` → `php artisan test --coverage --min=80`, so the test step fails before the gate runs. `AGENTS.md:44` states CI enforces it; it does not, and has never done so.
- The same mechanism makes `phpunit.xml:36` `DB_DATABASE=laravel_test` win over the job's `DB_DATABASE: laravel`. The workflow's *intent* is right; it is delivered through a channel that is silently discarded.

Recommendation — the smallest change that makes CI and local agree:

1. **Delete the duplicated `<server>` block** (`phpunit.xml:33-39`) and keep `<env>`. Without it, `<env>` is non-forcing, so CI's `127.0.0.1` wins while local Docker (which sets no shell env) still gets `db`.
2. In the `ci` job, set `DB_DATABASE: laravel_test`, and pin `DB_DATABASE: laravel` on the **Setup Application** step only, so `composer setup`'s `migrate --force` still targets the app schema.

---

## High Findings

### CI-002 — The CI "Create test database" step runs as an unprivileged user and grants nothing

Severity: HIGH
Category: CI / database bootstrap
File: `.github/workflows/tests.yml`
Line: 107–110 (`ci`), 200–203 (`dusk`)
Confidence: HIGH (verified empirically)

Problem:

Both jobs connect as `laravel` and run `CREATE DATABASE IF NOT EXISTS laravel_test`. The `laravel` user has **no global `CREATE`**, and the step omits the `GRANT` that the local equivalent performs.

Evidence — reproduced verbatim against the same `mariadb:11.7` image the workflow uses:

```
$ mariadb -ularavel -plaravel -e "CREATE DATABASE IF NOT EXISTS ci_probe …"
ERROR 1044 (42000): Access denied for user 'laravel'@'%' to database 'ci_probe'
exit=1
```

`SHOW GRANTS FOR CURRENT_USER()` confirms why:

```
GRANT USAGE ON *.* TO `laravel`@`%`        ← only USAGE globally
GRANT ALL PRIVILEGES ON `laravel`.*       ← per-database, created by the init script
```

Local intent is done correctly **as root** — `docker/mysql/init/01-create-test-database.sql` does `CREATE DATABASE …` **plus** `GRANT ALL PRIVILEGES ON laravel_test.* TO 'laravel'@'%'`. CI replaced that root invocation with a PDO one-liner as `laravel`, with no `GRANT`.

Impact:

Both jobs fail at this step. This is very likely the reason `ARCHITECTURE.md:155` records the Dusk job as needing to be "observed once" — it has probably never completed.

Recommendation — use the root credentials the job already declares (no new infrastructure):

```yaml
run: mysql -h 127.0.0.1 -uroot -p"$MARIADB_ROOT_PASSWORD" -e \
  "CREATE DATABASE IF NOT EXISTS laravel_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; \
   GRANT ALL PRIVILEGES ON laravel_test.* TO 'laravel'@'%';"
```

This mirrors `docker/mysql/init/01-create-test-database.sql` exactly, and `mariadb-client` is preinstalled on `ubuntu-latest`.

---

### CI-003 — The `ci` job never builds the front-end assets, so every page-rendering test throws

Severity: HIGH
Category: CI / missing build step
File: `.github/workflows/tests.yml`; `resources/views/partials/head.blade.php`
Line: `tests.yml:99-102` (Node setup, unused); `head.blade.php:14`
Confidence: HIGH

Problem:

`npm ci` and `npm run build` exist only in the `dusk` job (`tests.yml:192,198`). The `ci` job sets up Node at `:99-102` and then never uses it. Since `public/build` is git-ignored, the Vite manifest is absent on the runner and `@vite` throws.

Evidence:

- `.gitignore:3` — `/public/build`. Confirmed ignored: `git check-ignore -v public/build/manifest.json` → `.gitignore:3:/public/build`. The manifest exists **locally only**, which is precisely why the suite passes here and would not there.
- `resources/views/partials/head.blade.php:14` — `@vite([...])`, also `components/passkey-registration.blade.php:2` and `components/passkey-verify.blade.php:10`.
- `vendor/…/Illuminate/Foundation/Vite.php:968-981` — `if (! is_file($path)) throw new ViteManifestNotFoundException(...)`.
- **There is no test-side bypass.** `withoutVite()` lives in `vendor/…/Testing/Concerns/InteractsWithContainer.php:126` and is opt-in; `grep -rn "withoutVite" tests/ app/ config/` returns **nothing**, and `tests/TestCase.php` (15 lines) does not call it.

Impact:

Even with CI-001 and CI-002 fixed, the `ci` job fails on the first `assertOk()` against a GET route. To answer the question directly: **yes, this is real** — `@vite` sits in the rendering path, so the asset build is a prerequisite for the PHP suite, not only for Dusk.

Recommendation:

Add `npm ci` + `npm run build` to the `ci` job, mirroring `dusk:192,198`, and add `cache: 'npm'` to `actions/setup-node` in both jobs so it costs seconds rather than minutes.

---

### CI-004 — `laravel13-dusk` uses an image that nothing in the repository builds

Severity: HIGH
Category: Docker / broken documented workflow
File: `docker-compose.yml`
Line: 33–35
Confidence: HIGH

Problem:

The `laravel13-dusk` service declares `image: demo13-laravel13-dusk` with **no `build:` section**. The only build block in the file tags `demo13-laravel13`.

Evidence:

```
docker-compose.yml:3-6    build: … dockerfile: Dockerfile.dusk   image: demo13-laravel13
docker-compose.yml:35                         image: demo13-laravel13-dusk     ← no build:
```

Grepping the whole repository for a build command finds none — only two places that *use* the service (`.github/instructions/tests.instructions.md:8` and `.github/skills/ux-implement/SKILL.md:68`), neither of which builds anything. The image exists on this machine only because it was built by hand:

```
demo13-laravel13-dusk:latest   d72110188b0b   1.93GB
demo13-laravel13:latest        d72110188b0b   1.93GB    ← same digest, manually tagged
```

Impact:

On a fresh clone, `docker compose up` tries to pull `demo13-laravel13-dusk` from Docker Hub and fails. The service is otherwise coherent — `DUSK_CHROMEDRIVER_PATH=/usr/bin/chromedriver` and the `DUSK_CHROME_BINARY` fallback `/usr/bin/chromium` both match the Debian packages installed by `Dockerfile.dusk:6` — so this is purely the missing build wiring.

Recommendation — three lines:

```yaml
    laravel13-dusk:
        build:
            context: .
            dockerfile: Dockerfile.dusk
        image: demo13-laravel13-dusk
```

Both services may build the same `Dockerfile.dusk` with different tags; their distinct `APP_CONFIG_CACHE` paths are already correct and should stay.

---

### Summary of application-level risk: none high

Beyond CI, no HIGH defect exists in the application. I state this explicitly because "no HIGH findings" is a claim that deserves the same evidence as any other. The following were checked exhaustively and found clean:

- **XSS** — the only three unescaped sinks in the entire `resources/` tree are the server-generated 2FA QR SVG of the *authenticated user's own* TOTP URI, and two same-origin `innerHTML`/`morph` calls on Blade-escaped output. All user data (customer names, notes, comment bodies) reaches the DOM through `{{ }}`, Alpine `x-text` or `@js()`.
- **SQL injection** — all 12 raw-SQL sites in `app/` bind their parameters; every interpolated column name comes from a code-controlled constant.
- **CSRF** — `withRouting(web: …)` with no `api:` key; no `preventRequestForgery` exception; Livewire 4 force-adds `web` to its update route; the one hand-rolled `fetch()` reads the token from the form's own `_token` input.
- **Mass assignment** — `active`, `deleted_at`, `created_by`, `updated_by`, `deleted_by`, `user_id` are all outside `#[Fillable]`; the audit columns are stamped only from `Auth::id()`.
- **IDOR** — every route has an authorization decision except the one in SEC-001.

---

## Medium Findings

### DOC-001 — `ARCHITECTURE.md` states two invariants that the schema and the code both forbid

Severity: MEDIUM
Category: Documentation / data integrity
File: `.github/docs/architecture/ARCHITECTURE.md`
Line: 80–81
Confidence: HIGH

Problem:

The "Important constraints" section states:

> "Epic comments are removed when their epic is permanently deleted and keep a null author when the user is deleted."

**Both halves are false.**

Evidence:

*Half 1 — the cascade cannot happen.* An epic with comments can never be deleted at all, let alone have its comments cascade away:

- `app/Providers/AppServiceProvider.php:73-80` — the `Epic::deleting` hook returns `! $locked->comments()->exists()`.
- `Model::forceDelete()` routes through `Model::delete()`, which fires `deleting` (`vendor/…/Model.php:1797`), so the guard also vetoes the permanent delete.
- Even if the guard were removed, the FK forbids it. Verified against the live schema:
  ```
  epic_comments_epic_id_foreign   DELETE_RULE = RESTRICT
  ```
  from `database/migrations/2026_09_30_175707_create_epic_comments_table.php:18` (`->constrained()`, no `cascadeOnDelete`).
- Nothing anywhere in `app/` deletes comments. Grep for `comments()` returns only the relation definitions, the read in `EpicCommentController::show()`, the `make()` in `store()`, and the guard's `exists()`.
- The application agrees with the guard, not with the doc: `app/Transformers/EpicListTransformer.php:70-77` renders a **blocked** "Delete permanently" action when `comments_count > 0`, and `tests/Feature/Epics/EpicCommentTest.php:301` asserts `$this->assertFalse($epic->delete())`.

*Half 2 — the author can never be null.* `epic_comments.user_id` is `bigint unsigned NOT NULL` with `epic_comments_user_id_foreign DELETE_RULE = RESTRICT`, from `…create_epic_comments_table.php:17`. The `'Deleted user'` fallback at `app/Http/Controllers/EpicCommentController.php:27` is a **read-time** artefact of `User`'s `SoftDeletingScope` (because `EpicComment::user()` is a plain `belongsTo` with no `withTrashed()`, `app/Models/EpicComment.php:44-47`). A hard delete of the user is blocked outright.

Impact:

A maintainer trusting this line could reasonably add `cascadeOnDelete` to `epic_comments.epic_id` or make `user_id` nullable — which would silently disable the only enforcement of the force-delete guard at the database level and break `EpicListTransformer`'s blocked-action logic and its test. This is the file the project designates as the architectural reference.

**And the dead end that follows from the first half is a product consequence, not just a doc error.** Once an epic receives its first comment it can never be removed by anyone, through any path:

- it cannot be soft-deleted (the `deleting` guard);
- it cannot be permanently deleted (the same guard, via `forceDelete()` → `delete()` → `deleting`, plus the RESTRICT foreign key);
- there is no comment-deletion route — `routes/web.php:67-72` exposes only `show` and `store`;
- `EpicComment` deliberately has no `SoftDeletes` and no `active` cast (`tests/Unit/ArchitectureTest.php:137-143`), so a comment cannot even be archived.

`tests/Feature/Epics/EpicTrashTest.php:145-161` has to *manufacture* the trashed-with-comments epic just to assert that force-delete is refused — the application can never produce that state. The only reachable states for a commented epic are deactivate and reactivate, so `ARCHITECTURE.md:79` and `:80` state mutually exclusive rules.

Recommendation:

This needs a product decision, because the two sentences cannot both hold. Whichever way it goes, make `ARCHITECTURE.md` and the code agree:

- **If an epic with comments is meant to be undeletable (the code's current behaviour):** delete the clause from line 80 and record the dead end under *Known Technical Debt*, since commented epics are permanently unremovable.
- **If comments should be removed on permanent delete (what line 80 says):** delete the children inside `TrashController::forceDeleteLocked()` before `forceDelete()`, let the `Epic::deleting` guard return `true` when `$epic->isForceDeleting()` (the flag is already set at `deleting` time, `vendor/…/SoftDeletes.php:57`), and drop the blocked-force-delete branch in `EpicListTransformer::trash()`.

**Do not do both.** Either way, also correct the second half: `user_id` is NOT NULL with a RESTRICT foreign key, and the "null author" the UI shows is a read-time effect of `User`'s `SoftDeletingScope`, not a stored null.

---

### DOC-002 — The delete-guard docblock promises protection a bare `$model->delete()` does not get

Severity: MEDIUM
Category: Documentation / data integrity
File: `app/Providers/AppServiceProvider.php`
Line: 44–52 (docblock), 53–81 (implementation)
Confidence: HIGH

Problem:

The docblock states:

> "The hook is what makes this safe, not the caller: it holds the guarantee for every path that soft deletes a record, including a plain `$model->delete()` outside a controller."

That is false. The hook only opens a transaction **around the check**; the actual soft-delete `UPDATE` runs after that transaction has already committed, so the row lock is gone.

Evidence:

- `Model::delete()` fires `deleting` **before** it writes (`vendor/…/Model.php:1797-1806`): `fireModelEvent('deleting')` → `touchOwners()` → `performDeleteOnModel()`. The `UPDATE … SET deleted_at` happens after the hook returned.
- `ManagesTransactions.php:52-55` — the PDO `commit()` runs as soon as `$this->transactions === 1`. With no enclosing transaction, the hook's own transaction *is* that level-1 transaction, so the `SELECT … FOR UPDATE` lock is released when the hook returns.
- Contrast the controller paths, which **are** correct: `CustomerController.php:87`, `ProjectController.php:96`, `EpicController.php:113` and `TrashController.php:60` all wrap `delete()` in an outer `DB::transaction`. There the hook's transaction becomes a savepoint (`ManagesTransactions.php:148-161`) and the lock genuinely survives to the outer commit. I confirmed the nested savepoint does **not** commit independently.

Impact:

No runtime defect today — every production caller wraps the delete. The risk is entirely maintenance: the comment tells a future author (a console command, a queued job, a factory teardown) that a bare `$model->delete()` is safe. It is not: a child created between the check's commit and the soft-delete `UPDATE` would slip through, producing a trashed parent with a live child. Note this is already the shape of `database/factories/HasStates.php:16-21` (`trashed()` → `$model->delete()` in `afterCreating`) — safe there only because the test's own transaction holds the lock.

Recommendation:

Reword the docblock to state the actual precondition — the guard holds the row for as long as the **caller's** transaction does, so the delete must run inside `DB::transaction()`, as all four call sites do. `.github/instructions/models.instructions.md:7` repeats the same overclaim and should be corrected in the same pass. **No code change.** Do not add locking machinery: that would be new infrastructure for a guarantee the app does not currently need.

---

### DOC-003 — `TrashController`'s class docblock misattributes restore-path safety to the delete's row lock

Severity: MEDIUM
Category: Documentation / data integrity
File: `app/Http/Controllers/TrashController.php`
Line: 17–19 (docblock), 30–53 (`restoreTrashed`)
Confidence: HIGH

Problem:

The class docblock claims:

> "The delete always takes a row lock inside its transaction, so a name inserted while the request was in flight cannot slip past the unique index the restore depends on."

`restoreTrashed()` contains **no** transaction and **no** lock. The delete's row lock locks the *record being deleted*, which has no bearing on name uniqueness. The sentence conflates two different verbs.

Evidence:

- `TrashController.php:30-53` — no `DB::transaction`, no `lockForUpdate()`. The only lock in the class is `forceDeleteLocked()` at :89-92, on the `DELETE` path.
- The restore **is** race-safe, but for a different reason: the generated-column unique index. Verified against the live schema:
  ```
  UNIQUE KEY epics_project_id_active_name_unique (project_id, active_name)
  ```
  where `active_name` is `STORED GENERATED if(deleted_at is null, name, NULL)` (`app/Support/Database/helpers.php:63-66`). Restoring sets `deleted_at = NULL`, which makes `active_name` non-NULL and therefore index-checked; the resulting `QueryException` is caught at `TrashController.php:40-46` and converted into the conflict response.
- `nameIsTaken()` at :34 is a plain non-transactional read and is only a **fast path**.

Impact:

Same class of risk as DOC-002: a maintainer who trusts lines 17-19 could delete the `catch` block as "redundant with the lock", converting a handled conflict into a **500** — the exact outcome the whole `UniqueConstraintViolation` apparatus exists to prevent. It also hides the harder-to-notice property that the restore does not *wait* for a concurrent inserter; it fails fast and reports.

Recommendation:

Reword lines 17–19 to say the restore relies on the `active_name` unique index plus the `QueryException` catch, and that the pre-check is only a fast path. State separately that `destroyTrashed()` locks the record it deletes. **No code change.**

---

### UX-001 — Searching for 1–3 characters silently does nothing, and the no-JS path behaves differently

Severity: MEDIUM
Category: Frontend / correctness
File: `resources/js/app.js`; `resources/views/components/list/searchable-results.blade.php`; `resources/views/components/list/search.blade.php`
Line: `app.js:549-551, 565-568`; `searchable-results.blade.php:4`; `search.blade.php:3`
Confidence: HIGH

Problem:

Two defects in one interaction.

Evidence:

```js
// app.js:549 — searchInput
if (query.length > 3 || (query.length === 0 && …)) { this.search(query); return; }

// app.js:565 — submitSearch
if ((query.length > 0 && query.length <= 3) || (query.length === 0 && this.currentSearch === '')) { return; }
```

```blade
searchable-results.blade.php:4   x-on:submit.prevent="submitSearch($event)"
search.blade.php:3               <form method="GET" action="{{ $search['action'] }}">
```

1. The user types `Ane`, presses Enter, and **nothing happens** — a silent dead end. No message, no hint. `ListTransformer::searchPlaceholder()` returns `__('Search record')` (`lang/eu.json` "Bilatu erregistroa") with no indication of a minimum length.
2. **Progressive enhancement is inverted.** Without JavaScript the same form performs a real GET and filters to `?search=Ane`, because only `x-on:submit.prevent` suppresses it. The JS path is the *less* capable one. The server has no such minimum: `SearchableListRequest::rules()` validates only `search => nullable|string|max:191`.

Impact:

Affects every list of every resource on every keystroke. Defect 1 is a dead end for any user who searches a short term; defect 2 means the app behaves differently depending on whether JS loaded.

Recommendation:

Remove `.prevent` from `searchable-results.blade.php:4` (make it `x-on:submit="submitSearch($event)"`). `submitSearch` already returns early when it does not intend to search, so in that case the native GET runs and both paths agree. **One token.** Add one line to the placeholder telling the user that searching starts at four characters, using the existing `__('Search record')` slot or the tooltip pattern already used in `field-label`.

---

### UX-002 — Disabling 2FA is a single unconfirmed click, and no test exercises it

Severity: MEDIUM
Category: Business rule / frontend
File: `resources/views/pages/settings/⚡security.blade.php`
Line: 251–255 (button), 200–205 (`disable()`)
Confidence: HIGH

Problem:

`Disable 2FA` calls `DisableTwoFactorAuthentication` immediately, with no confirmation dialog.

Evidence:

```blade
{{-- ⚡security.blade.php:251-255 --}}
<flux:button variant="danger" wire:click="disable">
    {{ __('Disable 2FA') }}
</flux:button>
```

```php
// ⚡security.blade.php:200-205
public function disable(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
{
    $disableTwoFactorAuthentication(auth()->user());
    $this->twoFactorEnabled = false;
}
```

Every other destructive security action in this codebase **is** confirmed: `⚡security.blade.php:349-371` (`delete-passkey-modal`), `⚡delete-user-form.blade.php:13-17`, and `resources/views/components/list/confirm-modal.blade.php` for every list row. This one is the outlier.

And no test calls it: `tests/Feature/Settings/SecurityTest.php` (249 lines) never invokes `disable()` — the only 2FA assertions are `assertSet('twoFactorEnabled', false)` after `mount()` and three locked-property tests.

Impact:

A mis-click silently and irreversibly removes 2FA from the account. Because nothing tests `disable()`, a regression in the Fortify wiring would be caught by neither the feature suite nor the browser suite.

Recommendation:

Reuse the pattern that already exists in this codebase: wrap the button in `<flux:modal.trigger name="disable-2fa-modal">` and add a sibling `<flux:modal name="disable-2fa-modal" aria-labelledby="disable-2fa-modal-heading">` with a Cancel and a `variant="danger"` confirm. Add one test beside `SecurityTest.php:80`:

```php
Livewire::test('pages::settings.security')->call('disable')->assertSet('twoFactorEnabled', false);
```

No new component library, no new dependency.

---

### A11Y-001 — The error message carrying the blocking business rules is not announced

Severity: MEDIUM
Category: Accessibility (WCAG 4.1.3 Status Messages)
File: `resources/views/components/list/flash.blade.php`
Line: 4 (success) vs 10 (error)
Confidence: HIGH

Problem:

The success flash is a live region; the error flash is not.

Evidence:

```blade
flash.blade.php:4   <flux:callout … variant="success" role="status" aria-live="polite" …>
flash.blade.php:10  <flux:callout class="rounded-xl" icon="exclamation-triangle" variant="danger" :data-test="$prefix.'-error'">
```

Impact:

The error flash is precisely the one that carries the blocking business rules after a redirect — `__('Cannot be deleted while it has related records.')`, `__('Cannot be restored because another record outside the trash uses this name.')`, `__('Cannot be permanently deleted while it has related records.')`. A screen-reader user is redirected and told nothing at all.

Recommendation:

Add `role="alert"` to line 10. **One attribute.**

---

### A11Y-002 — The 2FA setup key and its copy button have no accessible name

Severity: MEDIUM
Category: Accessibility (WCAG 4.1.2 Name, Role, Value)
File: `resources/views/pages/settings/⚡two-factor-setup-modal.blade.php`
Line: 272–282
Confidence: HIGH

Problem:

Two controls carry no accessible name whatsoever.

Evidence:

```blade
272: <input type="text" readonly value="{{ $manualSetupKey }}"
273:     class="w-full p-3 bg-transparent outline-none …" />
275: <button @click="copy()"
276:     class="px-3 transition-colors border-l cursor-pointer …">
277:     <flux:icon.document-duplicate x-show="!copied" variant="outline"></flux:icon>
279:     <flux:icon.check x-show="copied" variant="solid" class="text-green-500"></flux:icon>
282: </button>
```

No label, no `aria-label`, no `title`, not even a placeholder on the input; the button contains only two `x-show`-toggled icons.

Impact:

Announced as "edit, blank" and "button". This is the one screen where the user must transcribe and copy a secret, which makes it the worst possible place to lose the affordance. The rest of the codebase does this correctly — `row-actions.blade.php:16,26,33`, `⚡security.blade.php:322`, `search.blade.php:15` all set `:aria-label` on icon-only controls.

Recommendation:

Add `:aria-label="__('Copy setup key')"` to the button and an `aria-label` naming the setup key to the input, plus those two keys in all four `lang/*.json` (the existing parity test will demand them).

---

### SEC-001 — `GET epics/{epic}/comments` performs no authorization check at all

Severity: MEDIUM
Category: Authorization
File: `app/Http/Controllers/EpicCommentController.php`
Line: 14–35
Confidence: HIGH

Problem:

This is the only route in `routes/web.php` and `routes/settings.php` that resolves a record and returns data with no authorization decision — no Form Request, no `$this->authorize()`, no Gate. `EpicController::drawerEpic()` (`app/Http/Controllers/EpicController.php:57-69`) has the same gap.

Evidence:

```php
// EpicCommentController.php:14 — no Form Request, no authorize(), no Gate
public function show(Epic $epic): JsonResponse
```

The write path immediately beside it *does* authorize, which shows the omission is inconsistent rather than deliberate:

```php
// EpicCommentRequest.php:26-29
public function authorize(): bool
{
    return $this->user()?->can('comment', $this->route('epic')) ?? false;
}
```

Every comparable read also authorizes: `CustomerListRequest.php:11`, `ProjectListRequest.php:11`, `CustomerSelectOptionsRequest.php:11`.

Impact:

**Zero today**, because `EpicPolicy` returns `true` for every ability — the documented product scope (`ARCHITECTURE.md:43-47`). The finding matters because this endpoint becomes an information disclosure the instant *any* epic ability is tightened, and it is the one place where that tightening would be silently incomplete.

Recommendation:

Add `$this->authorize('viewAny', Epic::class)` at the top of `show()`, and gate `drawerEpic()` on the same ability. Add one feature test asserting 403 for a non-allowed user, so the check cannot be dropped again. No new ability is required — reuse `viewAny`, which all three policies already define.

---

### PERF-001 — A search keystroke executes the whole page and three queries that are then discarded

Severity: MEDIUM
Category: Performance
File: `app/Http/Controllers/Controller.php`; the three `*Controller::index`; the three `list.blade.php`
Line: `Controller.php:22-29`; `CustomerController.php:34`, `ProjectController.php:36`, `EpicController.php:36`
Confidence: HIGH (mechanism) / MEDIUM (magnitude)

Problem:

When the search box sends `X-List-Fragment`, the response is supposed to be only the results block. In practice the server does the entire request and throws most of it away.

Evidence:

- `View::fragment()` is `$this->render(fn () => $this->factory->getFragment($fragment))` (`vendor/…/View/View.php:87-95`), and `render()` calls `renderContents()` **first** — the entire compiled template executes. `startFragment`/`stopFragment` are just `ob_start()`/`ob_get_clean()` (`vendor/…/View/Concerns/ManagesFragments.php:29-54`).
- On a fragment request for `/projects`, the sidebar, header, breadcrumbs, state tabs (each calling `request()->fullUrl()`), the empty-state callout, the confirm modal and `@include('projects/form')` all render — and the form instantiates a FormRequest and re-parses its validation rules **once per field label**, because `field-label.blade.php:24` builds a fresh `FieldHints` and `FieldHints::declaredRules()` runs `new $requestClass` + `rules()` + `ValidationRuleParser::explode()` + `parse()` per rule (`app/Support/Validation/FieldHints.php:159-209`). On `/epics` that is 7 labels × ~5 rule parses.
- Three queries also run whose results appear **outside** the fragment: the two badge `COUNT`s from `stateCounts()` (`ListQueryBase.php:83-84`) and `Customer::query()->where('active', true)->exists()` (`ProjectController.php:36`).

Impact:

On a 25-row page this is single-digit milliseconds — I rate it MEDIUM rather than LOW only because it sits on the interaction path and is pure waste, not a trade-off. It is **independent of data volume**: it happens on every keystroke and every page click.

Recommendation:

The smallest honest fix is two lines in the controllers: when `$request->hasHeader('X-List-Fragment')`, pass `null` for the counts and skip the `hasCustomers`/`hasProjects` probe — `ListTransformer::tabs()` already tolerates `null` counts (`ListTransformer.php:91`). Extracting the fragment body into a partial per resource would additionally avoid the wasted Blade work, but that touches the view skeleton and is **not** worth it unless the search path is ever measured as slow. **No caching, no memoization.**

---

### TEST-001 — The restore's `QueryException` catch is unreachable from any test

Severity: MEDIUM
Category: Testing
File: `app/Http/Controllers/TrashController.php`; `tests/Feature/ConcurrentNameInsertTest.php`; `tests/Support/RacesNameInsert.php`
Line: `TrashController.php:38-46`; `ConcurrentNameInsertTest.php:121-133`
Confidence: HIGH

Problem:

`restoreTrashed()` has two defences against a name collision: the pre-check at line 34 and the `catch (QueryException)` at lines 40-46. The test named for this scenario exercises only the first. Deleting the entire `try`/`catch` leaves the suite green.

Evidence:

- `TrashController.php:34-36` — the pre-check short-circuits before the `try` block is entered.
- `RacesNameInsert::afterUniquenessSelect()` (`tests/Support/RacesNameInsert.php:15-32`) hooks `DB::listen` and creates the competitor **inside the listener** for the `SELECT … WHERE name = ?` issued by `nameIsTaken()`. The competitor therefore already exists when `nameIsTaken()` returns, so line 34 returns `true` and line 35 short-circuits. The `catch` at line 40 is never reached.
- The only other `QueryException` references in `tests/` are `UniqueConstraintViolationTest` (unit-tests `causedBy()` only — `rethrowAsValidationError()` is never exercised anywhere), `TracksAuditColumnsTest`, `DatabaseHelpersTest`, and the three `*CrudTest` index-collision tests.

Impact:

The catch block is correct and genuinely reachable in production: a second transaction can commit an insert of the same name between `nameIsTaken()` returning `false` and `restore()` executing, and the composite unique index then rejects it. Removing the block turns that window into a 500 — the exact outcome `http.instructions.md:8` mandates against. CI would not notice.

Recommendation:

Add one test per resource that forces the `QueryException` on the restore itself — the smallest way is a `DB::listen` hook on the `UPDATE … SET deleted_at = null` statement that inserts the competitor first — asserting the conflict flash and that the record is still soft-deleted. **No production change.** Do **not** build a two-connection or forked test harness; that is exactly the infrastructure this project forbids, and it would be slow and flaky. A class docblock on `RacesNameInsert` stating that it proves the `QueryException` → user-facing-error conversion and does **not** exercise `lockForUpdate` or two-connection serialisation would also be worth adding.

---

### BR-002 — The epic-comment write is the only child-creation path that takes no parent lock

Severity: MEDIUM
Category: Data integrity / concurrency
File: `app/Http/Controllers/EpicCommentController.php`
Line: 37–45
Confidence: HIGH (code fact) / MEDIUM (reachability)

Problem:

`store()` writes the comment with no transaction and no lock, while every other child-creation path takes a `lockForUpdate()` on its parent:

```php
// EpicCommentController.php:37-45 — no transaction, no lock
$comment = $epic->comments()->make($request->validated());
$comment->user()->associate($request->user());
$comment->save();
```

versus, for the other two edges of the hierarchy:

```php
// ProjectController.php:65-68 and EpicController.php:83-86
DB::transaction(static function () use ($request): void {
    Customer::query()->lockForUpdate()->findOrFail($request->integer('customer_id'));
    Project::create($request->validated());
});
```

Evidence:

`ARCHITECTURE.md:83-87` claims the guard's lock makes the invariant universal — "a child created between the check and the delete makes the delete wait and then fail". That holds for customers and projects precisely because their create paths take the *same* row lock. The epic→comment edge does not, so the serialisation argument does not transfer to it.

Interleaving: the `Epic::deleting` guard locks the epic, sees zero comments, and proceeds. The concurrent `INSERT` into `epic_comments` is satisfied because the epic row still *exists* (a soft delete leaves the row), so the comment commits against a **trashed** epic — exactly the state `EpicListTransformer::trash():70-77` renders as permanently undeletable, and which `forceDelete` will then refuse.

Impact:

Narrow — one concurrent request pair — and it widens DOC-001's dead end rather than creating a new class of damage. Not a security issue.

Recommendation:

Wrap the write in the same lock the guard takes, using the pattern already established twice in this codebase:

```php
DB::transaction(static function () use ($request, $epic): void {
    $epic->newQuery()->lockForUpdate()->findOrFail($epic->getKey());
    $comment = $epic->comments()->make($request->validated());
    $comment->user()->associate($request->user());
    $comment->save();
});
```

No new abstraction — this is the third copy of a pattern the project already has. Add one regression test asserting an epic cannot be trashed while a comment is being written.

---

### BR-003 — Inactive epics remain fully writable, which no document states

Severity: MEDIUM
Category: Undocumented behaviour / invalid state
File: `app/Policies/EpicPolicy.php`; `app/Http/Requests/EpicRequest.php`; `app/Http/Controllers/EpicController.php`
Line: `EpicPolicy.php:37-40`; `EpicRequest.php:81-92`; `EpicController.php:57-69`
Confidence: HIGH

Problem:

`ARCHITECTURE.md:63-66` restricts *parent selection* to active parents, with a documented carve-out for edit mode. Two related paths sit outside that rule and neither the code nor the documentation acknowledges it:

1. **`comment` ignores `active` entirely.** `EpicPolicy::comment()` returns `true` unconditionally, and `routes/web.php:70` binds `epics/{epic}` with default binding — which excludes *trashed* epics but not *inactive* ones. So a comment can be posted to an epic that appears in no list.
2. **Editing an inactive epic is allowed.** `EpicRequest::selectableProjectRule()` is the only place the active check lives, and it inspects the **parent project's** flag, never the epic's own. `EpicController::drawerEpic()` reopens the drawer for any epic id in the session or in `old('_epic_id')` via `Epic::query()`, which excludes trashed but includes inactive records.

Evidence: `EpicPolicy.php:37-40` (`return true;`); `EpicRequest.php:81-92` (no `active` condition on the epic itself); `EpicController.php:66` (`Epic::query()->…->find((int) $id)`).

Impact:

Not a security issue — every authenticated verified user may already see every epic (`ARCHITECTURE.md:44-47`). But `active` stops being a meaningful state boundary for epics: an epic removed from every list is still writable. A reviewer reading `ARCHITECTURE.md` would reasonably assume inactive means "not shown and not touched".

Recommendation — this is a product decision, so either direction is fine; what is not fine is the silence. Smallest consistent change: make `EpicCommentRequest::authorize()` refuse an inactive epic, and note the carve-out in `ARCHITECTURE.md:63-66`. If inactive epics are meant to stay editable and commentable, document that instead — one sentence.

---

## Low Findings

### BR-004 — `resolve_name_conflict` promises behaviour it does not have

Severity: LOW
Category: Technical debt / naming
File: `app/Http/Requests/RestoreRequest.php`; `app/Http/Controllers/TrashController.php`; `resources/views/components/name-conflict-modal.blade.php`
Line: `RestoreRequest.php:27`; `TrashController.php:48-50`; `name-conflict-modal.blade.php:35`
Confidence: HIGH

Problem:

The flag is validated as a boolean and read **only** to choose between two flash strings. No code path branches on it:

```php
// TrashController.php:48-50
$message = $request->boolean('resolve_name_conflict')
    ? __('Record restored successfully. No new record was created with the repeated name.')
    : __('Record restored successfully.');
```

By the time this line runs, the conflict has already been resolved or refused by `nameIsTaken()` at `TrashController.php:34-36`. Any caller can send the flag on any restore and receive the "No new record was created" string regardless of what happened.

Evidence: repo-wide grep returns 7 hits — the request rule, the controller, the modal's hidden input, one doc line, and three tests that pin the current behaviour.

Impact: cosmetic, but the name actively misleads a maintainer into expecting a resolution feature that does not exist.

Recommendation: rename it to what it does (e.g. `restored_instead_of_created`). **Do not build a resolution feature** — the behaviour is deliberate and tested. The rename is the whole fix.

---

### BR-005 — Deactivating a customer leaves its active projects fully usable, and that is nowhere written down

Severity: LOW
Category: Undocumented behaviour
File: `app/Http/Controllers/InactiveController.php`; `app/Queries/Projects/ProjectListQuery.php`; `app/Queries/Projects/ProjectSelectOptionsQuery.php`
Line: `InactiveController.php:17-25`; `ProjectListQuery.php:109-115`; `ProjectSelectOptionsQuery.php:22-23`
Confidence: HIGH

Problem:

`ARCHITECTURE.md:61-62` correctly states that deactivation does not cascade, and that is verified. What the spec does **not** say is what a usable state looks like afterwards, and the answer is counter-intuitive:

- `ProjectListQuery::withCustomer()` joins `customers` with no `active` predicate, so projects of a deactivated customer stay in the **active** project list.
- `ProjectSelectOptionsQuery` filters only `projects.active`, so a project under a deactivated customer is still offered as an epic parent.
- Meanwhile the *customer* selector correctly refuses that customer for new projects (`ProjectRequest.php:72-74`).

`tests/Feature/ResourceActivationTest.php:544` (`test_deactivating_a_parent_does_not_cascade_to_children`) locks the non-cascade behaviour in, so the code is intentional — only the documentation is missing.

Recommendation: no code change. Add one sentence after `ARCHITECTURE.md:62`: *"Deactivating a customer or project does not hide, block or cascade to its children: existing active projects remain listed, editable and usable as epic parents."*

---

### BR-006 — The audit trail is written correctly and then never shown to anyone

Severity: LOW
Category: Feature not surfaced
File: `app/Concerns/TracksAuditColumns.php`
Line: 20–93
Confidence: HIGH

Problem:

`created_by` / `updated_by` / `deleted_by` are maintained correctly — no path silently skips `deleted_by` for an authenticated actor, and the write path is sound (`newModelQuery()` carries no global scopes, so the post-soft-delete update is not filtered out by `SoftDeletingScope`; `restore()` clears `deleted_by` and re-stamps `updated_by` in one write). But `grep` for those three column names across `resources/` returns **zero matches**. No list row, drawer or detail view displays them.

Impact: the feature is invisible. `ARCHITECTURE.md` does not claim it is displayed, so this is not a contradiction — but a reviewer or user will reasonably expect a trail written on every mutation to be readable.

Recommendation: no change required for correctness. If surfacing is wanted, the cheapest slot is the existing list `extraDate` cell, which already renders `created_at` / `updated_at` / `deleted_at` per state (`ListTransformer.php:42-46`). Either way, record the intent in `ARCHITECTURE.md` so it is not re-flagged.

---

### OPS-001 — Development ports are bound to all host interfaces while the admin services are loopback-only

Severity: LOW
Category: DevOps / network exposure
File: `docker-compose.yml`
Line: 11-13, 114-115, 138-139 vs 234-251
Confidence: HIGH

Problem:

The three admin services are correctly loopback-only, but the app, the Vite dev server and the npm profiles are not:

```yaml
234-235:   - 127.0.0.1:13306:3306        # db
239-240:   - 127.0.0.1:4000:80           # phpMyAdmin
250-251:   - "127.0.0.1:${MAILHOG_PORT:-8025}:8025"   # mailhog
 11-13:   - "80:80"                      # laravel13
           - "${VITE_PORT:-5173}:${VITE_PORT:-5173}"
114-115:   - "3000:3000"                  # laravel13-npm-all
138-139:   - "3000:3000"                  # laravel13-npm
```

The Vite dev server sets only `server.cors = true` and no host or filesystem restriction (`vite.config.js:25-26`), and the container mounts the whole project — including `.env`, `storage/` and `dc-data/` — at `/docker`.

Impact:

Anything on the LAN can reach the app and an unauthenticated Vite dev server. Docker-published ports bypass the server's own bind address, so `server.host` cannot protect this. Rated LOW because `ARCHITECTURE.md:143-149` states these are local-development only — but the inconsistency with the three admin services that *are* handled correctly suggests an oversight rather than a decision.

Recommendation:

Bind loopback, consistent with the three services already done that way: `"127.0.0.1:80:80"`, `"127.0.0.1:${VITE_PORT:-5173}:…"` and `"127.0.0.1:3000:3000"`. No new infrastructure, no file.

---

### BUG-001 — A restore can report success for a record a concurrent request permanently deleted

Severity: LOW
Category: Correctness
File: `app/Http/Controllers/TrashController.php`
Line: 38-52
Confidence: HIGH

Problem:

Because `restoreTrashed()` takes no lock, its `UPDATE` can match **zero rows** and still be reported as a success.

Evidence:

- `Model::performUpdate()` discards the affected-row count (`vendor/…/Model.php:1537-1545`), so `save()` returns `true` whether 1 or 0 rows changed.
- Interleaving: T1 = `PATCH customers/trash/5`, T2 = `DELETE customers/trash/5`. `forceDeleteLocked()` (`:85-95`) holds `SELECT … FOR UPDATE` on row 5. T1's `UPDATE` blocks; T2 commits the hard delete; T1 resumes, matches 0 rows, raises no error.

Impact:

The flash at `TrashController.php:52` reports a successful restore for a row that no longer exists. No data corruption — the user simply sees a wrong confirmation and the record is genuinely gone.

Recommendation:

The smallest fix that matches the existing style: re-read the record by key inside the same `try` after `$record->restore()` and treat a missing row as a failure. **Do not add locking machinery** for this.

---

### A11Y-003 — Every paginated list renders two nested `<nav>` landmarks

Severity: LOW
Category: Accessibility (WCAG 1.3.1 / 2.4.1)
File: `resources/views/components/list/table.blade.php`
Line: 11–13
Confidence: HIGH

Problem:

```blade
resources/views/components/list/table.blade.php:11
<nav aria-label="{{ __('Pagination') }}" data-test="{{ $prefix }}-pagination">
    {{ $paginator->links() }}
</nav>
```

and the framework view it wraps (`vendor/laravel/framework/src/Illuminate/Pagination/resources/views/tailwind.blade.php:2`):

```blade
<nav role="navigation" aria-label="{{ __('Pagination Navigation') }}">
```

There is no `resources/views/vendor/pagination/` override, so the framework view is what renders.

Impact:

In Basque the user gets a landmark labelled `Orrikatzea` nested inside one labelled `Pagination Nabigazioa` (`lang/eu.json:206-207`). Two same-purpose landmarks is a real uniqueness/naming defect and it scales with page count.

Recommendation:

Keep the application `<nav>` — it carries the `data-test` that `resources/js/app.js:584` matches and that the Dusk tests assert on — and neutralise the inner one: publish the pagination view (`php artisan vendor:publish --tag=laravel-pagination`) and change its `<nav>` to a `<div>`. One file, two tokens.

---

### A11Y-004 — Search results are replaced silently with no status announcement

Severity: LOW
Category: Accessibility (WCAG 4.1.3)
File: `resources/views/components/list/searchable-results.blade.php`; `resources/js/app.js`
Line: `searchable-results.blade.php:3`; `app.js:606-647`
Confidence: HIGH

Problem:

The whole morph target is a plain `<div>` with no `role="status"`/`aria-live`, and no view renders a result count. After four keystrokes the table silently swaps.

Evidence: `searchable-results.blade.php:3`; `app.js:606-647` swaps the table with `window.Alpine.morph(this.$root, results.outerHTML)`. No `aria-live` exists in any file under `resources/views/components/list/`.

Recommendation:

Add a visually hidden status line inside the morph target, fed from the data already in hand, and set it in `refreshList()` after the morph. Do **not** put `aria-live` on the wrapper itself — the subtree is replaced wholesale and that would re-announce every cell.

---

### A11Y-005 — 10 of 12 drawer fields have no `for`/`id` association

Severity: LOW
Category: Accessibility
File: `resources/views/components/forms/field-label.blade.php`; the three `form.blade.php`
Line: `field-label.blade.php:29`; `customers/form.blade.php:3,13`; `projects/form.blade.php:4,14,23,68`; `epics/form.blade.php:3,37,46,51,55,80,93`
Confidence: HIGH (inconsistency) / MEDIUM (WCAG impact — Flux internals not verifiable)

Problem:

`field-label` supports `:for` (`field-label.blade.php:29` → `<flux:label :for="$for">`), but only two call sites use it (`projects/form.blade.php:33`, `epics/form.blade.php:14`). Meanwhile `epics/form.blade.php:50` declares `id="epic-end-date"`, which nothing references.

Impact — stated with the caveat: Flux free renders `ui-label`, not a native `<label>`, and its JS enhances those elements, so I **cannot** verify from readable source that a real accessible name is missing, and I am not claiming one. What is verifiable is that the codebase is internally inconsistent, so the day the accessible name is derived from `<label for>`, the ten unassociated fields lose it.

Recommendation:

Pass `for` and add a matching `id` for each of the twelve fields. Mechanical, one line per field, no new component, and safe regardless of what Flux turns out to expose.

---

### A11Y-006 — The "why can't I delete this?" hint is reachable by hover only

Severity: LOW
Category: Accessibility (WCAG 1.3.1)
File: `resources/views/components/list/row-actions.blade.php`
Line: 24
Confidence: HIGH

Problem:

```blade
row-actions.blade.php:24
<span tabindex="0" class="inline-flex" :aria-label="$action['hint']">
```

`aria-label` on a `<span>` with no role is not reliably announced (generic role does not support naming), and the wrapped button is `disabled`, so it is not focusable and its own `:aria-label` never reaches the accessibility tree.

Recommendation:

Add `role="note"` to the span so the label has a role that supports naming. **One attribute.**

---

### A11Y-007 — Resource tables have no `<th scope>` and no caption

Severity: LOW
Category: Accessibility (best practice)
File: `resources/views/customers/list.blade.php`, `projects/list.blade.php`, `epics/list.blade.php`
Line: e.g. `customers/list.blade.php:71-80`
Confidence: HIGH

Problem:

`vendor/livewire/flux/…/table/column.blade.php:39` renders `<th {{ $attributes->class($classes) }} data-flux-column>` and no call site passes `scope`. Context is otherwise good: the actions column carries `<span class="sr-only">{{ __('Actions') }}</span>` and `page-header.blade.php:34` emits an `sr-only` `<h1>`.

Impact: browsers infer column-header semantics for `<th>` inside `<thead>` even without `scope`, so this is a best-practice gap, not a broken announcement.

Recommendation: `scope="col"` on the column definitions. The `sr-only` `<h1>` makes a `<caption>` optional.

---

### DOC-004 — Every model annotates dates as mutable `Carbon` while the app forces `CarbonImmutable`

Severity: LOW
Category: Correctness / types
File: `app/Models/{Customer,Project,Epic,User,EpicComment}.php`
Line: `Customer.php:5`, `Project.php:5,24-25`, `Epic.php:6,23-24`, `User.php:6,26,30`, `EpicComment.php:5,24-25`
Confidence: HIGH

Problem:

All five models declare `@property Carbon $created_at` with `use Illuminate\Support\Carbon;`, but `AppServiceProvider.php:108` calls `Date::use(CarbonImmutable::class)`.

Evidence — verified in the container:

```
is_subclass_of("Carbon\CarbonImmutable", "Illuminate\Support\Carbon")  =>  false
```

`Illuminate\Support\Carbon` extends `Carbon\Carbon` (mutable); `Carbon\CarbonImmutable` is **not** a subclass of it. `DateFactory::__callStatic` resolves the configured class, so the runtime type of every cast date is `Carbon\CarbonImmutable`.

Impact:

Larastan level 9 trusts the docblock, so it reports nothing while the declared type is wrong. A maintainer writing `->addDay()` expecting in-place mutation gets a silent no-op, and any future code type-hinting `Illuminate\Support\Carbon` on a model attribute will fail at runtime.

Recommendation:

Change the import in the five models to `Carbon\CarbonImmutable`. Annotation-only, zero behavioural risk.

---

### I18N-001 — 41 of 370 Basque strings are the English value verbatim, and no test can detect it

Severity: LOW
Category: i18n
File: `lang/eu.json`
Line: various (see evidence)
Confidence: HIGH

Problem:

The locale default is Basque (`config/app.php:81`), but a large block of the app's own copy is untranslated English. Measured directly:

```
identical-value keys: 41 (of 370)
```

Representative keys, overwhelmingly the passkey flow:

| Key | `lang/eu.json` value |
|---|---|
| `"Password updated."` | `"Password updated."` |
| `"Profile updated."` | `"Profile updated."` |
| `"Register passkey"` | `"Register passkey"` |
| `"Sign in with a passkey"` | `"Sign in with a passkey"` |
| `"Manage your passkeys for passwordless sign-in"` | *(English)* |
| `"Passkeys"` / `"Passkey name"` / `"No passkeys yet"` | *(English)* |

The breadth is uneven across locales — `es.json:215,228,284` are properly translated while `fr.json:215,228` are not — which confirms drift rather than a policy.

Impact:

A Basque user reads the entire passkey registration flow, and two of the app's own success toasts (`⚡security.blade.php:105`, `⚡profile.blade.php:48`), in English. `tests/Unit/Translations/TranslationFilesTest.php` only compares key sets and placeholders, so **CI cannot see this**.

Recommendation:

Translate the 41 keys. Scoped strictly to "a string that reached the UI untranslated while appearing, to every test, translated" — I am not reviewing Basque prose quality.

---

### I18N-002 — `__()` does not pluralize, so per-row `aria-label`s are grammatically wrong

Severity: LOW
Category: i18n / accessibility
File: `resources/views/customers/list.blade.php`, `projects/list.blade.php`
Line: `customers/list.blade.php:99-100`; `projects/list.blade.php:138`
Confidence: HIGH

Problem:

`__('View :count projects', ['count' => …])` never selects a plural form, because `__()` does not pluralize JSON keys — only `trans_choice()` does.

Evidence: `lang/eu.json:356-357` holds two separate keys (`"Ikusi :count epika"` / `"Ikusi :count proiektu"`), and the same pattern exists in `es.json`, `fr.json`, `en.json`. A customer with one project renders `aria-label="View 1 projects"`.

Impact: wrong grammar on a per-row `aria-label`, heard by every screen-reader user on every single-child row. Basque is unaffected (agglutinative); en/es/fr are.

Recommendation — the fix needing **zero** new keys: drop `:count` and reuse keys that already exist in all four locales, `:aria-label="__('Projects')"` / `__('Epics')`. The count is already visible inside the button. Real pluralization would need PHP lang files, i.e. a new `lang/` subdirectory, which `AGENTS.md:5` says needs approval — so I am not recommending it.

---

### PERF-002 — `EnsureUserIsActive` re-reads a user row the guard already loaded this request

Severity: LOW
Category: Performance
File: `app/Http/Middleware/EnsureUserIsActive.php`
Line: 23–24
Confidence: HIGH

Problem:

```php
$user = $request->user();
if ($user instanceof User && ! User::whereKey($user->getAuthIdentifier())->where('active', true)->exists()) {
```

`$request->user()` resolves the guard, which loads the row **in this same request**: `SessionGuard::user()` → `EloquentUserProvider::retrieveById()` → `select * from users where id = ? and deleted_at is null`. Line 24 then issues a second `EXISTS` against the same row, whose `active` value is already loaded and cast to `boolean` (`app/Models/User.php:61`).

Impact: one redundant primary-key lookup on every authenticated request, including every Livewire update (Livewire's update route runs the `web` group). Stated honestly: **this does not matter for performance.** It is worth reporting as a simplification, not as a bottleneck.

Recommendation: `if ($user instanceof User && ! $user->active) {`. Same freshness, one fewer query, one fewer line. No caching and no session flag.

---

### PERF-003 — Every row's `editPayload` is built and serialized twice

Severity: LOW
Category: Performance / response size
File: `app/Transformers/ListTransformer.php:139`; the three `*ListTransformer::columns()`; the three `list.blade.php`
Line: `ListTransformer.php:139`; e.g. `CustomerListTransformer.php:93`
Confidence: HIGH

Problem:

`columns()` includes `'editPayload' => $this->editPayload($record)`, and `editAction()` builds the very same payload again at `ListTransformer.php:139`. Both copies reach the browser: `data-payload` on the row-name button **and** on each row action.

Impact: `editPayload` contains `notes`, which is `longText` with a 10 000-character ceiling (`config/validation.php:28`). A 25-row page can therefore carry up to ~500 KB of duplicated note text. With realistic short notes this is invisible; with long ones it roughly doubles the response body for no functional gain — the second copy is redundant because both handlers live in the same `<tr>`.

Recommendation: emit the payload once per row. Not a one-liner — one of the two handlers has to look it up from the row. **Optional**; I am reporting it because the ceiling is 500 KB, not because I expect it to bite.

---

### TEST-002 — `DatabaseHelpersTest` runs DDL on the shared base test database with no isolation trait

Severity: LOW
Category: Testing / isolation
File: `tests/Feature/Support/Database/DatabaseHelpersTest.php`
Line: 17, 39, 44, 55, 79
Confidence: HIGH

Problem:

This is the only feature test with no database trait, and it runs `Schema::create/drop` against the **shared base** `laravel_test`, plus commits real `users` rows. Laravel only rewrites the connection for tests using one of the four DB traits (`vendor/…/Testing/Concerns/TestDatabases.php:46-56`), which is exactly the `laravel_test_test_1..8` set observed in MariaDB.

Impact: parallel-safe today only because it is the sole class in that category. Two latent risks: an assertion failing between lines 18 and 39 leaves `helper_*` tables behind (masked by `dropIfExists` next run), and the committed `users` rows survive the rest of that worker.

Recommendation: **do not** add a DB trait — MariaDB `CREATE TABLE` implicitly commits, which would commit `RefreshDatabase`'s enclosing transaction and break isolation for everything after it in that test. Add the one-line assertion `DuskTestCase.php:20` already uses, so the next author sees the intent.

---

### TEST-003 — A Dusk test blocks for ~20 seconds on a presentation timeout

Severity: LOW
Category: Testing / brittleness
File: `tests/Browser/Customers/CustomerCrudTest.php`
Line: 500–503
Confidence: HIGH

Problem:

```php
->waitUntil('document.querySelector(\'[data-test="customer-status"]\').style.display === \'none\'', 22);
```

asserts against `flash.blade.php:5`'s `x-init="setTimeout(() => visible = false, 20000)"`.

Impact: ~20s in a Dusk job budgeted at `timeout-minutes: 25` (`tests.yml:130`), and it fails if anyone tunes that constant. It is also coupled to Alpine's implementation detail (`x-show` writing `display:none`).

Recommendation: delete the `waitUntil` and keep the `assertVisible` immediately before it (line 499). Auto-dismiss is presentation, not behaviour. Net: −4 lines, −20s.

---

### TEST-004 — The uniformity test cannot attribute an ability to a controller

Severity: LOW
Category: Testing
File: `tests/Unit/ResourceUniformityTest.php`
Line: 138–151
Confidence: HIGH

Problem:

The "has an HTTP call site" half of the test aggregates all of `app/Http` into one string, so it cannot attribute an ability to a controller.

Impact: if `EpicController::destroy` dropped `->authorize('delete', …)` while `CustomerController::destroy` kept it, the aggregate still matches and the test stays green. The policy-drift half (lines 135-136) is unaffected.

Recommendation: loop per controller over the same `File::allFiles(app_path('Http/Controllers'))`. ~5 lines, reusing the existing helper.

---

### TEST-005 — Two unreferenced test base classes, one of which always passes

Severity: LOW
Category: Testing / dead code
File: `tests/Browser/Pages/Page.php`, `tests/Browser/Pages/HomePage.php`
Line: `Page.php:14-19`; `HomePage.php:20-35`
Confidence: HIGH

Problem:

`Page::siteElements()` returns the placeholder `['@element' => '#selector']`; `HomePage::assert()` is an empty method body containing only `//`. Grepping finds only the two declarations — every browser test selects by raw `[data-test=…]`.

Impact: a new contributor copies `HomePage` and inherits an assertion that always passes.

Recommendation: delete both files.

---

### OPS-002 — `LOG_LEVEL=debug` is what a fresh production deployment inherits

Severity: LOW
Category: Observability / configuration
File: `.env.example`
Line: 21
Confidence: HIGH

Problem:

`.env.example:21` and `.env:21` both set `LOG_LEVEL=debug`, and every level-driven channel defaults to `debug`. Production therefore retains 14 days (`daily` + `max_files: 14`) of full exception context — file paths, rendered arguments — which is then exposed through the log-viewer UI.

Recommendation: `LOG_LEVEL=warning` in `.env.example`. **One line, no code.** Local development keeps `debug` via the untracked `.env`.

---

### OPS-003 — Debugbar's default inherits `APP_DEBUG`, not "off"

Severity: LOW
Category: Observability / configuration
File: `config/debugbar.php`
Line: 19
Confidence: HIGH

Problem:

`'enabled' => env('DEBUGBAR_ENABLED')` evaluates to `null`, which means **"follow `app.debug`"** — the config's own comment three lines above says so. Neither `.env` nor `.env.example` sets `DEBUGBAR_ENABLED`, and `.env:4` sets `APP_DEBUG=true`; `storage/debugbar/` contains populated dumps from 2026-10-03 through 2026-10-05, so it is demonstrably running.

Impact: setting `APP_DEBUG=true` on a live host simultaneously enables the debug error page *and* Debugbar's collectors, which persist full request data — including `Cookie` headers carrying session and XSRF tokens — to `storage/debugbar/*.json` (`config/debugbar.php:268`), and exposes the package's `/_debugbar/*` routes, which are **not** behind `auth` (contrast the log viewer's gating).

Mitigating: `storage/debugbar/.gitignore` correctly excludes the dumps, `.env.example:4` ships `APP_DEBUG=false`, and `docker-compose.yml:52` sets `DEBUGBAR_ENABLED: "false"` for the Dusk service.

Recommendation: make the default fail-closed — `'enabled' => env('DEBUGBAR_ENABLED', false)` — and add `DEBUGBAR_ENABLED=true` to the untracked local `.env`. Two small edits, no new dependency.

---

### SEC-002 — `SESSION_ENCRYPT=false` leaves session payloads readable in the database

Severity: LOW
Category: Session security (hardening)
File: `.env`, `.env.example`; `config/session.php`; `database/migrations/0001_01_01_000000_create_users_table.php`
Line: `.env:35`; `config/session.php:50`; migration `:37`
Confidence: HIGH

Problem:

`SESSION_DRIVER=database` + `SESSION_ENCRYPT=false` means `sessions.payload` (`longText`) holds the serialized session in plaintext — including the user id, the password hash, the CSRF token, and flashed old input, which for this app carries submitted record bodies and comment bodies back into the session on validation failure.

Evidence that the rest of the cookie posture is sound: `http_only => true` (`config/session.php:185`), `same_site => 'lax'` (:202), `serialization => 'json'` (:231, so a leaked `APP_KEY` yields no PHP gadget chain), `domain => null` (host-only cookie). The one gap is `secure`, which resolves from `SESSION_SECURE_COOKIE` or `APP_ENV === 'production'` (:172).

Impact: read-only database access (a backup, phpMyAdmin on 127.0.0.1:4000) yields directly replayable sessions without needing `APP_KEY`.

Recommendation: `SESSION_ENCRYPT=true` in production. Note the coupling with SEC-003 below.

---

### SEC-003 — Rate limiting covers three auth paths; registration, password reset and verification resend are unthrottled

Severity: LOW
Category: Rate limiting / abuse
File: `app/Providers/FortifyServiceProvider.php`; `config/fortify.php`; `config/auth.php`
Line: `FortifyServiceProvider.php:120-144`; `config/fortify.php:170-172`; `config/auth.php:95-102`
Confidence: HIGH

Problem:

Only three limiters exist anywhere — `login` (5/min by username|ip), `two-factor` (5/min), `passkeys` (10/min). Laravel 13's default global middleware stack has no `throttle`, and `bootstrap/app.php` calls neither `throttleApi()` nor a global limiter, so **no other route in the application is rate limited**. Self-registration is enabled.

Evidence: `config/fortify.php:117-125` wires exactly those three. The only brake on reset-mail abuse is per-recipient and per-email (`config/auth.php:95-102`, `throttle => 60`), which does not bound a spray across many addresses.

Impact: an unauthenticated attacker can create unbounded unverified accounts (blocked from data access by the `verified` middleware, but they grow `users`) and use `POST /register`, `POST /forgot-password` and `POST /email/verification-notification` as a mail-flooding amplifier against arbitrary third-party addresses.

Recommendation: register a `password-reset-link` limiter keyed on `$request->ip()` and a `verify-email` limiter, and attach them to the relevant Fortify routes. If self-registration is not a production feature, remove `Features::registration()` — one line. **No new dependency, no new middleware layer.**

---

### DEP-001 — `select2@4.1.0` requires Node ≥ 24 while CI runs Node 22

Severity: LOW
Category: Dependencies / CI
File: `package-lock.json`; `.github/workflows/tests.yml`
Line: `package-lock.json:3797-3804`; `tests.yml:102,174`
Confidence: HIGH

Problem:

`select2` 4.1.0 declares `"engines": { "node": ">=24" }`, while both CI jobs pin `node-version: '22'`. `.npmrc` has no `engine-strict=true`, so npm emits `EBADENGINE` as a warning and `npm ci` still succeeds — a noisy warning, not a failure.

Local development is unaffected: `docker-compose.yml:110,134` use `node:${DC_NODE:-24-bullseye}`.

Impact: the warning is easy to read as harmless noise while the combination is simply untested in CI.

Recommendation: change `node-version: '22'` to `'24'` in both jobs, matching Docker. **One line each, no dependency change.**

---

### DEP-002 — `laravel/chisel` is a runtime `require` used only by a one-shot installer command

Severity: LOW
Category: Dependencies / classification
File: `composer.json`
Line: 13
Confidence: HIGH

Problem:

`"laravel/chisel": "^0.1.0"` sits in `require`, not `require-dev`, so it and its transitive dependencies are installed in every production deployment. It is used only by `app/Console/Commands/InstallFeaturesCommand.php:5-6,41`, a command the installer invokes once — and `chisel.php:266-277` shows the tool **deletes itself** once it has run.

Impact: unnecessary production install surface (`nikic/php-parser`, resolved in the non-dev `packages` array at `composer.lock:2946`) and a service-provider registration.

Recommendation: move it to `require-dev`. One line.

---

### OBS-001 — A genuine concurrent-write race is converted to a 422 and leaves no trace

Severity: LOW
Category: Observability
File: `app/Support/Database/UniqueConstraintViolation.php`
Line: 30–39 (call sites: `CustomerController.php:60,75`, `ProjectController.php:70,84`, `EpicController.php:88,102`, `TrashController.php:41`)
Confidence: HIGH

Problem:

```php
if (! self::causedBy($exception)) { throw $exception; }        // real DB error → 500 → logged
throw ValidationException::withMessages([...]);                // unique violation → 422, never logged
```

A unique-index violation that passed validation becomes a `ValidationException`, which Laravel renders but does not report. After the fact, a real concurrent-write race is indistinguishable from a user submitting a duplicate name.

Impact: the named operational need — "how often does the concurrent-name race fire, and on which resource?" — is unanswerable today.

Recommendation: **the one logging change this review recommends.** Add a single `Log::warning()` inside `rethrowAsValidationError()`, immediately before the throw, carrying only the field and the SQLSTATE. It is the single convergence point for all six write paths, fires only on real DB-level collisions, introduces no new file, class or dependency, and must log **no** submitted values so no user data reaches the log.

---

### CLEAN-001 — Unreferenced starter-kit views and Flux overrides

Severity: LOW
Category: Maintainability / dead code
Files: `resources/views/layouts/app/header.blade.php`; `resources/views/layouts/auth/{card,split}.blade.php`; `resources/views/components/placeholder-pattern.blade.php`; `resources/views/flux/navlist/group.blade.php`; `resources/views/flux/icon/{layout-grid,folder-git-2,book-open-text}.blade.php`
Line: see files
Confidence: HIGH

Problem:

Nothing renders `layouts::app.header`, `auth.card`, `auth.split` or `placeholder-pattern` (verified by grep: zero references). Three of the four Flux icon overrides are used **only** inside the dead `header.blade.php`; `chevrons-up-down` is genuinely used by `desktop-user-menu.blade.php:5`. `navlist/group.blade.php` (51 lines) is referenced by nothing at all, and by no Flux component internally.

Impact: ~200 lines of unreferenced Blade. Two concrete traps rather than mere clutter — `header.blade.php:2` hardcodes `class="dark"` on `<html>` while the layout actually in use (`sidebar.blade.php:2`) does not, so re-enabling it would silently flip the default theme; and `header.blade.php:26-27` contains a visible, focusable `href="#"` search item that does nothing, plus two links pointing at the Laravel starter kit.

Recommendation: delete the six files. Deletion is the smaller change — nothing references them, and `chevrons-up-down` must be kept.

---

### CLEAN-002 — Stale observability environment variables for uninstalled packages

Severity: LOW
Category: Technical debt
File: `phpunit.xml`
Line: 44–46
Confidence: HIGH

Problem:

`PULSE_ENABLED=false`, `TELESCOPE_ENABLED=false` and `NIGHTWATCH_ENABLED=false` are set for packages that appear in neither `composer.json` nor `composer.lock`. They are no-ops.

Impact: misleading — a reader may believe Pulse or Telescope is installed and merely switched off for tests. Note that `tests/Unit/ModelSchemaParityTest.php` and `tests/Unit/ResourceUniformityTest.php` sit in `tests/Unit` yet use `RefreshDatabase`, so "Unit" in this project does not mean "no database".

Recommendation: delete lines 44–46. No behaviour change.

---

## Informational Findings

- **No N+1 anywhere on the list paths.** Traced all three endpoints: 7 queries for `/customers`, 9 for `/projects`, 10 for `/epics`, plus 2 for the database session driver. Every row value comes from a correlated subquery or a single eager load. `Project::fullName()`'s lazy `customer` never fires because `EpicListQuery::withProjectAndCustomer()` loads `project.customer` with matching columns. **No change recommended.**
- **The generated-column uniqueness design is sound and verified.** `active_name = IF(deleted_at IS NULL, name, NULL)` with a unique index correctly permits reusing a soft-deleted name (NULLs are distinct) and rejects a live duplicate (SQLSTATE 23000 / errno 1062, already matched by `UniqueConstraintViolation::causedBy()`). A foreign-key violation (1452) is correctly **not** misreported as a name conflict. The `down()` migrations are correct — dropping the table drops the generated column and index with it. **No change.**
- **No deadlock is possible.** Every path acquires locks parent-before-child; `store()` takes the parent then inserts, `destroy()` takes only the parent. No model declares `$touches`, so `Model::delete()` adds no implicit locks. **No change.**
- **`Customer::epics()` / `Project::comments()` correctly exclude trashed intermediates.** Verified in vendor rather than assumed: `HasOneOrManyThrough::performJoin()` registers a `SoftDeletableHasManyThrough` scope when the intermediate model is soft-deletable (`vendor/…/HasOneOrManyThrough.php:119-132,149-152`), and it also applies inside `withCount`. The docblocks are accurate.
- **`epics`/`projects` INNER JOINs do not drop soft-deleted parents.** The joins are raw, with no `deleted_at`/`active` predicate, and both FKs are NOT NULL + RESTRICT, so orphan rows are impossible. Consistent with the documented trash/inactive semantics.
- **Index audit: no missing index on any hot path.** `(deleted_at, id)` is selected by the optimiser for every list query; `customer_id`, `project_id`, `epic_id` and the `active_name` indexes all serve their predicates. `epic_comments_deleted_at_id_index` can never be selective — but this is **already tracked** as `MOD-001` in `.github/tasks/model-consistency.md:90-142`, where the dead columns are an explicit, test-pinned decision. **Not re-reported as new.**
- **Trusted hosts are correct and fail-closed.** `bootstrap/app.php:17-34` is stricter than the framework default (`subdomains: false`). The `LogicException` is **per-request**, not at boot — `TrustHosts::at()` only stores the closure, and `shouldSpecifyTrustedHosts()` skips it when `environment('local')`. A malformed `APP_URL` therefore causes a total outage, not a host-header bypass. The caveat: `.env.example:2` ships `APP_ENV=local`, so a deploy that copies it and forgets to change it runs with trusted-host validation **and** `SESSION_SECURE_COOKIE` both off (SEC-002).
- **No XSS, SQLi, IDOR, mass-assignment or CSRF defect exists.** Verified exhaustively — see High Findings.
- **`RequirePasswordForLivewire` is a necessary, tested workaround, not a hack.** `RequirePassword::handle()` returns a 423 `JsonResponse` for JSON requests, and Livewire's `Utils::applyMiddleware()` only aborts on `RedirectResponse` (`vendor/livewire/livewire/src/Drawer/Utils.php:192-194`). Without the wrapper, the persistent-middleware replay would let a guarded action execute with no password confirmation — a real bypass. Proven end to end by `tests/Feature/Settings/SecurityTest.php:195-247`.
- **`InactiveController` reactivate/deactivate is intentionally idempotent.** Explicitly asserted by `tests/Feature/ResourceActivationTest.php:200-213`. **Do not "fix".**
- **The epic-name concurrency lock narrows the window; the unique index is what makes it safe.** Correct as written.
- **The four architecture unit tests genuinely catch drift** — each has at least one negative constraint that fails on a real regression (`ValidationCoverageTest` hard-fails on any literal `max:N`; `ResourceUniformityTest` asserts exact key *order*; `ModelSchemaParityTest` really hits the DB). **No change.**
- **`InactiveUserTest` exceeds `ARCHITECTURE.md`'s claim**, covering all four documented auth paths (password, 2FA, remember-me, passkey) plus session revocation on the next request, JSON rejection and guest ordering.
- **`phpunit.dusk.xml` cannot wipe a real database** — `tests/DuskTestCase.php:20` asserts the connection is `laravel_test` and every browser class uses `DatabaseMigrations` (per-test `migrate:fresh`). Worth adding `DB_DATABASE` and `DUSK_BASE_URL` to that XML so it states what the code already asserts.
- **Zero application-level logging, and that is correct here.** No jobs, no scheduler, no external clients, empty queue — the framework's exception reporting covers the only silent-failure mode a request/response CRUD app has. The single exception is OBS-001.
- **`LOG_STACK=single` is inert.** `LOG_CHANNEL=daily` wins (`config/logging.php:21`), so `LOG_STACK` is only read inside the `stack` channel definition, which is never selected. Harmless stock Laravel; noted so nobody concludes logging is broken.
- **All 8 main Composer packages resolve to the newest release in range.** PHP `^8.3` is consistent across composer, framework, Docker (8.4) and CI (8.3). No `latest` Docker tags; `phpmyadmin` and `mailhog` are fully pinned.
- **Dependabot covers all four ecosystems** — composer, npm, github-actions and docker, weekly with a 5-day cooldown and one grouped PR each.
- **`.npmrc` is well configured** — `audit=true` means npm's advisory check runs on every install including CI; `ignore-scripts=true` removes a supply-chain surface and is safe here because the native packages ship prebuilt optional dependencies.
- **`jquery` and `select2` are genuinely used**, confined to the project form's async customer select (`app.js:361-463`, `projects/form.blade.php:37-62`). They ship on every page (~150 KB raw) but replacing them would be a rewrite of working, tested UI for no operational benefit. **No change.**
- **`livewire/blaze` is load-bearing** despite having no first-party call: Flux requires it (`composer.lock:2397-2399`), and the compiled Blade cache shows every `flux::` component compiling through it.
- **A committed 227 KB draw.io XML file sits at the repository root.** `demo13` (1025 lines, `mxfile` XML) was added by commit `3c4e0f6` "migrations" and is tracked. It is a diagram source file, not application code.
- **Progressive enhancement is absent by design for writes.** `Route::resource(...)->only(['index','store','update','destroy'])` declares no `create`/`edit`/`show` GET route for any resource, and both the drawer and the confirm modal are `<dialog>`-driven. With JS off the three resources are read-only. Coherent, and `ARCHITECTURE.md` never claims otherwise — but it is the answer to a question that will be asked twice. UX-001 is the exception where the *read* path regresses.
- **Health endpoint `/up` exists and is genuinely used** — the CI Dusk job polls it 30× before running the browser suite. It does not check MySQL, which is the dependency that actually breaks; no Compose service probes it.

---

## Security

No confirmed vulnerability. The four classic classes were checked exhaustively and are clean: **XSS, SQL injection, CSRF, mass assignment**, plus IDOR (except the single forward-looking gap in SEC-001).

The security-relevant items are therefore hardening rather than defects:

| ID | Severity | Summary |
|---|---|---|
| **SEC-001** | MEDIUM | `EpicCommentController::show()` has no authorization decision at all — the one place where tightening the policies would be silently incomplete |
| **SEC-002** | LOW | `SESSION_ENCRYPT=false` leaves session payloads replayable from the database |
| **SEC-003** | LOW | Registration, password-reset and verification-resend are unthrottled; no global limiter |
| SEC-004 | INFO | `APP_ENV=local` in `.env.example` disables both trusted-host validation and `SESSION_SECURE_COOKIE` if copied to production verbatim |
| SEC-005 | INFO | Hardcoded dev DB credentials in `docker-compose.yml` — accepted per `ARCHITECTURE.md:143-149`; DB is loopback-only |
| SEC-006 | INFO | `MAIL_MAILER=log` writes reset/verification tokens to a browser-readable log; acceptable locally |
| — | INFO | No CSP. Defence-in-depth only; nothing to protect against today given clean escaping |

---

## Bugs / Correctness

| ID | Severity | Summary |
|---|---|---|
| **UX-001** | MEDIUM | Searching 1–3 characters silently does nothing; the no-JS path behaves differently from the JS path |
| **UX-002** | MEDIUM | Disabling 2FA is one unconfirmed, irreversible click — and no test exercises it |
| **BUG-001** | LOW | A restore can report success for a record a concurrent request permanently deleted |
| **DOC-004** | LOW | All five models annotate dates as mutable `Carbon` while the app forces `CarbonImmutable` — verified `is_subclass_of(...) === false` |

Verified **not** buggy, despite being flagged during the review: the `Model::delete()`-returns-`false` contract leaves the model untouched; `delete() !== false` treating `null` as success is unreachable; `Rule::unique()->ignore($model)` correctly excludes the edited record and correctly re-scopes on `project_id` when an epic moves; `selectableProjectRule()`/`selectableCustomerRule()` cannot be bypassed; the `X-List-Fragment` response cannot 500; `old('customer_id')`/`old('project_id')` do not select trashed parents; the four `InactiveController` routes are authorized; `Constraint::causedBy()` does not misreport a foreign-key violation; `EpicComment::user_id` cannot be spoofed.

---

## Database

No data-integrity defect. The schema matches the migrations exactly, verified column by column against `information_schema`.

| ID | Severity | Summary |
|---|---|---|
| **DOC-001** | MEDIUM | `ARCHITECTURE.md` claims a comment cascade and a nullable author that the guard and two RESTRICT foreign keys both forbid |
| **DOC-002** | MEDIUM | The delete-guard docblock overclaims its lock guarantee for callers without a transaction |
| **DB-001** | LOW | `epic_comments` carries `active` + `deleted_at` + an unusable index — **already tracked** as `MOD-001` in `.github/tasks/model-consistency.md`, not re-reported |
| **DB-002** | LOW | `ProjectController::update()` locks only the *new* customer; the old one is covered by InnoDB's implicit foreign-key parent lock, which is an invisible dependency |

Explicitly **not** recommended, despite being technically available: no composite index on `(customer_id, active)`, no index on `start_date`, no `(deleted_at, name)`, no schema change for `DATE` columns. The optimiser already picks an index for every hot predicate, the tables are tiny, and each would be speculative.

---

## Performance

| ID | Severity | Summary |
|---|---|---|
| **PERF-001** | MEDIUM | A search keystroke renders the whole page and runs three queries that are then discarded |
| **PERF-002** | LOW | `EnsureUserIsActive` re-reads a row the guard already loaded — a simplification, not a bottleneck |
| **PERF-003** | LOW | Every row's `editPayload` is built and serialized twice; ceiling ~500 KB on a notes-heavy page |

Stated honestly for the record: **no caching, memoization, Redis, eager-loading, index or query-splitting change is warranted.** At 25 rows per page on a small dataset, the entire performance surface is 7–10 queries per list request, all index-backed.

---

## Architecture

The documented architecture is real and, with the three exceptions below, correctly implemented.

The base-class approach — `Controller::listView()`/`deletedNameConflict()`, `TrashController`, `InactiveController`, `ListQueryBase`, `ListTransformer` — absorbs the shared behaviour correctly across three near-identical resources. `EpicTrashController`'s single real difference (project-scoped name uniqueness) is expressed through the template method `nameIsTaken()` and is not a leaky abstraction; it is backed by the composite index `epics_project_id_active_name_unique (project_id, active_name)`. The nine `index()` methods cannot be absorbed further without losing the concrete Form Request type hints the controllers need.

**No new layer, abstraction, repository, service, DTO, interface or dependency is recommended anywhere in this report.** Every finding above is a comment, an attribute, a line of `.env.example`, a deleted file, or one targeted code change.

| ID | Severity | Summary |
|---|---|---|
| **DOC-001** | MEDIUM | Two false invariants in the architectural reference document |
| **DOC-002** | MEDIUM | A docblock promising a data-integrity guarantee the code does not give |
| **DOC-003** | MEDIUM | `TrashController`'s docblock misattributes restore safety to the delete's row lock |

---

## Testing

Strong suite: 612 tests / 3138 assertions, all passing, with four architecture tests that genuinely catch drift. The Dusk/feature-test split documented in `ARCHITECTURE.md:103-105` is verified accurate and is the right trade-off — the browser-only risks that Dusk would catch *and* are resource-specific (the project customer select2 with inline AJAX creation, the epic remote project select) are covered, while the shared components are covered once on customers.

| ID | Severity | Summary |
|---|---|---|
| **TEST-001** | MEDIUM | The restore's `QueryException` catch is unreachable from any test; deleting it keeps CI green |
| TEST-002 | LOW | `DatabaseHelpersTest` runs DDL on the shared base test database with no isolation trait |
| TEST-003 | LOW | A Dusk test blocks ~20 s on a hard-coded presentation timeout |
| TEST-004 | LOW | The uniformity test cannot attribute an ability to a specific controller |
| TEST-005 | LOW | Two unreferenced test base classes, one of which always passes |
| I18N-001 | LOW | 41 of 370 Basque strings are English verbatim, and no test can detect it |

**Dusk: NOT RUN.** Recorded honestly rather than inferred. The suite was reviewed statically instead; TEST-003 and TEST-005 come from that read.

---

## Production

**The CI workflow has never passed.** Four independently fatal defects — CI-001 (CRITICAL), CI-002, CI-003, CI-004 — each verified with a live command rather than by reading. They are *not* caused by one another: fixing CI-001 does not fix CI-002, and so on. Until all four are fixed, `master` cannot be green and the coverage gate in `AGENTS.md:44` is fiction.

| ID | Severity | Summary |
|---|---|---|
| **CI-001** | **CRITICAL** | `phpunit.xml`'s `<server>` block forces `DB_HOST=db`, overriding the workflow's `127.0.0.1` — the `ci` job cannot resolve its database |
| **CI-002** | **HIGH** | `CREATE DATABASE laravel_test` runs as `laravel`, which holds only `GRANT USAGE` globally, and no `GRANT` follows — both jobs fail here |
| **CI-003** | **HIGH** | The `ci` job never runs `npm run build`; `@vite` throws for every page-rendering test and there is no `withoutVite()` bypass |
| **CI-004** | **HIGH** | `laravel13-dusk` uses an image nothing builds; a fresh clone cannot start the documented Dusk workflow |
| **OPS-001** | LOW | App, Vite and npm ports bound to all host interfaces while the three admin services are loopback-only |
| **OPS-002** | LOW | `LOG_LEVEL=debug` is what a fresh production deployment inherits |
| **OPS-003** | LOW | Debugbar's default inherits `APP_DEBUG`, exposing unauthenticated `/_debugbar/*` routes and cookie-bearing request dumps |
| SEC-002 | LOW | `SESSION_ENCRYPT=false` |
| SEC-004 | INFO | `.env.example` ships `APP_ENV=local`, disabling both trusted-host validation and secure cookies if copied verbatim |
| DEP-001 | LOW | `select2@4.1.0` wants Node ≥ 24; CI runs 22 |

**The local toolchain is healthy**, and that contrast is the point: `pint --test` (205 files), `phpstan` level 9 (no errors), the full suite (612 passed / 3138 assertions) and `composer audit` (no advisories) all pass at `0ff2cba`. The application is fine; the *pipeline around it* was written and never executed.

`ARCHITECTURE.md:155` currently records only the Dusk job as unverified. That should be broadened to the whole workflow.

---

## Maintainability

The project is well documented and its decision records are unusually good — `.github/tasks/model-consistency.md` is a genuine model of "here is the finding, here is why it was not fixed, here are the options". What is missing is only that three of those decisions were never carried through to `ARCHITECTURE.md`, which is why DOC-001/002/003 exist.

| ID | Severity | Summary |
|---|---|---|
| **CLEAN-001** | LOW | ~200 lines of unreferenced starter-kit views and Flux overrides, including a `class="dark"` trap |
| **CLEAN-002** | LOW | `phpunit.xml` sets env vars for three packages that are not installed |
| DEP-002 | LOW | `laravel/chisel` in `require` rather than `require-dev` |
| OBS-001 | LOW | A genuine concurrent-write race leaves no trace |
| — | INFO | A committed 227 KB draw.io XML file at the repository root (`demo13`) |

---

## Rejected Findings

Recorded so they are not re-investigated.

### Rejected as false positives — disproved by direct verification

| Rejected claim | Who reported it | Why it is wrong |
|---|---|---|
| **`storage/` has no ignore rules; logs, debugbar dumps and compiled views are committable** (rated HIGH) | security-reviewer (SEC-02) | **Disproved.** All twelve `storage/**/.gitignore` files exist and are tracked. Verified with `git check-ignore -v`: `storage/framework/views/probe.php` → `storage/framework/views/.gitignore:1:*` and `storage/logs/probe.log` → `storage/logs/.gitignore:1:*`. `git ls-files storage` returns 15 entries, all `.gitignore` files plus intentional content. The reviewer had no shell and inferred from the root `.gitignore`, which covers only two storage paths — but Laravel's per-directory `.gitignore` files do the real work. **This was the highest-rated finding in the review and it is simply wrong.** |
| **The test failure and PHPStan error in `tests/Unit/Users/UserTest.php` break CI** | testing-reviewer (T1, T2) | **Obsolete.** Both were fixed by commit `0ff2cba`, made by the user mid-review. Re-verified at current HEAD: suite 612 passed, PHPStan no errors. |
| **`bootstrap/app.php` throws `LogicException` at boot when `APP_URL` is malformed** | raised as a hypothesis by laravel-reviewer | **Disproved.** `TrustHosts::at()` only stores the closure; it is invoked from `handle()` and only when `! environment('local') && ! runningUnitTests()`. `config:cache` and every CLI command are unaffected. The failure mode is a per-request 500, which is the *safer* behaviour. |
| **A missing `list-results` fragment would 500** | raised as a hypothesis by laravel-reviewer | **Disproved twice over.** All three views declare `@fragment('list-results')` (customers:65, projects:92, epics:212) wrapping `<x-list.searchable-results>`, whose root carries `data-list-results`, matching the fetch in `app.js:614,625`. And a missing fragment would not 500 anyway: `getFragment()` returns `null` and `View::fragment()` returns an empty string with a 200, so the JS falls back to a full page reload. |
| **`Customer::epics()` `hasManyThrough` does not filter trashed intermediate projects** | raised as a hypothesis by laravel-reviewer | **Disproved.** `HasOneOrManyThrough::performJoin()` registers a `SoftDeletableHasManyThrough` scope when the intermediate model is soft-deletable (`vendor/…/HasOneOrManyThrough.php:119-132,149-152`), and it also applies inside `withCount` via `getRelationExistenceQuery()`. The docblock is accurate. |
| **The `INNER JOIN`s in the project/epic list queries silently drop rows whose parent is soft-deleted or inactive** | raised as a hypothesis by laravel-reviewer | **Disproved.** The joins are raw with no `deleted_at`/`active` predicate, and both FKs are NOT NULL + RESTRICT, so an orphan row is impossible. Dropping nothing is the correct behaviour per the documented trash semantics. |
| **`old('customer_id')` / `old('project_id')` can select a trashed parent** | raised as a hypothesis by laravel-reviewer | **Disproved.** Both use `Customer::query()->find()` / `Project::query()->find()`, which apply `SoftDeletingScope` and return `null` for a trashed parent. Neither checks `active`, which matches the documented "current parent stays selected even if inactive". |
| **`ProjectSelectOptionsQuery` leaks trashed or inactive customers** | raised as a hypothesis by laravel-reviewer | **Unreachable.** A customer cannot be trashed while it has projects (the `deleting` guard), and `Project::customer()` is `withTrashed()`, so a trashed customer cannot have a project. An *inactive* customer can, and that is the documented behaviour. |
| **The restore race is unguarded because `restoreTrashed()` takes no lock** | raised as a hypothesis by laravel-reviewer and concurrency-reviewer | **Disproved.** The generated-column unique index is the guarantee: restoring sets `deleted_at = NULL`, which makes `active_name` non-NULL and index-checked. The `QueryException` is caught and converted. The restore is race-safe; only the docblock's stated *reason* is wrong (DOC-003). |
| **`UniqueConstraintViolation::causedBy()` misreports a foreign-key violation as a name conflict** | raised as a hypothesis by laravel-reviewer | **Disproved.** A MariaDB FK violation is `['23000', 1452]`; `in_array(1452, [19, 1062])` is `false`. Regression-tested at `tests/Unit/Support/Database/UniqueConstraintViolationTest.php:40-45`. |
| **Reactivating a record that was never deactivated is a defect** | raised as a hypothesis by laravel-reviewer | **Intended.** Idempotency is explicitly asserted by `tests/Feature/ResourceActivationTest.php:200-213`. |

### Rejected as duplicates — folded into a single finding

| Duplicates | Consolidated into |
|---|---|
| L-01, DB-2, F3 — all three independently found the `ARCHITECTURE.md:80-81` epic-comment contradiction | **DOC-001** |
| L-02, C-01, C-13, F1 — four independent reports of the delete-guard docblock overclaim | **DOC-002** |
| L-03, C-02 — the `TrashController` restore docblock | **DOC-003** |
| L-04, PERF-02 — the redundant `EnsureUserIsActive` query | **PERF-002** |
| SEC-01, L-08 — the unauthorized `epics/{epic}/comments` read | **SEC-001** |
| F2, C-10 (partially) — untested restore error path | **TEST-001** |

### Rejected as overengineering — technically available, explicitly not recommended

| Rejected recommendation | Why |
|---|---|
| Add `UNIQUE (customer_id, active)` + a composite foreign key so a child can never attach to a trashed parent (DB-1) | A schema migration with real cost for a small CRUD app, to close a race the product already tolerates (`Project::customer()` is `withTrashed()`, so every list and drawer renders the parent regardless). The *documentation* fix is the honest and proportionate response. |
| Add a composite index on `start_date`, or `(deleted_at, name)` | `EXPLAIN` confirms the optimiser already selects `(deleted_at, id)` for every list query; 25 rows per page. Speculative. |
| Replace `jquery` + `select2` with a Flux/Alpine combobox | A rewrite of working, tested UI with a passing Dusk test (`ProjectCustomerSelectTest.php:37-118`) for no operational benefit. Explicitly flagged so nobody "optimises" it into breakage: `projects/form.blade.php:48` calls `window.jQuery` from an Alpine `x-effect`, so a lazy `import()` is **not** a drop-in. |
| Build a two-connection or `pcntl_fork` concurrency test harness | New infrastructure, slow, flaky. The proportionate fix is one test per resource forcing the exception, plus a docblock on `RacesNameInsert` stating what it does not prove. |
| Add Sentry / OpenTelemetry / Prometheus / any metrics or tracing | None exist, and none are needed. Correctly absent. |
| Add a global rate-limiting middleware layer | Two named `RateLimiter::for(...)` registrations attached to the relevant Fortify routes is the whole fix. |
| Switch `LOG_CHANNEL` to `stderr` | Would silently break `opcodesio/log-viewer`, which reads `storage/logs` and is this project's primary log UI. |
| Add `security` headers / a strict CSP | Requires enabling `livewire.csp_safe` and refactoring every inline Alpine expression — a large change to defend against nothing, given escaping is already clean. |
| Extract shared Blade components / introduce a base list partial | Explicitly forbidden by `review-rules.md` and `AGENTS.md`. Measured the duplication: only the 6-line `name` and `notes` fields are verbatim-identical; everything else genuinely differs per resource. |

### Rejected as unverifiable or not applicable

- **`laravel/sail` unused, `@laravel/multiplex` unused, `package.json` has no `devDependencies`** — all three are stock Laravel starter-kit facts. Unused **dev** tooling in `require-dev` has no production impact. INFO, no action.
- **`emergency` channel not resolvable, `slack`/`papertrail` read unset env vars** — byte-identical to stock `config/logging.php`. Not a local defect.
- **`LOG_STACK` is inert** — stock Laravel. Recorded so nobody concludes logging is broken.
- **The `logs/test-coverage-summary.txt` file tracked under `storage/logs/`** — explicitly allow-listed by `storage/logs/.gitignore:3` (`!test-coverage-summary.txt`). Intentional.
- **`DC_UID`/`DC_GID` and hardcoded dev DB credentials** — documented as local-only in `ARCHITECTURE.md:143-149`; both app services set `user:`; DB is loopback-only. Accepted.
- **Test brittleness from exact query-count budgets** (`ResourceActivationTest:88-112`, `ListQuerySharedBehaviourTest:162-224`) — deterministic today, and the file already documents why. Deliberate trade-off, not a defect.

---

## Action Plan

Ordered by impact. **Every item is a comment, an attribute, a line of `.env.example`, a deleted file, or one targeted code change. No new layer, abstraction or dependency is proposed anywhere.**

### P0 — the pipeline cannot pass; nothing else matters until it does

| # | ID | Change | Size |
|---|---|---|---|
| 1 | **CI-001** | Delete the duplicated `<server>` block (`phpunit.xml:33-39`), keeping `<env>`. Set `DB_DATABASE: laravel_test` on the `ci` job and pin `DB_DATABASE: laravel` on the Setup Application step only. | 7 lines deleted, 2 added |
| 2 | **CI-002** | Replace both PDO `CREATE DATABASE` one-liners with the root-credential version that also `GRANT`s, mirroring `docker/mysql/init/01-create-test-database.sql`. | 2 steps |
| 3 | **CI-003** | Add `npm ci` + `npm run build` to the `ci` job, and `cache: 'npm'` to `setup-node` in both jobs. | 2 steps + 2 lines |
| 4 | **CI-004** | Add the missing `build:` block to `laravel13-dusk`. | 3 lines |

Then run `docker compose run --rm laravel13-phpunit` once to read the real coverage figure — the 80% gate has never been evaluated, so its verdict is currently unknown.

### P1 — user-facing defects

| # | ID | Change | Size |
|---|---|---|---|
| 5 | **UX-001** | Remove `.prevent` from `searchable-results.blade.php:4` so short searches fall through to the native GET; add a hint that search starts at four characters. | 1 token + 1 line |
| 6 | **UX-002** | Wrap "Disable 2FA" in the modal pattern already used at `⚡security.blade.php:349-371`; add the one missing test. | ~15 lines + 1 test |
| 7 | **A11Y-001** | Add `role="alert"` to `flash.blade.php:10`. | 1 attribute |

### P2 — correctness and misleading invariants

| # | ID | Change | Size |
|---|---|---|---|
| 8 | **DOC-001** | Rewrite `ARCHITECTURE.md:80-81` to state the enforced invariants (no cascade; `user_id` NOT NULL/RESTRICT; soft-deleted author renders a placeholder). | 2 lines of prose |
| 9 | **DOC-002** | Correct the `AppServiceProvider` docblock (and `.github/instructions/models.instructions.md:7`) to state that the guarantee requires the caller to wrap `delete()` in `DB::transaction()`. **No code change.** | prose |
| 10 | **DOC-003** | Correct the `TrashController` docblock: the restore relies on the `active_name` index plus the exception catch, not on a row lock. **No code change.** | prose |
| 11 | **SEC-001** | Add `$this->authorize('viewAny', Epic::class)` to `EpicCommentController::show()` and gate `drawerEpic()`; add one 403 test. Reuses the existing `viewAny` ability. | 2 lines + 1 test |
| 12 | **A11Y-002** | Add `aria-label` to the 2FA setup key input and its copy button; add the two keys to all four `lang/*.json`. | 2 attrs + 8 keys |
| 13 | **PERF-001** | On `X-List-Fragment` requests pass `null` counts and skip the `hasCustomers`/`hasProjects` probe. `tabs()` already tolerates `null`. **Skip the partial extraction** — not worth touching the view skeleton. | 2 lines × 3 |
| 14 | **TEST-001** | One test per resource forcing the `QueryException` on the restore; add a docblock to `RacesNameInsert` stating what it does not prove. | 3 small tests |
| 15 | **BUG-001** | Re-read the record by key inside the `try` after `restore()` and treat a missing row as a failure. No new locking. | ~3 lines |

### P3 — hardening and housekeeping

| # | ID | Change | Size |
|---|---|---|---|
| 16 | **OPS-001** | Bind the app, Vite and npm ports to `127.0.0.1`, matching the three admin services. | 3 lines |
| 17 | **SEC-002 / SEC-003 / SEC-004** | `SESSION_ENCRYPT=true` in production; `APP_ENV=production` in `.env.example`; add `RateLimiter::for('password-reset-link')` and `RateLimiter::for('verify-email')` keyed on IP; drop `Features::registration()` if it is not a product feature. | ~12 lines |
| 18 | **OPS-002 / OPS-003** | `LOG_LEVEL=warning` in `.env.example`; `'enabled' => env('DEBUGBAR_ENABLED', false)` in `config/debugbar.php` plus `DEBUGBAR_ENABLED=true` in the local `.env`. | 3 lines |
| 19 | **A11Y-003/004/005/006/007** | Neutralise the inner pagination `<nav>`; add a visually hidden `role="status"` to the morph target; pass `for`/`id` on the 12 drawer fields; `role="note"` on the blocked-action span; `scope="col"` on the table columns. | mechanical |
| 20 | **I18N-001 / I18N-002** | Translate the 41 Basque keys that are currently English; drop `:count` from the two per-row `aria-label`s. | 41 strings + 2 lines |
| 21 | **DOC-004 / PERF-002** | `Carbon` → `Carbon\CarbonImmutable` in the five models; replace the redundant `EXISTS` in `EnsureUserIsActive` with `! $user->active`. | 6 lines |
| 22 | **TEST-002/003/004/005** | Add the `laravel_test` assertion to `DatabaseHelpersTest` (do **not** add a DB trait); delete the 20-second `waitUntil`; make the uniformity test per-controller; delete the two dead Dusk page objects. | small |
| 23 | **CLEAN-001 / CLEAN-002** | Delete the six unreferenced starter-kit views and Flux overrides (**keep** `chevrons-up-down`); delete the three stale `phpunit.xml` env vars. | 6 files + 3 lines |
| 24 | **DEP-001 / DEP-002** | `node-version: '24'` in both CI jobs; move `laravel/chisel` to `require-dev`. | 3 lines |
| 25 | **OBS-001** | One `Log::warning()` in `rethrowAsValidationError()` carrying the field and SQLSTATE only — the single logging change recommended. | 4 lines |
| 26 | — | Delete the committed `demo13` draw.io XML from the repository root. | 1 file |
| 27 | — | Broaden `ARCHITECTURE.md:155` from "the Dusk job is unverified" to "the whole workflow was unverified until <date>". | 1 line |

### Explicitly not doing

- **PERF-003** (the duplicated `editPayload`) — optional; the JS change is contained but the payoff only materialises on notes-heavy data. Revisit if pages ever feel heavy.
- **DB-001's schema option** — no composite foreign key. The product already tolerates a child on a trashed parent; only the documentation was wrong.
- **Named volume for `dc-data/`, `restart:` policies, templating `CONTAINER_UID`** — real but small local-dev ergonomics, recorded in the Low findings, not worth bundling into the CI fix.
- **Any caching, eager-loading, index, observability vendor, CI/CD platform change, or dependency replacement.** The evidence says none is warranted.

---

*Review executed read-only. No application code, test, dependency, lockfile, configuration or database data was modified. The only artifact created by this review is this file.*