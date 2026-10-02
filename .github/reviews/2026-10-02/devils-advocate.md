# Devil's Advocate — 2026-10-02

Every candidate finding was challenged before it reached the consolidated report.

## Hypotheses raised, then refuted with evidence

### REJECTED — "Eager-loading comments with `limit(20)` applies the limit globally across all epics in the page"

This was the strongest candidate HIGH finding of the review. `Relation::getEager()`
(`vendor/laravel/framework/src/Illuminate/Database/Eloquent/Relations/Relation.php:244-249`)
simply returns `$this->get()`, and there is no `getResultsByParent()` anywhere in the framework —
which appeared to prove a single global `LIMIT 20` and a silent data-loss bug in
`EpicListQuery::active()`.

**Refuted empirically.** Three epics with 30 comments each, queried through
`App\Queries\Epics\EpicListQuery::active()` on an isolated database:

```
Proof2 epic 1 count=30 loaded=20
Proof2 epic 2 count=30 loaded=20
Proof2 epic 0 count=30 loaded=20
SUM LOADED = 60

Q: select * from (select *, row_number() over (partition by `epic_comments`.`epic_id`
   order by `created_at` desc, `id` desc) as `laravel_row` from `epic_comments` ...
```

Laravel 13 resolves constrained `hasMany` eager loads with a single window-function query. The
limit is per parent and there is no N+1. **Dropped.**

### REJECTED — "Searching for the live search input loses focus / caret after each fragment refresh"

`resources/js/app.js:477` calls `window.Alpine.morph(this.$root, results.outerHTML)` where
`$root` is the very element that carries `x-data="listSearch(...)"` and contains the search input.

**Refuted.** Alpine's morph plugin patches the existing DOM in place, so the component instance and
focus survive; `tests/Browser/Customers/CustomerCrudTest.php:85-91` asserts exactly that
(`window.listSearchPageState` set before the search is still `'preserved'` after the morph).
**Dropped.**

### REJECTED — "Case-insensitive uniqueness lets you create `acme` when `ACME` exists"

`active_name` inherits `utf8mb4_unicode_ci`, and `SELECT 'ACME' = 'acme' COLLATE
utf8mb4_unicode_ci` returns `1` on the live server. On its own this looks like a bug.

**Refuted as a defect.** `Rule::unique(Customer::class)` resolves through the same connection and
therefore the same collation, so validation and the database always agree — the user is told "the
name has already been taken" instead of hitting a constraint violation. Kept only as a note in
`database.md` DB-002 so nobody later "fixes" the collation and silently changes behaviour.

### REJECTED — "`create_customer_conflict`/`resolve_name_conflict` request flags are dead"

Both look like leftovers: `*RestoreRequest` validates `resolve_name_conflict` and the controllers
use it only to choose a different success **message**; `reuse_deleted_name` only skips a branch.

**Refuted.** `resources/views/components/name-conflict-modal.blade.php:27,35` shows both flags are
the machine-readable form of the two buttons the user actually clicks ("create a new one" vs
"restore the deleted one"). They are the payload for a two-way decision, not dead code.
**Dropped.**

### REJECTED — "The comment drawer cannot re-open after posting a comment"

`resources/views/epics/list.blade.php:4-13` resolves `commented_epic_id` by searching
`$list['rows']`, which only holds the current page (5 rows).

**Refuted.** A comment can only be posted from the drawer of a row that was on screen, and
`EpicCommentController::store()` returns `back()`, which restores the previous URL *including*
`?page=`. The same page is re-rendered, so the epic is always present. **Dropped.**

### REJECTED — "Local Docker disables SQL strict mode (`--sql-mode=""`), weakening validation"

`config/database.php` sets `'strict' => true` for both mysql and mariadb connections, and Laravel
applies the mode per connection through `PDO::MYSQL_ATTR_INIT_COMMAND`, which overrides the server
default. **Dropped** (documented as verified-clean in `laravel.md`).

### REJECTED — "`EnsureUserIsActive` breaks the login POST because it runs in the `web` group"

`bootstrap/app.php:17` appends it to `web`, and `web` runs before the route's own middleware.

