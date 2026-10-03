# Devil's Advocate — 2026-10-02

Purpose: challenge every finding from the ten specialist reports before consolidation, per
`.github/agents/devil-advocate.agent.md`. The review is READ-ONLY; nothing was modified.

## Challenge log — findings I attacked and their verdict

### Falsified by experiment (removed)

**CLEAN-002 (original version) — "multi-line PHP concatenation embeds a newline in the Flux modal name".**
I compiled `resources/views/components/list/header.blade.php` in-container and read the output as
`['name' => $prefix.\n            '-form']`, and inferred a literal newline in the value. That inference was **wrong**.
PHP's `.` operator ignores whitespace, including newlines, between operands. Executed proof:

```
$ php -r '$prefix = "customer";
$name = $prefix.
            "-form";
var_dump($name, $name === "customer-form");'
string(13) "customer-form"
bool(true)
```

**Verdict: REJECTED as a defect.** The formatting is merely ugly. Removed from the finding list and recorded in
`clean-code.md` under "Notes / not findings" so nobody re-raises it.

**CLEAN-002 (revised) — "`x-on:click` prop is an injection surface".**
Re-examined. All three call sites pass literal repository strings
(`create-click="createCustomer()"`, `"createProject()"`, `"createEpic()"`), and Blade `{{ }}` escapes the attribute.
There is no path from user input to that prop, and it is not a vulnerability.
**Verdict: demoted to LOW "injection surface" observation.** It survives only as a documentation request, and I have
dropped it from the consolidated report's action plan — it requires no action.

### Duplicates (merged)

**LAR-002 and TEST-001 are the same defect.** Both report `phpstan analyse` failing on
`app/Http/Controllers/Controller.php:20`. Different specialists found it independently because the Laravel reviewer
ran PHPStan and the testing reviewer reasoned about `composer ci:check`'s ordering.
**Verdict: merged into a single finding (`BUG-002`), referenced from both reports.**

**TEST-002 and LAR-001 are the same defect.** Both report `View::fragmentIf()` returning a string and breaking
`assertViewHas`/`viewData`.
**Verdict: merged into a single finding (`BUG-001`).**

**PERF-002 and BUS-001 overlap.** PERF-002 reported the unbounded `get()` and flagged the `active` filter as a
side observation; BUS-001 reported the missing `active` filter as the primary issue.
**Verdict: merged into `BUS-001`**, which is the real problem. The unbounded-load half of PERF-002 is *not* a finding
at this data scale (5 rows) and is dropped — see below.

**MAINT-001 and the 2026-09-30 review's MAINT-001 are unrelated** despite the ID. Different subject. Kept, with the
ID prefix distinguishing them.

### Style / personal preference (rejected)

**"Extract the three list views into a shared component" (CLEAN-001).** Attacked on three grounds: (a) the column
sets genuinely differ — 3, 5 and 7 columns, plus different parent-payload shapes; (b) `ARCHITECTURE.md:122-123`
explicitly rejects abstractions not justified by a concrete problem; (c) the shared parts are *already* extracted
(`x-list.table`, `x-list.row-actions`, `x-list.confirm-modal`, `x-forms.tracked-resource`).
**Verdict: KEPT but downgraded.** The duplication is real and measurable (I quoted the three copies verbatim), but
the honest recommendation is narrower than "extract a generic list component": extract the Alpine `x-data` block
only. I rewrote the recommendation in the consolidated finding to say exactly that, and set it to P2. I am
**not** recommending a generic list-page component, which is what the 2026-09-30 review correctly warned against.

**CLEAN-003 — the nine `UniqueConstraintViolation` call sites.** Attacked: the two styles are not duplication, they
are different *requirements* — restore needs a different response than store/update. And the fix (a trait or abstract
controller) is explicitly forbidden by `ARCHITECTURE.md:122-123`.
**Verdict: downgraded MEDIUM → LOW, and the recommendation is now "accept it, revisit at four resources".** Dropped
from the action plan entirely.

