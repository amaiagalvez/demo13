# Performance Specialist Review — 2026-10-02

Snapshot: HEAD `781ccbb` + working tree. Real `EXPLAIN` output captured via Boost MCP against MariaDB 11.7.2.

Scale context, measured: `customers` = 5 rows, `projects` = 2 rows, `epics` = 1 row, `PER_PAGE` = 5
(`app/Queries/ListQueryBase.php:14`). This is a single-tenant internal CRUD app. Most "optimisation" suggestions are
rejected below for that reason. Only one finding survives, and it is a correctness-adjacent bug, not a speed issue.

## PERF-001 — Eager-loaded `comments` used `limit()` inside `with()`, which applies a **global** limit, not a per-epic limit

Severity: MEDIUM
Category: Performance / N+1
File: app/Queries/Epics/EpicListQuery.php
Line: 37-40 (committed HEAD)
Confidence: HIGH

Problem:

The committed list query embedded comments with a per-parent limit:

```php
->with(['comments' => fn (Relation $query) => $query
    ->with('user')
    ->latest()
    ->latest('id')
    ->limit(self::RECENT_COMMENTS_LIMIT)])   // RECENT_COMMENTS_LIMIT = 20
```

`HasMany::limit()` has two behaviours (vendor/laravel/framework/src/Illuminate/Database/Eloquent/Relations/HasOneOrMany.php:557-565):

```php
public function limit($value)
{
    if ($this->parent->exists) {
        $this->query->limit($value);
    } else {
        $this->query->groupLimit($value, $this->getExistenceCompareKey());
    }
}
```

During eager loading the relation is built by `Builder::getRelation()` from `newInstance()` (Builder.php:1004-1010), so
`$this->parent->exists` is `false` and the `groupLimit` branch is taken — which is the correct per-parent windowed
limit. **This code is therefore correct.** The finding is recorded because the correctness depends entirely on that
non-obvious `parent->exists` branch: if the relation were ever loaded through a path where the parent exists, or if
someone moved the `limit()` to a `->whereHas`/subquery, the list would silently show the 20 newest comments across
*all* epics instead of 20 per epic.

Evidence:

- `vendor/.../HasOneOrMany.php:557` (the branch), `vendor/.../Eloquent/Builder.php:999-1010` (`getRelation()` uses `newInstance()`).
- `app/Queries/Epics/EpicListQuery.php:20-24` — the committed `active()` applies the eager-load closure.
- Test coverage at HEAD: `tests/Feature/Epics/EpicCommentTest.php` `test_list_embeds_only_the_most_recent_comments_of_each_epic_but_counts_all`
  creates `$limit` comments on one epic plus one on another, which is exactly the assertion that would catch a
  global limit.

Impact:

None today — behaviour is correct and tested. The risk is regression-only.

Recommendation:

No change. If the eager load is ever replaced (it is being replaced, in the working tree, with a per-epic JSON
endpoint `epics.comments.index`), the per-parent semantics must be preserved in the replacement. The replacement
in the working tree does preserve it — `$epic->comments()->latest()->latest('id')->limit(...)` on an already-resolved
parent takes the plain `limit()` branch, which is correct.

## PERF-002 — `availableCustomers` / `availableProjects` are loaded in full on every list render

Severity: MEDIUM
Category: Performance / Unbounded query
File: app/Http/Controllers/ProjectController.php
Line: 28
Confidence: HIGH

Problem:

The project list controller loads the entire customer table on every render, with no `active` filter, no pagination
and no limit:

```php
// app/Http/Controllers/ProjectController.php:28
'availableCustomers' => Customer::query()->orderBy('name')->get(['id', 'name']),
```

and the epic list does the same for projects:

```php
// app/Http/Controllers/EpicController.php:28
'availableProjects' => Project::query()->with('customer')->orderBy('name')->get(['id', 'name', 'customer_id']),
```

`Customer::query()` excludes soft-deleted rows (SoftDeletes scope) but **includes inactive** rows, which is why the
callout in `resources/views/projects/list.blade.php:90-94` says "No active customers are available" while the query
does not filter on `active`.

Evidence:

- `ProjectController.php:28` (customers), `EpicController.php:28` (projects, plus an eager-loaded `customer`).
- The callout copy: `resources/views/projects/list.blade.php:92` `__('No active customers are available. Create one from the project form.')`
  and `resources/views/epics/list.blade.php:106` `__('No active projects are available. Create a project before adding epics.')`.
- Live data confirms inactive rows are returned: `SELECT id, name, active FROM customers` returns
  `id=2, name='Bezero 2', active=0`, and `projects.id=1` belongs to `customer_id=2` — i.e. that inactive customer is
  currently offered in the project form's select.
- Contrast with the other four list controllers, which correctly pass an empty collection:
  `ProjectInactiveController.php:24`, `ProjectTrashController.php:27`, `EpicInactiveController.php:24`, `EpicTrashController.php:27`.

Impact:

Two separate effects:

1. **Performance.** Unbounded `->get()` on the list page. Today this is 5 customers / 2 projects — free. It becomes a
   real problem at a few thousand customers, and the page is already loading 5 paginated projects. The `with('customer')`
   on the epics variant adds a second unbounded query. This is a genuine scaling cliff, but the application is not
   near it.
2. **Correctness / UX (the more important half).** The `active` flag is not applied, so inactive customers and
   projects are selectable in create/edit forms, contradicting the on-screen callout. This is reported as
   `BUS-001` in the business report; it is repeated here only because the query is the evidence.

