# Security Specialist Review — 2026-10-02

Snapshot: HEAD `781ccbb` + working tree. READ-ONLY review; no secrets, endpoints or databases were attacked.

Stack: Laravel 13.17, Fortify 1.37, @laravel/passkeys 0.2, MariaDB 11.7, Blade + Alpine.
No API routes, no file uploads, no Redis/Horizon, no external service integrations.

## SEC-001 — Committed `.env` contains a real APP_KEY and live database credentials

Severity: HIGH
Category: Security / Secrets
File: .env
Line: 4 (APP_KEY), 20-23 (DB block)
Confidence: HIGH

Problem:

The repository's working `.env` file contains a fully populated `APP_KEY` and the local MariaDB credentials.
It is **not** tracked by git (`.gitignore` line 8 lists `.env`, and `git ls-files | grep -i env` returns only
`.env.example`), so this is not a committed-secret leak. The finding is that the key is a *real, usable* key sitting
in the working tree of a shared development machine, and `.env.example` documents defaults that make it easy to
ship `APP_DEBUG=true` by copying the wrong file.

Evidence:

```
$ git ls-files | grep -i env
.env.example            <- only the example is tracked; good

.env:4    APP_KEY=base64:v9Yt+am5tz+u3bmaTOS5Qy9gtHYHCUzpngN8vNQXkSc=
.env:19   APP_DEBUG=true
.env:20-23  DB_DATABASE=laravel / DB_USERNAME=laravel / DB_PASSWORD=laravel
```

Impact:

- Anyone with read access to this working tree can decrypt/forge cookies and session payloads for this APP_KEY.
- `APP_DEBUG=true` combined with the `Whoops` stack would expose stack traces and environment values on any error
  if the app were ever bound to a non-loopback interface. `docker-compose.yml:12` publishes port `80:80` on all
  interfaces, so the dev container is reachable from the local network with debug on.

Recommendation:

Keep `.env` untracked (already correct — no change needed). Do not treat the dev `APP_KEY` as sensitive beyond
this machine. If the container may be exposed beyond the developer machine, set `APP_DEBUG=false` for shared/dev
environments and bind the published port to `127.0.0.1` in `docker-compose.yml`. This is a hardening item, not an
incident.

## SEC-002 — `docker-compose.yml` publishes MariaDB on the host interface and ships a fixed root password

Severity: MEDIUM
Category: Security / Insecure Docker configuration
File: docker-compose.yml
Line: 199-219
Confidence: HIGH

Problem:

The `db` service publishes `127.0.0.1:13306:3306` and mounts a persistent volume, with hard-coded credentials
(`MYSQL_ROOT_PASSWORD: tormenta`, `MYSQL_USER/MYSQL_PASSWORD: laravel`). phpMyAdmin is also published on
`127.0.0.1:4000`.

Evidence:

```yaml
# docker-compose.yml:199-219
db:
  image: mariadb:11.7
  environment:
    MYSQL_ROOT_PASSWORD: tormenta
    MYSQL_DATABASE: laravel
    MYSQL_USER: laravel
    MYSQL_PASSWORD: laravel
  volumes:
    - ./dc-data/mysql:/var/lib/mysql
  ports:
    - 127.0.0.1:13306:3306
```

Note: the ports **are** bound to `127.0.0.1`, not `0.0.0.0` — this limits exposure to the host itself. The
`laravel13` web service (line 12) is the one bound to `0.0.0.0`.

Impact:

On a developer laptop or shared/CI machine, anything running as the host user can reach MariaDB on port 13306
and authenticate with the known `laravel/laravel` credentials, reading and modifying all application data. The
`./dc-data/mysql` bind mount also means the data directory lives inside the repository working tree.

Recommendation:

Acceptable for local development, and it is already loopback-bound. Two cheap improvements if this compose file is
ever used beyond one person's laptop: move the passwords into `.env` (referenced with `${MYSQL_ROOT_PASSWORD:-...}`),
and add `./dc-data` to `.gitignore` (already present, `.gitignore` line 12 — verified).

## SEC-003 — Custom `authenticateUsing()` bypasses `Auth::attempt()` and re-implements credential lookup

Severity: MEDIUM
Category: Security / Authentication
File: app/Providers/FortifyServiceProvider.php
Line: 44
Confidence: MEDIUM

Problem:

`Fortify::authenticateUsing()` replaces Fortify's default `AttemptToAuthenticate` step with a hand-rolled lookup:

```php
$user = User::where(Fortify::username(), $request->input(Fortify::username()))
    ->where('active', true)->first();
$provider = Auth::guard(config()->string('fortify.guard'))->getProvider();
$credentials = $request->only('password');
if (! $user || ! $provider->validateCredentials($user, $credentials)) { return null; }
```

