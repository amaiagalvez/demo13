# API Review — 2026-10-02

There is **no HTTP API** in this application. `routes/web.php` and `routes/settings.php` are the
only route files, there are no controllers under `app/Http/Controllers/Api`, no `routes/api.php`
and no `php artisan install:api` artefacts. This review therefore covers the single JSON surface
that does exist.

## The only JSON surface

`CustomerController::store()` returns `RedirectResponse|JsonResponse` because the project form's
select2 creates customers inline.

Consumer: `resources/js/app.js:318-339`.

| Case | Status | Body | Correct? |
|---|---|---|---|
| Created | 201 | `{"id": 1, "name": "..."}` | Yes — the JS only reads `result.id` / `result.name`. |
| Validation failure | 422 (Laravel default) | `{"message": "...", "errors": {"name": ["..."]}}` | Yes — `app.js:331` reads `result.errors?.name?.[0]`. |
| Record Name already in the trash | 409 | `{"message": "...", "errors": {"name": ["..."]}}` | Yes — `app.js:330-332` treats any non-2xx as an error. |
| Unauthorized | 403 | Laravel default | Acceptable — the JS surfaces the message. |
| Trashed-name collision race | 422 (`name`) | via `UniqueConstraintViolation::rethrowAsValidationError` | Yes — consistent with the 409/422 mix above. |

## Findings

### API-001 — No write endpoint is rate limited

Severity: INFO
Category: API / Abuse prevention
File: .github/workflows/../routes/web.php (all routes), app/Providers/FortifyServiceProvider.php
Line: 17-65 (auth-only limiters)
Confidence: HIGH

Observation: the only rate limiters defined are Fortify's `login`, `two-factor` and `passkeys`
(`FortifyServiceProvider:117-143`). No route uses the `throttle` middleware, so the CRUD write
endpoints have no throttle at all.

Impact: acceptable for a first-party authenticated application behind `auth` + `verified`.
Recorded so the omission is a decision, not an oversight.

### API-002 — The `api/*` branch of `shouldRenderJsonWhen` is unreachable

Severity: INFO
Category: Laravel / Dead configuration
File: bootstrap/app.php
Line: 24-26
Confidence: HIGH

Problem:

```php
$exceptions->shouldRenderJsonWhen(
    fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
);
```

No `api/*` route exists, so `$request->is('api/*')` can never be true. The `||` clause is dead but
harmless and future-proof. No change recommended; listed only so a reviewer does not "simplify" it
away before an API is added.

## Verified clean (no findings)

- Content negotiation is consistent: the JSON branch is gated on `expectsJson()`, and the client
  sends `Accept: application/json` (`app.js:319`) and `X-Requested-With: XMLHttpRequest`.
- The 409 body duplicates `message` inside `errors.name`, which is redundant but harmless and is
  what the JS expects.
- No API resources, no `JsonResource` subclasses, no pagination envelope — nothing to review there.
- CSRF protection is correct for the JSON call: `app.js:323` sends `X-CSRF-TOKEN` from the form's
  `@csrf` token, and the endpoint is in the `web` group.