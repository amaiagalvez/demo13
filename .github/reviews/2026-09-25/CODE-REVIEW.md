# Code Review

Audit date: 2026-09-25
Mode: READ-ONLY

## Executive Summary

The Laravel customer CRUD and trash flows are covered by passing isolated tests, use authenticated and verified routes, and show no confirmed security vulnerability in the reviewed surface. PHPStan and Composer audit pass. The configured Pint check still reports six style issues; Dusk is now configured as a separate critical CI job and requires one GitHub Actions run for confirmation.

## Detected Stack

- PHP 8.4.25 in Docker; Composer 2.10.3.
- Laravel 13.33.0, Fortify 1.40.0, Livewire 4.4.6, Flux 2.20.0.
- MariaDB 11.7/MySQL-compatible database; database queue, database cache and database sessions.
- Blade, Livewire, Flux, Vite Plus and Tailwind.
- PHPUnit 12.5.35, Larastan 3.12.2, Pint 1.32.1 and Dusk 8.7.0.
- Docker Compose and GitHub Actions CI. No API routes, Redis service, Horizon, or application jobs were found.

## Checks Executed

| Check | Result |
| --- | --- |
| `php artisan about` in active Docker service | PASS; Laravel 13.33.0, PHP 8.4.25, MySQL/database drivers confirmed |
| `php artisan route:list --except-vendor` | PASS; 11 routes listed, customer routes behind `auth` and `verified` |
| `vendor/bin/phpstan analyse --no-progress` | PASS; no errors |
| `composer audit --no-interaction --format=plain` | PASS; no security advisories |
| `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --compact` | PASS; 71 tests, 198 assertions |
| `vendor/bin/pint --test` | FAIL; 72 files checked, 6 style issues |
| `npm run build` on host | NOT AVAILABLE; Node 18 lacks the `node:util` `styleText` export required by the installed Vite Plus runtime |
| Dusk browser suite | CONFIGURED in a separate CI job; not run locally because no isolated browser session was available |

The PHP checks were run in the already active Laravel container because PHP and Composer are not installed on the host. The application test suite used SQLite in memory to avoid modifying persistent MySQL data.

## Critical Findings

None.

## High Findings

None.

## Medium Findings

None.

## Low Findings

### QLT-001 — Configured Pint gate fails

Severity: LOW
Category: Maintainability
File: `.github/workflows/tests.yml`
Line: 60
Confidence: HIGH

Problem:

The project defines formatting validation as part of its Composer checks, but the current code does not pass Pint.

Evidence:

`vendor/bin/pint --test` reported 6 style issues across 72 files, including `app/Transformers/CustomerListTransformer.php`, the customer unique-index migration, `routes/web.php`, and three customer test files. The CI workflow invokes `composer ci:check` at `.github/workflows/tests.yml:60`, which invokes the test script and its `lint:check` step.

Impact:

The configured quality gate cannot pass until the formatting issues are resolved, so CI can reject otherwise behaviorally valid changes.

Recommendation:

Run the repository's formatter in a separate change and rerun `vendor/bin/pint --test`. Do not mix formatting with behavioral changes.

## Informational Findings

### OPS-001 — Development compose exposes local infrastructure ports

Severity: INFO
Category: DevOps
File: `docker-compose.yml`
Line: 209
Confidence: HIGH

Problem:

The local Docker Compose file publishes MariaDB on port 13306 and phpMyAdmin on port 4000, with fixed development credentials in the compose file.

Evidence:

`docker-compose.yml:198-201` contains the MariaDB development credentials; ports are published at `docker-compose.yml:209` and `docker-compose.yml:214`.

Impact:

On a shared or exposed host, other users could reach development infrastructure. The file is clearly a local-development compose setup, so this is not evidence of a production vulnerability.

Recommendation:

Keep this compose file restricted to local development, or bind administrative ports to loopback and source credentials from environment variables when the stack is used beyond a developer workstation.

## Security

No confirmed security vulnerability was found. Customer routes are protected by `auth` and `verified`; Form Requests authorize create/update and controllers authorize delete/restore/force-delete. No unsafe raw query, command execution, file upload, SSRF sink, or unescaped customer rendering was identified. `APP_DEBUG=true` is present only in the local `.env.example` template and was not treated as a production configuration.

## Bugs / Correctness

No confirmed runtime bug was found in the exercised customer flow. The isolated test suite passed all 71 tests and 198 assertions.

## Database

Customer persistence has a database uniqueness constraint for active records, soft deletes, and explicit restore/force-delete operations. Deleted names can be reused, and the behavior is covered by a Feature test.

## Performance

No confirmed performance issue was found. Customer lists are paginated at five records per page and search input is bounded by validation.

## Architecture

The customer flow has localized controllers, Form Requests, policy, query object and transformer. No unnecessary new abstraction or disproportionate architecture issue was identified. The architecture document remains a template, which is documentation debt only.

## Testing

Unit and Feature tests pass in an isolated in-memory SQLite run. Dusk is configured in a separate CI job and should be confirmed on the next GitHub Actions run.

## Production

The application was observed in `local` mode with debug enabled inside the development container. Production deployment settings were not exercised. The Docker port exposure is treated as local-development hardening guidance, not a production finding.

## Maintainability

Pint reports six style issues; see QLT-001. No broad refactor is justified by the reviewed code.

## Rejected Findings

- `CustomerPolicy` methods currently allow every authenticated user. No roles, tenants, or narrower authorization requirement exists in the repository, so restricting it would be an invented business rule.
- `.env.example` uses `APP_DEBUG=true`. It is explicitly a local template and is overridden per deployment; no production leak was demonstrated.

## Action Plan

- P0: None.
- P1: None.
- P2: Resolve the six Pint findings and restore the configured formatting gate.
- P2: Confirm the new Dusk job succeeds on GitHub Actions.
- P3: Document whether soft-deleted customer names are intentionally reserved and keep administrative Docker ports local-only.

