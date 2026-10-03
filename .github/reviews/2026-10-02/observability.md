# Observability Specialist Review — 2026-10-02

Snapshot: HEAD `781ccbb` + working tree.

Scope note: this application declares no production deployment target anywhere in the repository.
`ARCHITECTURE.md:127-133` says only "Production deployments must provide a non-debug environment and isolated
database credentials". There is no Kubernetes/Helm/Fly/Railway/Forge config, no `Procfile`, no systemd unit, no
reverse-proxy config. Everything below is therefore assessed against "an internal single-tenant CRUD tool", and
pattern-driven demands for an observability stack are explicitly rejected.

## OBS-001 — No frontend error reporting: JS failures in the list fragment are silently swallowed

Severity: LOW
Category: Observability / Error visibility
File: resources/js/app.js
Line: 478-481
Confidence: HIGH

Problem:

```js
} catch (error) {
    if (!controller.signal.aborted) {
        throw error;
    }
}
```

Inside `refreshList`, a real failure (non-2xx, or a response without `[data-list-results]`) is re-thrown from an
`async` method whose callers never `await` or `.catch()`:

```js
// app.js:393 / :412
this.search(query);          // returns a promise nobody awaits
```

The result is an **unhandled promise rejection**: it appears in the browser console and nowhere else. The user sees
the list simply stop updating — no message, no retry, no indication that anything failed.

Evidence:

- `resources/js/app.js:446-487` — `refreshList`, with the `catch` at 478-481.
- `resources/js/app.js:393,412,420,429` — four call sites, none of which handle the returned promise.
- Contrast with the rest of the file, which *does* surface errors to the user:
  `initializeProjectCustomerSelect` sets `projectForm.form.customerCreateError` (line 344) and the epic form has
  `data-test="epic-comments-error"` with `x-show="form.commentsError"` (`resources/views/epics/form.blade.php:89-90`).
  So the codebase already knows how to do this — the list refresh is the outlier.

Impact:

A failing list refresh is undiagnosable for a non-developer user. There is a Dusk test
(`tests/Browser/Customers/CustomerCrudTest::test_customer_list_can_be_searched_and_cleared`) so the happy path is
covered, but no test asserts that a *failing* refresh tells the user something.

Recommendation:

Surface it the same way the comment loader does. The smallest change is to set a message on the existing
`x-list.flash` region rather than re-throwing. Roughly:

```js
} catch (error) {
    if (controller.signal.aborted) { return; }
    this.$root.dispatchEvent(new CustomEvent('list-error', { detail: { message: error.message } }));
}
```

…plus a listener that renders it. Keep it minimal — this is a five-line change, not an error-reporting framework.

## OBS-002 — No request correlation, so a report from a user cannot be traced

Severity: LOW
Category: Observability / Diagnostics
File: config/logging.php
Line: 21
Confidence: HIGH

Problem:

`config/logging.php` uses the framework default stack with no request id, no user id, and no structured context:

```php
'default' => env('LOG_CHANNEL', 'stack'),
```

with `.env:9-10` selecting `LOG_CHANNEL=daily`, `LOG_STACK=single`. There is no `Log::withContext()`, no
`RequestId` middleware, and no monolog processor that adds one.

Evidence:

- `config/logging.php:21` and the channels block at line 53+ (`stack`, `single`, `daily`, `stderr`, `syslog`,
  `errorlog` are the stock skeleton channels; none adds context processors).
- Verified by grep: no `withContext`, no `RequestId`, no `->shareContext` anywhere in `app/` or `config/`.

Impact:

For a 5-user internal tool this is a genuine convenience loss but not an operational risk: when a user reports
"it failed", there is no way to find the matching request in the log without a timestamp and a rough URL. Laravel's
exception handler does log the authenticated user id with query parameters by default for 5xx, which covers the
worst case.

Recommendation:

If/when this app gets a real deployment, add a request id. Do **not** build it now: the project has no deployment
target and `AGENTS.md` says to create docs/code only when asked. Recorded as P3.

## OBS-003 — Database-backed cache, session and queue, with no purge/flush path

