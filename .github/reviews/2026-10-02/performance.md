# Performance Review — 2026-10-02

READ-ONLY. `EXPLAIN` run against the live MariaDB; no benchmark numbers are invented.

## Findings

### PERF-001 — Every list render loads unbounded option lists, including on every search refresh

Severity: MEDIUM
Category: Performance
File: app/Http/Controllers/ProjectController.php, app/Http/Controllers/EpicController.php
Line: 28 (both)
Confidence: HIGH

Problem: the project form and the epic form are rendered inline in the list view, so the option
lists are re-queried on every request — including each debounced search fragment refresh.

Evidence:

```php
// app/Http/Controllers/ProjectController.php:28
'availableCustomers' => Customer::query()->orderBy('name')->get(['id', 'name']),

// app/Http/Controllers/EpicController.php:28
'availableProjects' => Project::query()->with('customer')->orderBy('name')->get(['id', 'name', 'customer_id']),
```

Both are unbounded `get()` calls with no pagination, no search and no `active` filter. The epic one
additionally eager-loads every customer of every project.

Trigger frequency: `resources/js/app.js:384-401` fires a request on every search input after
400 ms of inactivity, plus every pagination link (`app.js:415-431`), plus every popstate
(`app.js:375`).

Likely bottleneck: two full-table scans plus one `customers` scan per list request, and O(n) rows
serialised into the HTML/JSON payload of every response.

Expected impact: negligible at 5 customers / 67 projects. Linear degradation; at a few thousand
projects the epic list response alone would carry thousands of `<option>` elements and their
customers, and the search-as-you-type UX would visibly stall.

Proposed solution (do not build now): make the selects searchable/remotely loaded — the project
form already integrates select2 (`resources/views/projects/form.blade.php:34-47`), so the epic
select can reuse the same pattern with a small `GET epics/projects-options?search=` endpoint.

Complexity/cost: medium (new endpoint + new Form Request + authorization + tests). Justification
threshold: when projects/customers exceed a few hundred rows.

### PERF-002 — List filters and sorts are unindexed, and the search is a leading-wildcard `LIKE`

Severity: LOW
Category: Performance
File: app/Queries/Customers/CustomerListQuery.php, app/Queries/Projects/ProjectListQuery.php,
      app/Queries/Epics/EpicListQuery.php, app/Queries/ListQueryBase.php
Line: 22-24 / 29-35 / 37-50 / 30-34
Confidence: HIGH

Problem: every list query filters/sorts on unindexed columns and, when searching, on
`LIKE '%term%'`, which cannot use any index by construction.

Evidence:

```console
$ EXPLAIN SELECT * FROM customers WHERE deleted_at IS NULL AND active=1 AND (name LIKE '%acme%') ORDER BY name LIMIT 5;
type: ALL   key: NULL   Extra: Using where; Using filesort
```

`orderByRaw('epics.start_date IS NULL')` (`EpicListQuery:43,45`) forces a temporary table plus
filesort in addition.

Likely bottleneck: full table scan + filesort per list page.
Expected impact: not measurable at current row counts. Relevant past tens of thousands of rows.
Proposed solution: composite indexes chosen per list query, introduced together with (not before)
the data that needs them. See `database.md` DB-001 — same finding, database-side view.
Complexity/cost: low technically, but premature today.

### PERF-003 — `EnsureUserIsActive` adds one query to every authenticated request

Severity: LOW
Category: Performance
File: app/Http/Middleware/EnsureUserIsActive.php
Line: 24
Confidence: HIGH

Problem: every web request for an authenticated user runs an extra
`select exists(select * from users where id = ? and active = 1)`.

Evidence:

```php
if ($user instanceof User &&
    ! User::whereKey($user->getAuthIdentifier())->where('active', true)->exists()) {
```

Expected impact: one extra primary-key lookup per request — negligible.
Recommendation: keep it. This is the deliberate trade-off that revokes sessions on deactivation
(`InactiveUserTest:95-110`); caching it would weaken a documented security behaviour. Recorded for
completeness only.

### PERF-004 — Page size is hardcoded to 5

Severity: LOW
Category: Performance / UX
File: app/Queries/ListQueryBase.php
Line: 14
Confidence: HIGH

Problem: `public const PER_PAGE = 5;` is not configurable and not overridable per request.

Impact: 100 epics → 20 pages; every page is a round trip. The number also directly amplifies
PERF-001, because a larger page would load more option rows per response.
Recommendation: promote it to a config value (one line, `config('lists.per_page', 5)`) only when
the UX asks for it. Not a correctness problem.

## Verified clean (no findings)

- **No N+1 in the list queries.** `ProjectListQuery::withCustomer()` eager-loads `customer`;
  `EpicListQuery` eager-loads `project.customer`; `CustomerListQuery` uses `withExists` for the
  child-presence flag. Transformers only read already-loaded relations.
- **The per-epic comment limit is not an N+1.** Laravel 13 resolves a constrained `hasMany`
  eager load with one window-function query; measured 3 epics × 30 comments → 60 rows loaded in
  one statement.
- **No unbounded loops.** Transformers map over `$paginator->items()` (already 5 rows).