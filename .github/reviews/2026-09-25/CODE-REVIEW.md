# Code Review

Audit date: 2026-09-25
Mode: READ-ONLY

## Executive Summary

The selected review findings have been fixed and validated. Email verification is now enforced, restore conflicts return a controlled response, duplicate-key races are converted into validation errors, unsafe migration rollback is rejected before schema mutation, and the Dusk/password-reset follow-up work is in place. Lockfile reproducibility remains open in the current Compose file.

## Detected Stack

- PHP 8.4.25 and Composer 2.10.3 in Docker; Laravel 13.33.0.
- Fortify 1.40.0, Livewire 4.4.6, Flux 2.20.0, Blaze.
- MariaDB 11.7/MySQL-compatible database; database queue, cache and sessions.
- Blade, Livewire, Flux, Vite Plus and Tailwind CSS.
- PHPUnit 12.5.35, Larastan 3.12.2, Pint 1.32.1 and Dusk 8.7.0.
- GitHub Actions CI and Docker Compose. No API surface, Redis service, Horizon or application jobs were found.

## Checks Executed

| Check | Result |
| --- | --- |
| `docker compose config --quiet` | PASS |
| `php artisan about --only=environment` in Docker | PASS; Laravel 13.33.0, PHP 8.4.25 |
| `php artisan route:list --except-vendor` in Docker | PASS; 11 routes listed |
| `vendor/bin/phpstan analyse --no-progress` in Docker | PASS; no errors |
| `vendor/bin/pint --test` in Docker | PASS; 73 files checked |
| `composer audit --no-interaction --format=plain` in Docker | PASS; no advisories |
| `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --compact` in Docker | PASS; 74 tests, 221 assertions |
| Frontend build | NOT RUN; host has Node 18 while CI declares Node 22, and the build writes generated assets |
| Dusk | NOT RUN locally; CI job is configured but no isolated browser session was available |

PHP and Composer are unavailable on the host, so PHP checks ran in the existing Docker image. The test suite used in-memory SQLite and did not modify persistent MariaDB data.

## Critical Findings

None.

## High Findings

### SEC-001 — Email verification middleware is ineffective

Severity: HIGH
Category: Security / Authentication
File: `app/Models/User.php`
Line: 27
Confidence: HIGH
Status: RESOLVED

Problem:

Customer routes declare `verified`, but the `User` model does not implement `MustVerifyEmail`.

Evidence:

`User` implements only `PasskeyUser` at `app/Models/User.php:27`; the `MustVerifyEmail` import is commented out at line 4. Laravel's `EnsureEmailIsVerified` middleware only checks verification for users implementing that contract. Customer routes use `auth` and `verified` at `routes/web.php:9`.

Impact:

An authenticated but unverified account can reach customer list, create, update, delete, restore and permanent-delete actions. This contradicts the documented route boundary.

Recommendation:

Implement `Illuminate\Contracts\Auth\MustVerifyEmail` on `User` and use Laravel's verification trait/contract pattern, or remove `verified` if verification is not a product requirement. Add a feature test with `User::factory()->unverified()`.

### COR-001 — Restore does not handle reused active names

Severity: HIGH
Category: Correctness / Data integrity
File: `app/Http/Controllers/CustomerTrashController.php`
Line: 33
Confidence: HIGH
Status: RESOLVED

Problem:

The restore action calls `restore()` without handling an active customer that already uses the deleted customer's name.

Evidence:

`$customer->restore()` is unconditional at `app/Http/Controllers/CustomerTrashController.php:33`. The migration creates an active-name unique constraint at `database/migrations/2026_09_25_090000_allow_reusing_deleted_customer_names.php:27-29`, while the create flow explicitly permits reusing a deleted name at `app/Http/Controllers/CustomerController.php:32-43`.

Impact:

A valid restore attempt can throw an uncaught database uniqueness exception and return a 500 response instead of a controlled conflict response. The `resolve_name_conflict` flag only changes the success message after the restore.

Recommendation:

Define the restore conflict policy, check it atomically before restoring, and return a validation/conflict response or an explicit rename/replace outcome. Add a feature test with both an active and soft-deleted customer sharing a name.

## Medium Findings

### DB-001 — Uniqueness race returns an unhandled exception

Severity: MEDIUM
Category: Database / Concurrency
File: `app/Http/Controllers/CustomerController.php`
Line: 32
Confidence: HIGH
Status: RESOLVED

Problem:

Form Request uniqueness validation is separated from the database write.

Evidence:

The request performs a uniqueness query at `app/Http/Requests/CustomerRequest.php:30-32`, then `Customer::create()` writes at `app/Http/Controllers/CustomerController.php:42`. The database constraint remains the final invariant.

Impact:

Concurrent create/update/restore requests can both pass validation; the losing request can surface an uncaught `QueryException` rather than a user-facing conflict.

Recommendation:

Keep the database constraint and convert duplicate-key failures into the normal validation/conflict response. Make restore conflict handling atomic.

### DB-002 — Migration rollback can fail after valid name reuse

Severity: MEDIUM
Category: Database / Deployment
File: `database/migrations/2026_09_25_090000_allow_reusing_deleted_customer_names.php`
Line: 47
Confidence: HIGH
Status: RESOLVED

Problem:

The rollback recreates a global unique index on `name` after the forward migration permits duplicate names among soft-deleted rows.

Evidence:

The forward migration uses an active-only uniqueness structure at lines 21-29. The `down()` method calls `$table->unique('name')` at lines 47-49, which conflicts with a valid active/deleted duplicate state.