Severity: INFO
Category: Observability / Operability
File: .env
Line: 30-32
Confidence: HIGH

Problem:

None operationally today, but worth recording: the app uses the database for all three stateful subsystems.

```dotenv
SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
```

(`config/database.php`, `config/cache.php:18`, `config/queue.php:16` all default to the same.)

Evidence:

- `.env:30,32,31` — verified above.
- The three tables exist and are migrated: `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`
  (all present in the live schema dump and created by `0001_01_01_000000_create_users_table.php`,
  `0001_01_01_000001_create_cache_table.php`, `0001_01_01_000002_create_jobs_table.php`).
- **No job is ever dispatched** — grep confirms no `dispatch(`, no `ShouldQueue`, no `Queue::` in `app/`.
  `ARCHITECTURE.md:77-81` states this explicitly. So `jobs`/`failed_jobs` stay empty.

Impact:

Two consequences:

1. `failed_jobs` is the natural place an operator would look for async failures. Because no jobs exist, an empty
   `failed_jobs` table proves nothing — it is not a health signal.
2. `cache` and `cache_locks` are also unused: grep confirms **zero** `Cache::` calls in `app/`. So the `cache` table
   grows with nothing and can be ignored.

Recommendation:

None. But if the app ever adds a job or a cache write, note that `failed_jobs` growth is the first thing to alert on,
and that `php artisan queue:failed` becomes meaningful at that moment. Recorded so nobody builds a dashboard on
these tables today.

## OBS-004 — Health endpoint exists and is used as the CI readiness probe

Severity: INFO (positive finding)
Category: Observability
File: bootstrap/app.php
Line: 14
Confidence: HIGH

Problem:

None.

Evidence:

- `bootstrap/app.php:14` — `health: '/up'`.
- `.github/workflows/tests.yml:222-229` — `curl --fail --silent http://127.0.0.1:8000/up` in a 30-attempt loop before
  running Dusk. That is a correct readiness pattern.

Impact:

None.

Recommendation:

None.

## OBS-005 — Destructive DB commands are blocked in production by default

Severity: INFO (positive finding)
Category: Observability / Safety
File: app/Providers/AppServiceProvider.php
Line: 36-38
Confidence: HIGH

Problem:

None.

Evidence:

```php
DB::prohibitDestructiveCommands(
    app()->isProduction(),
);
```

This blocks `migrate:fresh`, `migrate:refresh` and `db:wipe` when `APP_ENV=production`. Note that `Date::use(CarbonImmutable::class)`
and the production `Password::defaults()` policy (min 12, mixedCase, letters, numbers, symbols, uncompromised) are
configured in the same method, lines 34 and 40-48 — so the password policy tightens automatically in production
without a code change.

Impact:

None. This is the correct production posture and it is enabled explicitly rather than by accident.

Recommendation:

None.

## Notes / not findings (rejected after devil's-advocate challenge)

- **No Sentry / Bugsnag / OpenTelemetry.** Recommending an error-tracking service for an internal CRUD app with no
  declared deployment target is exactly the pattern-driven recommendation `.github/docs/review-rules.md:29-42`
  prohibits. **Rejected.** OBS-002 records the only concrete gap (no request correlation), which is solvable with
  framework primitives if ever needed.
- **No structured JSON logging.** Same reasoning; there is no log consumer to emit JSON for. **Rejected.**
- **No uptime monitoring / alerting.** Nothing to alert on. **Rejected.**
- **No health checks for the database or cache.** `/up` is the framework's default liveness endpoint and does not
  probe dependencies. Adding DB-backed checks would be reasonable for a deployed service, but there is no deployed
  service. **Rejected.**
- **Flash messages for user feedback are present and consistent** — `x-list.flash` renders `session('status')` and
  `session('error')` (`resources/views/components/list/flash.blade.php:3-12`), and every controller sets exactly one of
  them via `->with('status', ...)` / `->with('error', ...)`. That is the app's user-facing diagnostic channel and it
  works. **Rejected as a finding**; noted so the absence in OBS-001 is understood as an inconsistency, not a
  project-wide gap.