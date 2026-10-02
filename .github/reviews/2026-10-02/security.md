# Security Review — 2026-10-02

READ-ONLY OWASP-oriented review. No file was modified.

## Findings

### SEC-001 — The development application is published on all interfaces while every other admin port is loopback-bound

Severity: MEDIUM
Category: Security / DevOps exposure
File: docker-compose.yml
Line: 18-20 (service `laravel13`, `ports:`)
Confidence: HIGH (fact) / MEDIUM (impact)

Problem: `laravel13` publishes the HTTP port and the Vite port on `0.0.0.0`. The database,
phpMyAdmin and MailHog are all explicitly bound to `127.0.0.1`. The application is therefore the
only reachable service on a shared network, and it carries development credentials
(`.env.example: DB_USERNAME=laravel`, `DB_PASSWORD=laravel`) and `BCRYPT_ROUNDS=12` + seeded
`test@example.com` user via `database/seeders/DatabaseSeeder.php:37-40`.

Evidence:

```yaml
# docker-compose.yml:17-20
ports:
    - "80:80"
    - "${VITE_PORT:-5173}:${VITE_PORT:-5173}"
```

```console
$ docker compose ps
demo13-db-1             ... 127.0.0.1:13306->3306/tcp
demo13-laravel13-1      ... 0.0.0.0:80->80/tcp, 0.0.0.0:5173->5173/tcp
demo13-laravel13-myadmin-1 ... 127.0.0.1:4000->80/tcp
demo13-mailhog-1        ... 127.0.0.1:8025->1025/tcp
```

Impact: any host on the same network can reach the dev app and attempt authentication; the
hardcoded MariaDB root password `MYSQL_ROOT_PASSWORD: tormenta` (`docker-compose.yml`, service `db`)
is committed to the repository.

Recommendation: bind the app to loopback like its siblings — `"127.0.0.0.1:80:80"` — and read the
DB root password from an env var with a local-only default. This is development-only tooling and
`.github/docs/architecture/ARCHITECTURE.md:130-131` already states administrative ports are local
only, so this aligns the config with the documented intent.

## Checked and confirmed NOT exploitable

| Area | Evidence |
|---|---|
| XSS via unescaped Blade | Only **one** `{!! !!}` in the whole view tree: `resources/views/pages/settings/⚡two-factor-setup-modal.blade.php:225` (`{!! $qrCodeSvg !! !!}`), a server-generated Fortify SVG. Every user value uses `{{ }}` (escaped) or `@js()`. |
| XSS via search fragment injection | `resources/js/app.js:462-477` does `template.innerHTML = await response.text()` then `Alpine.morph`. The fragment is same-origin server-rendered Blade with escaped output, so no injection point exists. |
| XSS via `data-payload` | `resources/views/components/list/row-actions.blade.php:16,27` — `{{ json_encode(...) }}` is escaped by `e()` (`ENT_QUOTES`, double-encode), so the DOM decodes back to valid JSON before `JSON.parse`. |
| XSS via Alpine expressions | `updateUrl: @js(route(...))` (`customers/list.blade.php:34`, `epics/list.blade.php:51`) and `@js($commentedEpic)` (`epics/list.blade.php:95`) are `Js::from()` encoded, which escapes `'` and `"`. |
| Mass assignment of `active` | `#[Fillable]` on `Customer` is `['name']` only; `active` is written server-side. `CreateNewUser::create()` (`app/Actions/Fortify/CreateNewUser.php:27-31`) whitelists name/email/password. `InactiveUserTest::test_new_users_are_active_by_default_without_accepting_registration_input` posts `active => false` and asserts the user is still active. |
| Mass assignment of `customer_id`/`project_id` | Validated with `Rule::exists(...)->whereNull('deleted_at')` in `ProjectRequest:47-51` / `EpicRequest:48-52`. |
| SQL injection | All queries go through Eloquent/Query Builder with bound parameters. The only raw SQL is `orderByRaw('epics.start_date IS NULL')` (a constant) and the migration's `storedAs('IF(deleted_at IS NULL, name, NULL)')`. `ListQueryBase::paginate()` escapes `% _ \` for `LIKE` (`app/Queries/ListQueryBase.php:27`). |
| IDOR / BOLA | Documented product decision: every authenticated verified user manages every resource (`ARCHITECTURE.md:41-44`, all three policies `return true`). No route escapes that boundary — trash routes re-fetch through `onlyTrashed()`, restore routes go through `*RestoreRequest::authorize()`. |
| CSRF | All routes live in the `web` group (`routes/web.php:17`). No `api` routes exist. |
| Session/cookie hardening | `config/session.php:172` `'secure' => env('SESSION_SECURE_COOKIE', env('APP_ENV') === 'production')`, `http_only => true`, `same_site => 'lax'`. Defaults are correct for production; `.env.example:29-30` documents the HTTPS override. |
| Rate limiting | Fortify defines `login` (5/min per email+IP), `two-factor` (5/min per `login.id`) and `passkeys` (10/min per credential+IP) — `app/Providers/FortifyServiceProvider.php:117-143`. |
| Inactive-user lockout | Password, 2FA, passkey and remember-me paths are all covered by `FortifyServiceProvider::configureActiveUsers()` plus `EnsureUserIsActive`, and asserted by 9 tests in `tests/Feature/Auth/InactiveUserTest.php`. |
| Secrets in the repository | `.env` is gitignored; only `.env.example` (empty `APP_KEY`) is tracked. Verified with `git ls-files`. |
| File upload / path traversal / SSRF / command injection | None. No upload endpoints, no `Storage::` writes from user input, no `exec`/`shell_exec`/`Http::` to user-controlled URLs. |

## Informational

- No rate limiting on the authenticated CRUD write routes. Acceptable for a first-party
  authenticated app; noted only so it is a deliberate choice.
- `resources/views/layouts/app/sidebar.blade.php:42,47` use `target="_blank"` without
  `rel="noopener noreferrer"`. Modern browsers imply `noopener`, so this is not exploitable.