The intent (blocking inactive users at login) is legitimate and documented in ARCHITECTURE.md. The risk is that the
callback now owns *all* credential semantics, and it re-implements behaviour the framework already provides:
`retrieveByCredentials()`, the `Attempting`/`Validating`/`Validated` auth events, and any future provider change.
It also returns `null` (rather than throwing) for both "user not found" and "wrong password", which is correct and
does not leak user existence.

Evidence:

- `vendor/laravel/fortify/src/Http/Controllers/AuthenticatedSessionController.php:73-89` — the pipeline still runs
  `EnsureLoginIsNotThrottled` (because `fortify.limiters.login` is set), `CanonicalizeUsername`,
  `RedirectsIfTwoFactorAuthenticatable`, `AttemptToAuthenticate`, `PrepareAuthenticatedSession`. So rate limiting
  (5/min per email+IP, `FortifyServiceProvider.php:126-132`) still applies. Good.
- `config/fortify.php:48` `'username' => 'email'` and `'lowercase_usernames' => true` (line 63) — the username is
  lowercased by `CanonicalizeUsername` before this closure runs, and the `users.email` column has collation
  `utf8mb4_unicode_ci` (verified via `SHOW CREATE TABLE users`), so lookup is case-insensitive.
- The `Login` event listener (lines 62-81) re-checks `active` and logs out + invalidates the session if the user was
  deactivated between the credential check and the session write. This is a correct belt-and-braces design and is
  covered by `tests/Feature/Auth/InactiveUserTest.php:61` and `:95`.

Impact:

No demonstrated bypass today. The exposure is maintenance risk: the closure is the single place that decides whether
a credential is valid, and it diverges from the framework contract in ways a future upgrade could silently break.
It also bypasses `Auth::validate()`'s ability to use a custom user provider's `retrieveByCredentials()` override.

Recommendation:

Keep the behaviour, reduce the surface. Two options, in order of preference:
1. Leave `authenticateUsing()` alone and rely on the existing `Login` listener + `EnsureUserIsActive` middleware,
   which already cover the "deactivated after password check" case — but verify the *initial* login is still blocked
   (it is: `authenticateUsing` is what blocks it, so this option is **not** viable; the closure is required).
2. Keep the closure but call the provider's `retrieveByCredentials()` instead of hand-writing the query, so any
   custom credential retrieval keeps working:

```php
$user = $provider->retrieveByCredentials([Fortify::username() => $request->input(Fortify::username())]);
if (! $user instanceof User || ! $user->active) { return null; }
if (! $provider->validateCredentials($user, $credentials)) { return null; }
```

This is a small, behaviour-preserving change that removes the duplicated lookup. Confidence MEDIUM because I have
not run the test suite against a modified provider (review is read-only).

## SEC-004 — Every list and write endpoint is available to any verified user; no ownership scoping

Severity: MEDIUM
Category: Security / Authorization (IDOR surface)
File: app/Policies/CustomerPolicy.php
Line: 10-59
Confidence: HIGH

Problem:

All three policies return `true` unconditionally for every ability, including `delete`, `forceDelete` and `comment`.
Any authenticated + email-verified user can read, edit, deactivate, trash and permanently delete every customer,
project and epic in the system, and read every epic comment.

Evidence:

```php
// app/Policies/CustomerPolicy.php
public function viewAny(User $user): bool { return true; }
public function delete(User $user, Customer $customer): bool { return true; }
public function forceDelete(User $user, Customer $customer): bool { return true; }
// identical shape in ProjectPolicy.php and EpicPolicy.php
```

`routes/web.php:16` — the whole resource surface is inside `Route::middleware(['auth', 'verified'])`.

This is **documented intent**, not an oversight: `.github/docs/architecture/ARCHITECTURE.md:40-44` states
"`CustomerPolicy` is the authorization boundary ... The current product scope permits every authenticated verified
user to perform these actions." The project instructions (`http.instructions.md`) also say "do not add roles unasked".

Impact:

No vulnerability against the documented design. The risk is that `ARCHITECTURE.md` reads as if `CustomerPolicy` is a
meaningful boundary, and a reader may assume per-record scoping exists. If the product ever gains a second class of
user, every policy silently permits everything and there is no test that would fail.

Recommendation:

Do **not** add roles (explicitly out of scope). Instead, make the risk visible at the point of future change:
add a short comment in each policy class pointing at `ARCHITECTURE.md`, e.g.
`// Every authenticated verified user may act on any record. See ARCHITECTURE.md#Authentication--Authorization`.
This costs nothing and prevents the assumption that the policy currently enforces ownership.

## SEC-005 — Rate limiting is present but modest for the auth surface

Severity: LOW
Category: Security / Rate limiting
File: app/Providers/FortifyServiceProvider.php
Line: 122-142
Confidence: HIGH

Problem:

