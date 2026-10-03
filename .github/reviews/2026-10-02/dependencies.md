# Dependencies Specialist Review — 2026-10-02

Snapshot: HEAD `781ccbb` + working tree. Both lockfiles present and committed.

## DEP-001 — Linux-x64 native binaries are pinned as direct `optionalDependencies`

Severity: MEDIUM
Category: Dependencies / Cross-platform
File: package.json
Line: 21-25
Confidence: HIGH

Problem:

```json
"optionalDependencies": {
    "@laravel/multiplex": "^0.4.1",
    "@rollup/rollup-linux-x64-gnu": "4.9.5",
    "@tailwindcss/oxide-linux-x64-gnu": "^4.0.1",
    "lightningcss-linux-x64-gnu": "^1.29.1"
}
```

Three of these are **platform-specific native binaries for linux-x64 only**, declared as direct dependencies of the
root project, and `@rollup/rollup-linux-x64-gnu` is pinned to an exact version (`4.9.5`, no caret) while its siblings
use ranges.

Evidence:

- `package.json:21-25` (quoted).
- The lockfile contains the full multi-platform matrix for these packages, which is what upstream publishes:
  `@tailwindcss/oxide` has `linux-{x64,x64-musl,arm64,arm64-musl,arm,arm-gnueabihf}` and `win32-{x64,arm64}`;
  `lightningcss` has the same set. Verified by enumerating `packages` keys in `package-lock.json`.
- `docker-compose.yml:94,120` pins the npm services to `node:${DC_NODE:-24-bullseye}` — i.e. the build always runs on
  linux, which is why this works today.
- `README`-level evidence that it works: `.github/workflows/tests.yml:141` runs `npm run build` on `ubuntu-latest`.

Impact:

`npm install` / `npm ci` on macOS or Windows will resolve these `os`/`cpu`-constrained packages as skipped
(they carry `"os": ["linux"], "cpu": ["x64"]`), so the direct entries do no harm — but they also do no good, and the
exact pin on `@rollup/rollup-linux-x64-gnu` at `4.9.5` will fight `vite`'s own resolution the moment Vite bumps its
Rollup requirement. The lockfile currently has no top-level `rollup` entry at all (verified: zero occurrences of the
string `rollup` in `package-lock.json`), so the pinned binary is the only source of it.

Recommendation:

Remove the three platform binaries from `optionalDependencies` and let the toolchains pull their own. If they were
added deliberately to work around an install failure, say so in a comment — but the lockfile already contains the full
matrix, so the pin is very likely a leftover from a debugging session.

`@laravel/multiplex` (an optional Laravel MCP/agent package) is a different matter: it is genuinely optional and
correctly declared. Keep it.

## DEP-002 — `composer audit` and `npm audit` are clean

Severity: INFO (positive finding)
Category: Dependencies / Security
File: composer.lock
Line: —
Confidence: HIGH

Problem:

None.

Evidence:

```
$ docker compose exec laravel13 composer audit --no-interaction
No security vulnerability advisories found.

$ docker compose run --rm --no-deps --entrypoint npm laravel13-npm audit --omit=dev
found 0 vulnerabilities
```

Both were executed live. No lockfile was modified by either command (verified with `git status`).

Impact:

None.

Recommendation:

None.

## DEP-003 — Direct dependencies are current and consistently constrained

Severity: INFO (positive finding)
Category: Dependencies
File: composer.json
Line: 14-21
Confidence: HIGH

Problem:

None.

Evidence:

```json
"require": {
    "php": "^8.3",
    "laravel/chisel": "^0.1.0",
    "laravel/fortify": "^1.37.2",
    "laravel/framework": "^13.17",
    "laravel/tinker": "^3.0",
    "livewire/blaze": "^1.0",
    "livewire/flux": "^2.13.1",
    "livewire/livewire": "^4.1"
}
```

Every constraint is a caret range with no wildcards, no `dev-`/`alpha`/`beta` channels, and
`"minimum-stability": "stable"` + `"prefer-stable": true` (`composer.json:98-99`). Dev dependencies are all
first-party Laravel tooling plus PHPUnit/Pint/Larastan/Dusk — no abandoned packages.