**CLEAN-005 — duplicated `orderBy` chains in `EpicListQuery`.** Nine lines repeated twice, adjacent in the same
class. **Verdict: KEPT at LOW / P3.** The extraction is a single private method on the existing class — no new type.

**DB-002 — `STORED` generated column wastes space.** Attacked: five rows, `STORED` is required for a
UNIQUE-indexed generated column in MySQL 8/MariaDB (a `VIRTUAL` column cannot be indexed without an explicit
prefix), so this is not even a free choice.
**Verdict: demoted to INFO.** Removed from the action plan.

**ARCH-005 — missing `config/view.php`, `config/hashing.php`.** Pure opinion. **Verdict: REJECTED.**

**PERF-005 — no index usable for `LIKE '%x%'`.** Attacked: five rows. A trigram index would be absurd.
**Verdict: demoted to INFO and dropped from the action plan.**

**DB-004 / DB-005 — missing indexes, confirmed by EXPLAIN.** Same attack: `customers` has 5 rows.
**Verdict: demoted to LOW with an explicit "no action now" recommendation.** Kept only so the EXPLAIN evidence
exists; dropped from the action plan.

### Pattern-driven recommendations (rejected outright)

**"Add an error tracker (Sentry/Bugsnag)."** No deployment target is declared anywhere in the repository.
`ARCHITECTURE.md:127-133` mentions only environment and credentials.
**Verdict: REJECTED.**

**"Add structured JSON logging."** There is no log consumer to emit JSON for. **REJECTED.**

**"Add uptime monitoring / alerting."** Nothing to alert on; `failed_jobs` and `cache` are provably empty (no jobs,
no `Cache::` calls). **VERIFIED AND REJECTED** — this is the "recommend a stack because a stack exists" pattern the
review rules prohibit.

**"Add distributed caching / Redis."** Redis appears in `.env` as unused boilerplate; there is no `Cache::` call and
no job. `ARCHITECTURE.md:77-81` says so. **REJECTED.**

**"Add optimistic locking on `updated_at`."** Would surface conflicts to users in an app where a record is edited
by one person at a time. **REJECTED** — it would *reduce* usability without solving a real problem.

**"Add a trait / base model for `active`."** Three models, one boolean. Forbidden by
`ARCHITECTURE.md:122-123`. **REJECTED.** The honest recommendation is one sentence of documentation (ARCH-002).

### Severity challenged and reduced

**SEC-001 (`.env` APP_KEY).** Attacked hard: `git ls-files` proves `.env` is **not tracked**, and the file lives in
the developer's own working tree. Nothing leaked. Calling a developer's local dev key a HIGH finding is an
exaggeration — the security agent's own rules say "Do not exaggerate severity."
**Verdict: demoted HIGH → LOW, reframed as "hardening".** It survives only because of the paired `APP_DEBUG=true` +
`0.0.0.0:80` exposure, which is OPS-004. I merged the actionable half into `OPS-001` (bind to loopback) and kept the
secret-hygiene half as an INFO.

**SEC-004 (all policies return `true`).** Attacked: `ARCHITECTURE.md:40-44` documents this as intended product
scope, and `http.instructions.md` says "do not add roles unasked". Reporting documented intent as a vulnerability
would violate the review rules.
**Verdict: KEPT at MEDIUM but reframed entirely** — the finding is no longer "there is a vulnerability", it is
"the policy file reads as if it enforces ownership when it enforces nothing, and no test would catch a future
role change". The recommendation is a comment, not a role system.

**SEC-003 (`authenticateUsing` reimplements credential lookup).** Attacked: there is no bypass; the pipeline still
runs `EnsureLoginIsNotThrottled`; the `Login` listener covers the deactivation race; and every behaviour is tested
by `InactiveUserTest`. Confidence is MEDIUM because I did not execute a modified provider.
**Verdict: KEPT at MEDIUM with MEDIUM confidence** — an honest "maintenance risk, no demonstrated bypass", which is
exactly what the agent rules call a *probable risk*.

