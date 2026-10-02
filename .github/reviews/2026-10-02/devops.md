# DevOps Review — 2026-10-02

## Findings

### DEV-001 — The dev application is reachable from the whole local network

Severity: MEDIUM
Category: DevOps / Exposure
File: docker-compose.yml
Line: 18-20
Confidence: HIGH

Problem: `laravel13` publishes `"80:80"` and `"${VITE_PORT:-5173}:${VITE_PORT:-5173}"`, which bind
`0.0.0.0`. Every other service in the file is explicitly loopback-bound:
`127.0.0.1:13306:3306` (db), `127.0.0.1:4000:80` (phpMyAdmin), `127.0.0.1:8025:8025` (MailHog).

Evidence:

```console
$ docker compose ps
demo13-db-1                 ... 127.0.0.1:13306->3306/tcp
demo13-laravel13-1          ... 0.0.0.0:80->80/tcp, 0.0.0.0:5173->5173/tcp
demo13-laravel13-myadmin-1  ... 127.0.0.1:4000->80/tcp
demo13-mailhog-1            ... 127.0.0.1:8025->1025/tcp
```

Impact: on a shared network (office, café, university) the dev app — with its seeded
`test@example.com` user (`database/seeders/DatabaseSeeder.php:37-40`), `BCRYPT_ROUNDS=12` and
`DB laravel/laravel` — is reachable by anyone. Combined with
`.github/docs/architecture/ARCHITECTURE.md:130-131` ("Administrative Docker ports and development
credentials are for local development only"), the current config does not match the documented
intent.

Recommendation: `ports: ["127.0.0.1:80:80", "127.0.0.1:${VITE_PORT:-5173}:${VITE_PORT:-5173}"]`,
consistent with the other services. Also move `MYSQL_ROOT_PASSWORD: tormenta` into an env var.

### DEV-002 — PHP version drifts between CI and local Docker

Severity: LOW
Category: DevOps / Consistency
File: .github/workflows/tests.yml, composer.json, docker-compose.yml, .github/docs/architecture/ARCHITECTURE.md
Line: 78 / 13 / 6 (multiple services) / 129
Confidence: HIGH

Problem: `composer.json` requires `php: ^8.3`; CI runs `php-version: '8.3'`; every Docker service
uses `webdevops/php-apache-dev:${DC_PHP:-8.4}` or `FROM webdevops/php-apache-dev:8.4`. The
architecture document records this ("CI runs PHP 8.3 and Node 22; local Docker currently runs
PHP 8.4"), so it is a known, documented gap rather than an accident.

Impact: 8.4-only syntax or behaviour (property hooks, asymmetric visibility) would pass Pint and
PHPStan locally and fail CI. Nothing in `app/` uses it today.
Recommendation: when convenient, pin the local image to 8.3 as well, or add a
`php -r 'echo PHP_VERSION;'` guard in CI output so the difference is visible in the log.

### DEV-003 — The CI `ci` job does not cache npm

Severity: LOW
Category: DevOps / CI cost
File: .github/workflows/tests.yml
Line: 88-91
Confidence: HIGH

Problem: `Setup Node` in the `ci` job has no `cache: 'npm'`; the `dusk` job does (line 137).
The `ci` job never runs `npm install`/`npm run build` (it only uses PHP), so the missing cache is
pure waste of a step, not a bug.

Impact: negligible runtime cost (the `ci` job does not build assets). Reported for consistency only.

### DEV-004 — CI is skipped entirely for `.md`-only changes, including `.github/workflows` docs

Severity: INFO
Category: DevOps / CI design
File: .github/workflows/tests.yml
Line: 22-42
Confidence: HIGH

Problem: the `changes` job sets `code=false` when the diff contains only `.md` files, and both
`ci` and `dusk` are gated on it.

Assessment: this is intentional and correct — docs-only PRs need no test run. Recorded so nobody
"fixes" it. Note that `.github/workflows/tests.yml` and `phpunit.xml` are not `.md`, so real
pipeline changes do trigger a run.

## Verified clean (no findings)

- **All action references are SHA-pinned** with version comments, and `permissions: contents: read`
  is the workflow default — least privilege.
- **Concurrency group** with `cancel-in-progress: true` prevents wasted runners on push.
- **`persist-credentials: false`** on every checkout.
- **Matrix-free, two-job pipeline**: `ci` (Pint + PHPStan level 9 + PHPUnit) and `dusk`, with
  `dusk` correctly gated on `needs: [changes, ci]`.
- **Dusk job is self-contained**: fresh `laravel_test` database via PDO, `php artisan
  dusk:chrome-driver --detect`, readiness polling on `/up` with log dump on failure.
- **CI uses the Node version the project declares** (22) and `npm ci`.
- **`composer ci:check`** = `config:clear` + `pint --test` + `phpstan` + `artisan test`. Correctly
  does **not** run `composer setup`'s `migrate --force` again.
- **Docker**: `db` is pinned to `mariadb:11.7`; service healthchecks use `healthcheck.sh` with
  retries; volumes are bind mounts into `./dc-data` (gitignored); containers run as
  `${DC_UID}:${DC_GID}`, not root.
- **`config:cache`-free containers**: `APP_CONFIG_CACHE` is redirected to `/tmp` for both app
  services, so the mounted source tree is never polluted with a cached config.
- **Secrets**: `.env` is gitignored, `APP_KEY` is empty in `.env.example`, and no credential is
  committed except the local-only DB root password flagged in DEV-001.