The frontend (`package.json:10-19`) is similarly range-constrained except for `vite-plus` which is pinned exactly at
`0.3.0` — correct practice for a `0.x` package, where caret ranges would allow breaking minor bumps.

Impact:

None.

Recommendation:

None. Note `vite-plus` is the only correct exact-pin in `package.json`; the three in DEP-001 are not.

## DEP-004 — `.github/dependabot.yml` exists; verify it covers both ecosystems

Severity: INFO
Category: Dependencies / Maintenance
File: .github/dependabot.yml
Line: —
Confidence: MEDIUM

Problem:

Cannot fully verify from the file alone without reading it; the file is present and tracked (`git ls-files` includes
`.github/dependabot.yml`). Recorded as an observation rather than a finding.

Impact:

None either way.

Recommendation:

Confirm it schedules both `composer` and `npm` on a short interval. Nothing else to change.

## DEP-005 — `composer.json` scripts delete `vendor/` and `composer.lock` in the local composer service

Severity: MEDIUM
Category: Dependencies / Developer safety
File: docker-compose.yml
Line: 70-76
Confidence: HIGH

Problem:

```yaml
laravel13-composer:
  profiles: ["composer"]
  command: bash -c "
      rm -Rf vendor
      && rm -f composer.lock
      && composer install --no-progress
      && chown -R ${DC_UID:-1000}:${DC_GID:-1000} vendor
      && chown ${DC_UID:-1000}:${DC_GID:-1000} composer.lock
      && composer show | grep izt"
```

`./:/docker` is a bind mount of the repository, so `rm -f composer.lock` deletes the committed lockfile and
`composer install` **regenerates it from whatever constraints resolve that day**. The equivalent npm services do the
same: `docker-compose.yml:107-108` (`rm -f package-lock.json && rm -Rf node_modules`) and `:129-130`.

Evidence: quoted above, for both files.

Impact:

Running `docker compose --profile composer run laravel13-composer` silently produces a different lockfile, which will
show up as an enormous unrelated diff in the next commit and can move dependency versions without anyone noticing.
This is a footgun in a repo where `AGENTS.md` explicitly says "Do not add base folders or change dependencies without
approval."

Recommendation:

Remove `rm -f composer.lock` / `rm -f package-lock.json` from those service commands. If a clean-lockfile
regeneration is genuinely wanted, make it an explicit, separate documented command rather than a side effect of
running the installer. This is a one-line deletion in three places and carries no risk.

## Notes / not findings (rejected after devil's-advocate challenge)

- **`jquery` 3.7 as a runtime dependency.** Legacy, but it is required by Select2 4 and is a deliberate,
  contained choice (one dropdown). Replacing Select2 with a native `<select>` + Alpine would remove jQuery *and*
  Select2 and simplify `resources/js/app.js:266-364`, but that is a UX-visible refactor of a working feature, not a
  defect. **Rejected.**
- **`laravel/chisel` as a runtime dependency.** It is the starter-kit variant installer; `chisel.php` and
  `chisel-paths.php` are build-time scaffolding, and `composer.json:74-80` deletes them in the `apply` step. Having it
  in `require` rather than `require-dev` is a cosmetic issue. **Rejected.**
- **`laravel/sail`, `laravel/pail`, `laravel/pao`, `laravel/boost` in `require-dev`** while the project runs Docker
  via `docker-compose.yml`, not Sail. Sail is unused but harmless (it is the official Laravel skeleton default and
  `boost.json:11` has `"sail": false`). **Rejected** — removing it would deviate from upstream skeleton defaults for
  no benefit.
- **`vite` 8.3.2 with `vite-plus` 0.3.0.** The lockfile resolves `vite` 8.3.2 and `vite-plus` 0.3.0. No conflict was
  observed and the build manifest exists locally. **Rejected.**
- **Lockfile integrity.** `package-lock.json` is `lockfileVersion: 3`, committed, and `npm ci` is used in CI
  (`.github/workflows/tests.yml:135`), so installs are reproducible. `composer.lock` is committed and CI runs
  `composer setup` → `composer install`, also reproducible. **Rejected.**