**CONC-002 (check-then-act on parent delete).** Attacked: requires two simultaneous requests on the same record in
an app with a handful of users; and the *force-delete* path is actually protected by the FK `RESTRICT`, which turns
the worst case into a 500 rather than corruption.
**Verdict: KEPT at LOW.** It is a real TOCTOU with a documented invariant behind it, but it is not HIGH.

**FE-005 (jQuery/Select2 in the main bundle).** Attacked: I did not measure bundle size, and the rules forbid
speculative optimisation.
**Verdict: KEPT at LOW with an explicit "do not do this now" recommendation**, and it does not appear in the action
plan.

### Unsupported / unverifiable (removed or downgraded)

**OBS-002 / MAINT-005 / DEP-004.** These are observations about missing tooling, not defects. DEP-004 in particular
("verify dependabot covers both ecosystems") I did **not** read the file, so I removed it rather than assert
anything about its contents. **Verdict: REMOVED from the consolidated report.**

### Confirmed — survived every challenge

- **BUG-001** — `View::fragmentIf()` returns a string. Independently reproduced (`4 failed, 233 passed`), root cause
  read out of framework source, and confirmed present in committed HEAD via `git show HEAD:<file>`.
- **BUG-002** — PHPStan error. Independently reproduced twice.
- **BUG-003** — `ArchitectureTest` failure. Independently reproduced.
- **BUS-001** — missing `active` filter, proven with live data: an inactive customer (`Bezero 2`, `active=0`) is
  currently offered in the project form. Already an accepted TODO in `todo.md`.
- **DB-001** — collation divergence. Proven from the live `SHOW CREATE TABLE` collation plus the partial-index DDL.
- **BUS-005** — restore-conflict copy says "active" while the guard checks all non-deleted rows. Proven by reading
  both lines.
- **OPS-002** — PHPUnit `<env>` non-forcing. Proven by executing the framework's own resolution logic in-container.
- **DEP-005** — `rm -f composer.lock` in a bind-mounted compose service. Read directly from the file.

## Action plan

Ordered by impact, no aesthetic items.

### P0 — CI is red; nothing else can be verified until these land

1. `BUG-001` — return the `View` instead of a rendered string from the nine list `index()` actions.
2. `BUG-002` — `@param view-string $view` on `Controller::listView()`. (One line; unblocks PHPStan, which currently
   stops CI before the suite runs.)
3. `BUG-003` — rename `EpicCommentController::index` to `show` so the architecture rule stays meaningful.

### P1 — correctness gaps that a user can hit today

4. `BUS-001` — filter the parent selects by `active`; the empty-state copy already promises this.
5. `BUS-005` — correct the "another active X uses this name" copy in three trash controllers, in all four locales.
6. `OPS-002` — add `force="true"` to the `DB_*` entries in `phpunit.xml` so an ambient variable cannot redirect the
   suite at another database.

### P2 — keep it maintainable

7. `CLEAN-001` — extract the duplicated Alpine `x-data` block from the three list views (narrow scope only).
8. `MAINT-001` — turn the uniqueness invariant into an assertion instead of prose.
9. `ARCH-001` / `OPS-001` — correct the test-database documentation and make the `ci` job use `laravel_test`.
10. `DEP-005` — stop deleting lockfiles in the compose helper services.
11. `OBS-001` — surface list-refresh failures instead of throwing into an unhandled rejection.
12. `BUS-006` — make the comment redirect unconditional, removing an untested fallback.

### P3 — when convenient

13. `CLEAN-005` — extract the duplicated ordering chain in `EpicListQuery`.
14. `CONC-001` — make `deactivate`/`reactivate` atomic single-query updates.
15. `CONC-002` — wrap the parent-delete guard and the delete in one transaction.
16. `DEP-001` — drop the pinned linux-x64 native binaries from `optionalDependencies`.
17. `FE-003` / `FE-005` / `MAINT-005` — optional UX and seed-data improvements.
18. `OPS-003` / `OPS-004` — align local `sql_mode` with production; bind the app port to loopback.