**Refuted.** `$request->user()` is `null` before authentication, so the guard short-circuits;
`prependToPriorityList(before: AuthenticatesRequests::class)` places it ahead of `auth` for
protected routes. Nine tests in `InactiveUserTest` cover login, 2FA (including mid-challenge
deactivation), passkey and remember-me. **Dropped.**

### REJECTED — "The three list views should be merged into one generic component"

Rejected on the project's own rules: `.github/docs/review-rules.md:27-42` forbids introducing
abstractions without a concrete problem, and the columns and search forms genuinely differ. The
finding was downgraded to **FE-001 (LOW, cleanup)** and scoped explicitly to the duplicated Alpine
block and fragment wrapper, using the existing `x-list.*` component precedent.

### REJECTED — "`data-payload="{{ json_encode($payload) }}"` is an XSS vector"

`resources/views/components/list/row-actions.blade.php:16,27` puts raw JSON into an HTML attribute
that Alpine parses with `JSON.parse`.

**Refuted.** Blade's `{{ }}` applies `e()` (`htmlspecialchars` with `ENT_QUOTES` and
double-encoding), so the browser decodes the attribute back to exactly the original JSON string
before `JSON.parse` runs. Recorded in `security.md` as checked-and-safe rather than dropped
silently, because the pattern looks wrong at a glance.

## Duplicates merged

| Kept | Merged into it |
|---|---|
| `laravel.md` LAR-001 | `testing.md` TEST-001 (the failing test) — one root cause, reported once as BUG-001 with the test as evidence |
| `database.md` DB-001 | `performance.md` PERF-002 — one issue (missing indexes), reported once with both the DDL and the EXPLAIN view |
| `devops.md` DEV-001 | `security.md` SEC-001 — identical evidence (`docker compose ps`); kept as a single MEDIUM finding |
| `testing.md` TEST-003 | `architecture.md` ARCH-001 — one documentation defect, reported once |
| `frontend.md` FE-002 | `maintainability.md` MAINT-001 — one orphan key |

## Severity downgrades after challenge

| Finding | Initial | Final | Reason |
|---|---|---|---|
| `deactivate`/`reactivate` lost update | MEDIUM | LOW (CONC-002) | Idempotent boolean display flag; no invariant depends on it. |
| Parent-deletion TOCTOU | MEDIUM | MEDIUM (kept) | It breaks an invariant written in `ARCHITECTURE.md:65`. Downgraded from HIGH: the window is milliseconds and there is no data loss. |
| Missing list indexes | MEDIUM | LOW (PERF-002 / DB-001) | 5 rows today. Recommending indexes now would be speculative optimisation. |
| One query per request from `EnsureUserIsActive` | MEDIUM | LOW (PERF-003) | Primary-key lookup; removing it would weaken a security behaviour. |
| Unbounded select option lists | MEDIUM | MEDIUM (kept) | It is the only performance issue with a realistic trigger (search-as-you-type re-fetching everything). |
| Duplicated list views | MEDIUM | LOW (FE-001) | Cleanup, not a defect; a fourth resource would justify it. |
| Orphan translation key | MEDIUM | LOW (MAINT-001) | Four dead lines. |
| No telemetry | MEDIUM | LOW (OBS-001) | Proportionate to the project scope. |

## Rejected as style / personal preference

The following were considered and are **not** in the consolidated report:

- Multi-line PHP expressions in Blade attributes
  (`resources/views/components/list/header.blade.php:29-30,34-35`) — valid, Pint-clean, and
  arguably more readable as-is.
- Suggesting interfaces, repositories, DTOs, a service layer, or a base `TrashController` to remove
  the three-way duplication — forbidden by `.github/docs/review-rules.md:27-42` without a concrete
  problem.
- `PER_PAGE = 5` being an odd number to choose — a product decision, not a defect.
- Rule ordering differences between `CustomerRequest` (`max` before `min`) and the other two
  (`min` before `max`) — semantically identical, and already recorded in
  `.github/tasks/06.model-consistency-audit.md` (C-06).
- `resources/views/layouts/app/sidebar.blade.php:42,47` `target="_blank"` without
  `rel="noopener noreferrer"` — implied by modern browsers, not exploitable.
- PHP 8.3 in CI vs 8.4 locally — already documented in `ARCHITECTURE.md:129`. Reported as DEV-002
  only because a new contributor will hit it, not as a defect.