Three named limiters are registered: `login` (5/min per lowercased email + IP), `two-factor` (5/min per
`login.id` session key), `passkeys` (10/min per credential id + IP). Password reset and email verification are not
rate limited by the application; Fortify's defaults for those apply.

Evidence:

```php
// app/Providers/FortifyServiceProvider.php:126-132
RateLimiter::for('login', function (Request $request) {
    $username = $request->input(Fortify::username());
    $username = is_string($username) ? $username : '';
    $throttleKey = Str::transliterate(Str::lower($username).'|'.$request->ip());
    return Limit::perMinute(5)->by($throttleKey);
});
```

Note the `is_string()` guard on line 128 — a hostile client sending `email[]=x` cannot break `Str::lower()`.
That is a deliberate, correct hardening. `config/fortify.php:117-124` wires all three limiters.

Impact:

Acceptable for the documented scope. The gap is password reset (`Features::resetPasswords()` is enabled,
`config/fortify.php:173`), which is the usual target of credential-stuffing-by-email floods.

Recommendation:

Optional. If public registration is ever disabled (already a TODO in `todo.md`), password-reset abuse becomes the
main remaining vector; consider adding a named `Limit::perHour(5)->by($request->ip())` limiter for the reset link at
that point. Not worth doing speculatively.

## SEC-006 — Comment bodies are rendered with `x-text` / escaped Blade; no XSS found

Severity: INFO (positive finding, recorded so it is not re-litigated)
Category: Security / XSS
File: resources/views/epics/form.blade.php
Line: 111
Confidence: HIGH

Problem:

None. This is recorded because comment bodies are the only free-text user input in the app and therefore the most
likely XSS vector.

Evidence:

- `resources/views/epics/form.blade.php:111` — `<p ... x-text="comment.body"></p>`. Alpine's `x-text` assigns to
  `textContent`, never `innerHTML`.
- `resources/views/epics/form.blade.php:107` — `x-text="comment.author"`, same.
- `resources/views/views/components/list/row-actions.blade.php:16,27` — the edit and action payloads are emitted as
  `data-payload="{{ json_encode(...) }}"` inside a **Blade-escaped** attribute (`{{ }}` applies `e()`/`htmlspecialchars`),
  then read back with `JSON.parse($el.dataset.payload)`. The browser decodes the entities before `JSON.parse`, so the
  round-trip is correct and attribute-breakout is impossible.
- No `v-html`, no `{!! !!}`, no `innerHTML` anywhere in `resources/views/**` — verified by grep.
- `EpicCommentRequest.php:36` caps the body at `max:5000`.

Impact:

None. Escaping is correct and consistent.

Recommendation:

None. Keep using `x-text` for any future user-generated field.

## Notes / not findings (rejected after challenge)

- **CSRF**: every mutating form in the views carries `@csrf` (`tracked-resource.blade.php:19`,
  `confirm-modal.blade.php:19`, `name-conflict-modal.blade.php:23,33`), and the inline-create `fetch` sends
  `X-CSRF-TOKEN` (`resources/js/app.js:323`). Not a finding.
- **Mass assignment**: every model declares `#[Fillable]` (`Customer.php:18`, `Project.php:19`, `Epic.php:18`,
  `EpicComment.php:11`, `User.php:32`) and `active` is deliberately excluded from all of them, so a client cannot
  self-activate. Proven by `tests/Feature/Auth/InactiveUserTest.php:16-32`. Not a finding.
- **SQL injection**: the only raw SQL is `->orderByRaw('epics.start_date IS NULL')` (a constant literal, no
  interpolation) in `EpicListQuery.php:40,42,53,54` and the same in `ProjectListQuery` search-free paths. All search
  terms go through bound parameters; `ListQueryBase::paginate()` builds `where(... 'like', "%{$escapedSearch}%")`
  which the query builder parameterises. Not a finding.
- **Path traversal / file upload / SSRF / command injection**: no file handling, no outbound HTTP, and no
  `exec`/`shell_exec`/`proc_open` in `app/`. `chisel.php` uses `Symfony\Component\Process\Process` with an **array**
  command (`new Process($command)` where `$command` is a list of literals such as `['composer', 'lint']`), which
  does not invoke a shell — no injection surface. `chisel.php` is also a build-time installer script, not runtime code.
- **Session cookie security**: `config/session.php:172` sets `'secure' => env('SESSION_SECURE_COOKIE', env('APP_ENV') === 'production')`,
  `'http_only' => true` (line 185), `'same_site' => 'lax'` (line 202). Defaults are correct for production.
- **`.env.example` contains no secrets** — all credential values are the local dev defaults and `APP_KEY` is empty
  (line 4). Not a finding.
- **Insecure CI**: `.github/workflows/tests.yml` uses `permissions: contents: read`, `persist-credentials: false`,
  and every action is pinned to a full commit SHA. There is no `pull_request_target` and no secret interpolation into
  `run:` blocks. Not a finding.