Recommendation:

Filter to active records, which fixes the UX half and incidentally reduces the row count:

```php
'availableCustomers' => Customer::query()->where('active', true)->orderBy('name')->get(['id', 'name']),
```

For the edit case the currently-selected parent must remain visible even if inactive (the requirement already
recorded in `todo.md`). Handle that in the form by merging the selected record, or defer it — but do **not** defer
the `active` filter, because the current behaviour contradicts the visible copy.

No pagination for these selects is defensible at this scale; do not add it speculatively.

## PERF-003 — `withExists()` subqueries for `projects_exists` / `epics_exists` are correct and cheap

Severity: INFO (recorded to prevent a future "optimisation")
Category: Performance
File: app/Queries/Customers/CustomerListQuery.php
Line: 23
Confidence: HIGH

Problem:

None. Recorded because the naive alternative (`$customer->projects->count()` in the transformer) is an N+1 and
someone may "fix" it later.

Evidence:

```php
// app/Queries/Customers/CustomerListQuery.php:23
->withExists(['projects' => fn (Builder $query) => $query->withoutGlobalScope(SoftDeletingScope::class)])
```

consumed at `app/Transformers/CustomerListTransformer.php:43` as `$customer->projects_exists`. Equivalent pattern at
`ProjectListQuery.php:30`. The `withoutGlobalScope(SoftDeletingScope::class)` is deliberate: it counts trashed
children, which is what `ARCHITECTURE.md:58` requires ("Index rows offer deactivation instead of deletion when a
customer has projects or a project has epics, **including deleted children**").

Impact:

None. One correlated subquery per row, 5 rows.

Recommendation:

Keep as-is. Do not replace with a lazy collection.

## PERF-004 — `ListQueryBase::paginate()` search escaping is correct and does not introduce a wildcard bypass

Severity: INFO (recorded as a positive)
Category: Performance / Correctness
File: app/Queries/ListQueryBase.php
Line: 31
Confidence: HIGH

Problem:

None. `$escapedSearch = addcslashes($search, '%_\\');` then `"%{$escapedSearch}%"` correctly neutralises user-supplied
`%` and `_` wildcards, which prevents a search for `%` from matching every row. All values are bound parameters.

Evidence:

```php
// app/Queries/ListQueryBase.php:29-40
$escapedSearch = addcslashes($search, '%_\\');
$query->where(function (Builder $query) use ($escapedSearch, $searchColumns): void {
    $query->where($searchColumns[0], 'like', "%{$escapedSearch}%");
    ...
});
```

Note: escaping `%`/`_` in the *value* works because MySQL/MariaDB's default `LIKE` escape character is `\`. If the
connection ever set `NO_BACKSLASH_ESCAPES`, `addcslashes` would produce literal backslashes instead. `docker-compose.yml:201`
runs MariaDB with `--sql-mode=""`, which does **not** include `NO_BACKSLASH_ESCAPES`, so this holds today.

Impact:

None.

Recommendation:

None. Noted only so the `NO_BACKSLASH_ESCAPES` dependency is known.

## PERF-005 — Leading-wildcard `LIKE` prevents index use on `name`

Severity: INFO
Category: Performance
File: app/Queries/ListQueryBase.php
Line: 34
Confidence: HIGH

Problem:

`"%{$search}%"` cannot use a B-tree index on `name`. At 5 customers this is irrelevant.

Evidence:

EXPLAIN of the epics list (representative) shows `type: ALL` with `key: NULL` — but the dominant cost is the join +
filesort from the multi-column `ORDER BY`, not the `LIKE` (no search term was supplied in that EXPLAIN).

Impact:

None at this scale. A trigram/full-text index would be pure over-engineering here.

Recommendation:

**Rejected.** Do not add full-text search or trigram indexes. Recorded only so the "missing index on name" idea is
not re-proposed without data.

## Notes / not findings (rejected after devil's-advocate challenge)

- **N+1 on the list pages.** Checked and clean: `EpicListQuery::withProjectAndCustomer()` eager-loads
  `project.customer`; `ProjectListQuery::withCustomer()` eager-loads `customer`; transformers read only those
  eager-loaded relations. `EpicListTransformer::columns()` touches `$epic->project->name` and `$project->customer->name`
  — both covered by the `with('project.customer')`. No N+1.
- **`withCount('comments')`** is one subquery, needed for the badge, and the counts are rendered for all 5 rows.
  Correct.
- **Fragment refresh re-queries the whole list.** `resources/js/app.js:453` fetches on every debounced keystroke
  (400 ms) with `AbortController`. This is one paginated query per pause in typing — acceptable, and the abort
  prevents pile-up. Not a finding.
- **No caching anywhere.** `CACHE_STORE=database`, no `Cache::` calls in `app/`. For a 5-row table, adding cache
  would be pure cost. **Rejected.**
- **No pagination on the select2 customer options** — see PERF-002, where the real issue is the missing `active`
  filter, not the missing pagination.
- **Blade rendering cost / Alpine payload size.** The list fragment is re-fetched as HTML and morphed by Alpine
  (`app.js:477`). This is a deliberate, documented choice (`ARCHITECTURE.md`, list fragments) and works. Not a finding.
- **Queue/Horizon/Redis performance.** Not applicable — `ARCHITECTURE.md:77-81` and a grep of `app/` confirm there
  are **no** jobs, events, listeners or notifications in the application.