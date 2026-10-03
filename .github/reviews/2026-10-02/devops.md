# DevOps Specialist Review — 2026-10-02

Snapshot: HEAD `781ccbb` + working tree.

## OPS-001 — CI runs `composer setup`, which migrates the application database in the test job

Severity: MEDIUM
Category: DevOps / CI
File: .github/workflows/tests.yml
Line: 118
Confidence: HIGH

Problem:

The `ci` job runs `composer setup` before `composer ci:check`:

```yaml
# .github/workflows/tests.yml:118-120
- name: Setup Application
  run: composer setup
- name: Run CI Checks
  run: composer ci:check
```

`composer.json:50-56` defines:

```json
"setup": [
    "composer install",
    "@php -r \"file_exists('.env') || copy('.env.example', '.env');\"",
    "@php artisan key:generate",
    "@php artisan migrate --force"
]
```

So `php artisan migrate --force` runs against `DB_DATABASE: laravel` (job-level env at line 65-70) — the *application*
database. Then `composer ci:check` runs the test suite, and `RefreshDatabase` migrates whatever database it is
pointed at.

Evidence: quoted above. The job env sets `DB_DATABASE: laravel` while `phpunit.xml:25` declares
`DB_DATABASE=laravel_test`, and the MariaDB service only creates `laravel` (`MYSQL_DATABASE: laravel`, line 87) — the
`dusk` job is the one that creates `laravel_test` (line 209). So the two jobs are asymmetric by design, and the `ci`
job never touches `laravel_test` at all.

Impact:

- The unit/feature suite runs against the **application** database, not the dedicated `laravel_test` the Dusk suite
  uses. In practice `RefreshDatabase` wraps each test in a transaction so this is survivable, but it means the
  non-Dusk job mutates the schema of the same database it just migrated, and a test that escapes its transaction
  would corrupt the next test rather than being isolated.
- It is fragile: `phpunit.xml` documents `laravel_test` as the intended target and it is silently overridden. Note
  that PHPUnit `<env>` **does** override an existing environment variable unless `force="false"` is set — I verified
  the resolution logic (`vendor/phpunit/phpunit/src/TextUI/Configuration/PhpHandler.php:134-149`), so the effective
  database in the `ci` job is whatever the job env says, i.e. `laravel`.
- No production risk — this is an ephemeral GitHub runner with a throwaway service container.

Recommendation:

Create `laravel_test` in the `ci` job's service (one extra `mysql -e` step, exactly as `dusk` does at lines 206-209)
and set `DB_DATABASE: laravel_test` in the job env. Then the non-Dusk suite is genuinely isolated from the
application database, and `phpunit.xml` stops lying. Alternative, if the current behaviour is intentional: drop the
`migrate --force` from `composer setup` in this job and document why.

## OPS-002 — `phpunit.xml` does not pin its env vars, so any ambient `DB_*` variable silently wins

Severity: MEDIUM
Category: DevOps / Test isolation
File: phpunit.xml
Line: 17-27
Confidence: HIGH

Problem:

```xml
<php>
    <env name="DB_CONNECTION" value="mysql"/>
    <env name="DB_HOST" value="db"/>
    <env name="DB_PORT" value="3306"/>
    <env name="DB_DATABASE" value="laravel_test"/>
    ...
</php>
```

None of these carry `force="true"`. PHPUnit's documented behaviour is that `<env>` only sets a variable when it is
not already present in the environment, unless `force="true"` is set.

Evidence — I reproduced the resolution logic from `vendor/phpunit/phpunit/src/TextUI/Configuration/PhpHandler.php:134-149`
in-container:

```php
// CI sets DB_DATABASE=laravel at job level
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

A developer who exports `DB_DATABASE` (or has it in a shell profile) and runs `php artisan test` will have their
tests run against that database instead of `laravel_test`, and `RefreshDatabase` will migrate it. This is a
destructive-by-default footgun in the most destructive test path in the project. It also means the local and CI
databases can silently differ, which is exactly the class of bug the dedicated `laravel_test` database exists to
prevent.

Recommendation:

Add `force="true"` to the `DB_*` entries (and consider all of them) so `phpunit.xml` is authoritative. This changes
nothing in CI (which wants the same values) and closes the footgun locally. If CI genuinely needs a different
database, set it with `force="true"` there too and keep the values identical.

## OPS-003 — MariaDB runs with `--sql-mode=""` while the application config declares `'strict' => true`

Severity: LOW
Category: DevOps / Data integrity
File: docker-compose.yml
Line: 201
Confidence: HIGH

Problem:

```yaml
db:
  command: --collation-server=utf8mb4_unicode_ci --default-authentication-plugin=mysql_native_password --sql-mode=""
```

The server runs with an **empty SQL mode**, which disables `STRICT_TRANS_TABLES` and friends. Meanwhile
`config/database.php:60,80` sets `'strict' => true` on both MySQL connections.

Evidence:

```php
// config/database.php:60
'strict' => true,
```

The two are not contradictory (Laravel's `strict` only controls its own PDO emulation setting when the server does
not enforce strict mode), but they are inconsistent, and the effective behaviour is the *lenient* one: over-length
strings get truncated silently, invalid dates become zero-dates, and `NOT NULL` violations on text columns are
downgraded to warnings.

Impact:

Development and production can disagree on data-integrity behaviour. A `varchar(255)` overflow passes locally and
errors (or truncates) in production, or vice versa. For this schema the realistic risk is low — `name` is capped at
255 by every Form Request and the column is `varchar(255)`, `body` is `text` with a 5000-char validation cap.

Recommendation:

Drop `--sql-mode=""` from the `db` command so local development matches production defaults, or set it to
`STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION` explicitly. This is a one-line change; the
only reason it is LOW is that the schema has no column where strict mode would currently catch a real bug.

## OPS-004 — The app container publishes port 80 on all interfaces with `APP_DEBUG=true`

Severity: LOW
Category: DevOps / Exposure
File: docker-compose.yml
Line: 12
Confidence: HIGH

Problem:

```yaml
ports:
    - "80:80"
