# Dependencies Review — 2026-10-02

## Findings

### DEP-001 — No lockfile, but `docker-compose.yml` deletes and regenerates `composer.lock`

Severity: LOW
Category: Dependencies / Reproducibility
File: docker-compose.yml
Line: 60-74 (service `laravel13-composer`)
Confidence: HIGH

Problem: the `composer` profile is the documented way to install PHP dependencies, and its command
starts with:

```yaml
command: bash -c "
    rm -Rf vendor
    && rm -f composer.lock
    && composer install --no-progress
```

Impact: running what looks like a routine "install dependencies" step silently rewrites a committed
lockfile and can upgrade every package to its newest allowed version. That makes dependency drift
easy to introduce without noticing it in a diff, and it is not covered by `composer audit` on the
committed lockfile any more.

Recommendation: use `composer update` explicitly when you intend to change versions, and make the
compose service `composer install` only. If the intent was "give me the latest", rename the service
so the destructive behaviour is obvious from the command name.

### DEP-002 — `laravel13-npm` installs `npm@latest` on every invocation

Severity: LOW
Category: Dependencies / Reproducibility
File: docker-compose.yml
Line: 88-90 and 111-113
Confidence: HIGH

Problem: both npm services run `npm install -g npm@latest && npm --version && npm config set
update-notifier false && npm install && npm run build`. The npm version therefore floats with wall
-clock time, and `npm install` (rather than `npm ci`) can rewrite `package-lock.json`.

Impact: a build that worked yesterday can fail today with no repository change. `package-lock.json`
is committed and `npm ci` is used in CI, so CI itself is deterministic — the flakiness is
local-only, which is exactly where it costs the most debugging time.

Recommendation: drop the `npm install -g npm@latest` step (the image already pins a Node major via
`${DC_NODE:-24-bullseye}`) and use `npm ci` in the local services so the lockfile is authoritative.

### DEP-003 — `.env.example` commits a machine-specific UID/GID

Severity: LOW
Category: Dependencies / Repo hygiene
File: .env.example
Line: 44-45
Confidence: HIGH

Problem: `DC_UID=1002` / `DC_GID=1002` are committed and consumed by `docker-compose.yml`
(`user: "${DC_UID:-1000}:${DC_GID:-1000}"` on three services). On any other machine the containers
run as the wrong uid unless the developer overrides them.

Impact: silent permission errors on `storage/` and `public/build` for new contributors — the kind
of friction that produces "works on my machine" bug reports.
Recommendation: comment the two lines out (the compose default of `1000` is the sane baseline) or
document the override in `AGENTS.md`.

## Verified clean (no findings)

- `composer audit --no-interaction` → **No security vulnerability advisories found.**
- `npm audit --omit=dev` → **found 0 vulnerabilities.**
- `npm ls --depth=0` → dependency tree resolves with no missing/invalid packages.
- Direct dependencies are current and coherent: `laravel/framework ^13.17`,
  `laravel/fortify ^1.37.2`, `livewire/livewire ^4.1`, `livewire/flux ^2.13.1`,
  `livewire/blaze ^1.0`, `laravel/chisel ^0.1.0`.
- Dev tooling: `phpunit/phpunit ^12.5`, `brianium/paratest ^7.20`, `laravel/dusk ^8.7`,
  `larastan/larastan ^3.9`, `laravel/pint ^1.27`, `nunomaduro/collision ^8.9.3`.
- No unused runtime dependency found (`livewire/blaze` backs the Volt-style
  `resources/views/pages/settings/⚡*.blade.php` components).
- `jquery` + `select2` are declared in `dependencies` although they are build-time assets, not a
  runtime concern; harmless.
- `.github/dependabot.yml` covers github-actions, composer and npm on a weekly schedule with a
  5-day cooldown and per-ecosystem grouping — appropriate for this size.
- All GitHub Actions are pinned to commit SHAs with version comments
  (`actions/checkout@3d3c42e…` v7.0.1, `shivammathur/setup-php@f3e473d…` v2,
  `actions/setup-node@8207627…` v7.0.0, `actions/cache@55cc834…` v6.1.0).
- No `laravel/sail`, no Horizon, no Redis client usage — the Redis variables in `.env.example` are
  unused boilerplate and can stay.