Impact:

A production rollback can fail after deleted names have been reused.

Recommendation:

Provide a deliberate data migration/policy before restoring the old constraint, or document and enforce that this migration is not safely reversible once reuse has occurred.

### OPS-001 — Compose build profiles discard lockfiles

Severity: MEDIUM
Category: DevOps / Supply chain
File: `docker-compose.yml`
Line: 68
Confidence: HIGH
Status: OPEN

Problem:

The Composer and npm profiles remove committed lockfiles and resolve dependencies afresh; npm also installs `npm@latest`.

Evidence:

The Composer profile removes `composer.lock` at lines 68-70. The npm-all profile removes `package-lock.json` at lines 103-109 and installs `npm@latest`.

Impact:

Rebuilds are not reproducible and can silently run different dependency versions from the reviewed lock state.

Recommendation:

Preserve lockfiles, use `composer install` and `npm ci`, and pin the container and npm versions or digests.

### OPS-002 — Local Compose has no MariaDB health check

Severity: MEDIUM
Category: Production readiness
File: `docker-compose.yml`
Line: 27
Confidence: HIGH
Status: RESOLVED

Problem:

Services depend on `db` for startup ordering, but the MariaDB service has no health check.

Evidence:

Application, Dusk, migration and PHPUnit services use `depends_on: - db` at lines 27-28 and related blocks; the `db` service at lines 194-207 has no `healthcheck`.

Impact:

Migrations or requests may start before MariaDB accepts connections, causing startup failures or flaky local/CI-like runs.

Recommendation:

Add a MariaDB health check and use `condition: service_healthy`, with bounded application retry behavior where needed.

## Low Findings

### OPS-003 — MailHog is exposed beyond loopback

Severity: LOW
Category: DevOps / Local security
File: `docker-compose.yml`
Line: 225
Confidence: HIGH
Status: RESOLVED

Problem:

The development MailHog port is published on all host interfaces.

Evidence:

`mailhog` uses `${MAILHOG_PORT:-8025}:8025` at `docker-compose.yml:225`, unlike the MariaDB and phpMyAdmin bindings that use `127.0.0.1`.

Impact:

Captured development email and the unauthenticated MailHog UI may be reachable by other machines on the local network.

Recommendation:

Bind MailHog to `127.0.0.1` or document the intentional exposure as a local-only exception.

## Informational Findings

No additional accepted informational findings. The application has no API routes, application jobs, Redis/Horizon integration, or external HTTP integrations to review.

## Security

SEC-001 is resolved. Customer policy methods returning `true` were not treated as a finding because the architecture explicitly permits every authenticated verified user and defines no tenant or role boundary. Customer inputs are validated and fillable fields are limited; no SQL injection, unsafe upload, SSRF, command injection, or unescaped customer output was identified.

## Bugs / Correctness

COR-001 is resolved by rejecting the restore before mutation when an active duplicate exists, handling a concurrent unique violation, and covering the failure state with a feature test.

## Database

The active-name constraint, soft deletes and force-delete path are present. DB-001 now converts duplicate-key races into validation errors. DB-002 now refuses rollback before changing the schema when duplicate names exist; a deployment must still treat that rollback as unavailable for such data. No foreign-key or N+1 defect was confirmed.

## Performance

Lists are paginated at five rows and no application-level N+1 was found. Leading-wildcard search and unindexed trash ordering may become expensive at scale, but no workload or query plan was available; they are rejected as confirmed findings.

## Architecture

The customer flow is separated into controllers, Form Requests, policy, query objects and transformers in line with the architecture document. No disproportionate abstraction or boundary violation was accepted.

## Testing

The isolated PHPUnit suite passed 74 tests and 221 assertions. New coverage verifies unverified users are redirected, active-name restore conflicts preserve database state, and password reset persists the new password while rejecting the old one. Dusk now waits for `/up` before launching, but remains unexecuted locally.

## Production

The container reports local mode with debug enabled, which is expected for the development image. Production configuration was not exercised. Compose credentials are treated as local-development credentials, not as a confirmed production secret leak; production must provide isolated secrets and non-debug settings.

## Maintainability

PHPStan and Pint pass. The restore policy is documented as technical debt in the architecture notes but remains incomplete in the implementation. No broad refactor or new architectural layer is justified.

## Rejected Findings

- CustomerPolicy authorization: rejected because a narrower scope would invent roles or tenancy not present in the product requirements.
- Leading-wildcard search and missing `deleted_at` index: rejected as confirmed performance defects without representative data or `EXPLAIN` evidence.
- Accessibility concerns for icon controls: rejected after review of the current Flux/Blade markup; no concrete blocking issue was established.
- SQL Server generated-column portability: rejected for this application because the documented production database is MariaDB and no SQL Server deployment exists.
- Fixed Docker database credentials as a production vulnerability: rejected because the Compose file is documented as local development infrastructure.
- Observability, APM and correlation IDs: recorded as optional production improvements, not defects for this small single-application scope.

## Action Plan

- P0: None.
- P1: RESOLVED — enforce and test the verified-user boundary; implement controlled restore conflict handling.
- P2: PARTIAL — duplicate-key races, unsafe rollback and Compose database readiness are addressed. Preserve lockfiles and use `npm ci` remains open in the current Compose file.
- P3: RESOLVED — bind MailHog to loopback, add verification/restore/password-reset coverage, and make Dusk wait for application readiness. Remaining: confirm the Dusk CI job.

