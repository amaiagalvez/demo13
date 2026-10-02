# Observability Review — 2026-10-02

## Findings

### OBS-001 — No application-level telemetry; failures surface only through the daily log

Severity: LOW
Category: Observability
File: config/logging.php, .env.example, phpunit.xml
Line: — / 20-21 / 17-19
Confidence: HIGH

Problem: there is no error tracker, no APM, no metrics endpoint and no structured request logging.
The only signal is `LOG_CHANNEL=daily` writing to `storage/logs/laravel-YYYY-MM-DD.log`.

Evidence: `phpunit.xml:17-19` explicitly disables the optional observability packages —
`PULSE_ENABLED=false`, `TELESCOPE_ENABLED=false`, `NIGHTWATCH_ENABLED=false` — and `boost.json`
sets `"nightwatch": false`.

Impact: proportionate for a demo application, but it means a production misconfiguration (a failed
mail send, a 500 on a list page) is only discoverable by reading a log file. Combined with
`AppServiceProvider::configureDefaults()` raising `Password::min(12)` in production, a real
deployment would be operating without visibility into its own failures.

Recommendation: **no change now.** This is the correct proportionality call for the current scope.
Record the trigger: the day a production environment is provisioned, add at minimum Sentry (or the
equivalent) and Laravel Pulse. `ARCHITECTURE.md:127-134` already states that production must provide
a non-debug environment — telemetry belongs in the same list.

### OBS-002 — Health endpoint is unauthenticated and unversioned

Severity: INFO
Category: Observability
File: bootstrap/app.php
Line: 14
Confidence: HIGH

Observation: `health: '/up'` is registered outside the `auth` group, as expected. It returns a
plain 200 with no dependency checks (no DB ping), so it proves only that PHP is serving.

Impact: acceptable for a load-balancer liveness probe. Recorded so nobody later assumes it verifies
database connectivity.

## Verified clean (no findings)

- **Errors are not swallowed.** No `try { … } catch (Throwable) {}` anywhere in `app/`. The only
  `catch` blocks are the three `QueryException` handlers, all of which rethrow anything that is not
  a uniqueness violation.
- **No sensitive data in log calls.** `grep -rn "Log::\|logger(" app/` returns nothing; there is no
  custom logging at all, so there is no credential or PII leakage path.
- **Health route is not registered twice** and does not collide with an application route.
- **`storage/logs` is not tracked** by git; `.gitignore` covers `/storage/pail` and
  `/storage/*.key`.
- **`APP_DEBUG=false` in `.env.example`** — the safe default, and the opposite of the usual
  Laravel boilerplate. `APP_ENV=local` means debug is off even in local development, which will
  make first-run errors less informative for a new contributor; mentioned only as a heads-up.