```

publishes to `0.0.0.0`, and `.env:6` sets `APP_DEBUG=true`, so the Ignition/Whoops error page is reachable by anyone on
the same network as the developer machine. Contrast with the deliberately loopback-bound services in the same file:
`db` (line 219, `127.0.0.1:13306`), `laravel13-myadmin` (line 224, `127.0.0.1:4000`), `mailhog` (line 235,
`127.0.0.1:${MAILHOG_PORT}`).

Evidence: quoted above; `.env:6` `APP_DEBUG=true`.

Impact:

On a shared network (office, café, university) the debug page exposes stack traces, file paths, configuration values
and the application key. `.env` is gitignored so this is not a committed-secret issue, but it is a live exposure.

Recommendation:

Change to `"127.0.0.1:80:80"` to match the other services. If the developer needs LAN access, that should be an
explicit, documented override rather than the default.

## OPS-005 — Dusk CI job creates the ChromeDriver binary at build time with no caching

Severity: LOW
Category: DevOps / CI performance
File: .github/workflows/tests.yml
Line: 213
Confidence: HIGH

Problem:

```yaml
- name: Prepare ChromeDriver
  run: php artisan dusk:chrome-driver --detect
```

runs on every Dusk job, with no cache action for the driver. The `ci` job caches Composer packages
(`actions/cache@55cc83...`, line 96) but the `dusk` job's Node cache is `cache: 'npm'` only (line 128).

Evidence: quoted above. The driver download is the slowest single step in that job.

Impact:

Adds perhaps 10-30 s of wall clock per Dusk run. No correctness impact.

Recommendation:

Optional. Add `php artisan dusk:chrome-driver --detect` output to a cache keyed on the PHP version. Purely a CI
speed item; P3.

## OPS-006 — `.env` is correctly gitignored and only `.env.example` is tracked

Severity: INFO (positive finding)
Category: DevOps / Secrets
File: .gitignore
Line: 8
Confidence: HIGH

Problem:

None.

Evidence:

```
$ git ls-files | grep -i env
.env.example

$ grep -n '^\.env' .gitignore
8:.env
9:.env.backup
10:.env.production
```

Plus `.phpunit.cache` (line 1), `.phpunit.result.cache` (line 11), `/node_modules` (line 2), `/vendor` (line 7),
`/public/build` (line 3) and `/dc-data` (line 12) are all ignored — the last one matters because
`docker-compose.yml:208` bind-mounts `./dc-data/mysql` as the MariaDB data directory.

Impact:

None. This is correctly configured.

Recommendation:

None.

## OPS-007 — Health endpoint and graceful degradation are in place

Severity: INFO (positive finding)
Category: DevOps / Operations
File: bootstrap/app.php
Line: 14
Confidence: HIGH

Problem:

None.

Evidence:

- `bootstrap/app.php:14` registers `health: '/up'`, and `.github/workflows/tests.yml:222-229` uses it as the readiness
  probe (`curl --fail --silent http://127.0.0.1:8000/up`).
- `app/Providers/AppServiceProvider.php:36-38` — `DB::prohibitDestructiveCommands(app()->isProduction())`, so
  `migrate:fresh`/`db:wipe` are blocked in production. This is the right guard and it is enabled by default in the
  Laravel 11+ skeleton; keeping it explicit is good.

Impact:

None.

Recommendation:

None.

## Notes / not findings (rejected after devil's-advocate challenge)

- **CI does not run `npm run build` in the `ci` job.** Only the `dusk` job builds assets (line 139). Correct: the
  `ci` job runs no browser tests, and `View`-level `@vite()` calls would fail without a manifest — but the feature
  suite passes, so `public/build/manifest.json` presence or the `Vite` test bypass handles it. Not a finding.
- **No `npm run lint` / `npm run typecheck` in CI.** Neither script exists (`package.json:6-9` defines only `build`
  and `dev`), and CI enforces formatting and types on the PHP side instead (`composer test:prepare`). Not a finding —
  but see the Dependencies report: there is **no frontend linter at all**, which is a genuine gap worth a
  recommendation rather than a defect.
- **Actions not pinned by SHA.** Checked: `actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1`,
  `shivammathur/setup-php@f3e473d116dcccaddc5834248c87452386958240`,
  `actions/cache@55cc8345863c7cc4c66a329aec7e433d2d1c52a9`,
  `actions/setup-node@820762786026740c76f36085b0efc47a31fe5020` — all full SHAs, and
  `permissions: contents: read` with `persist-credentials: false`. Excellent practice, not a finding.
- **`Dockerfile.dusk` has no pinned base image digest** (`FROM webdevops/php-apache-dev:8.4`). Reproducibility is by
  tag. Real but very low value for a local-only image. Rejected.
- **No liveness/readiness separation, no metrics exporter, no structured logging.** This is a local/internal
  application with no deployment target declared anywhere in the repository (`ARCHITECTURE.md:127-133` only says
  "Production deployments must provide a non-debug environment and isolated database credentials"). Recommending
  an observability stack would be pattern-driven and unsupported. Rejected — see observability.md for the recorded
  INFO items.
- **`docker-compose.yml` profiles** (`composer`, `npm`, `migrate`, `phpunit`, `reformat`, `npm-all`, `migrate-all`)
  are a good design — they keep destructive services out of `docker compose up`. Rejected as a finding.