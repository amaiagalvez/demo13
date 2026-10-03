# Database Specialist Review — 2026-10-02

Snapshot: HEAD `781ccbb` + working tree. Engine: **MariaDB 11.7.2** (docker `db` service).
Evidence gathered with read-only `SHOW CREATE TABLE`, `SHOW INDEX`, `EXPLAIN` via the Boost MCP tools.
No INSERT/UPDATE/DELETE/DDL was executed and no migration was run.

## DB-001 — Cross-driver divergence: the MariaDB and SQLite uniqueness strategies are not semantically equivalent

Severity: MEDIUM
Category: Database / Migrations
File: database/migrations/2026_09_25_000000_create_customers_table.php
Line: 27-40
Confidence: HIGH

Problem:

Each of the three create-table migrations implements "unique name among non-deleted rows" twice, with two
different mechanisms, and they do not agree on collation:

```php
// MariaDB / MySQL branch
$table->string('active_name')->nullable()->storedAs('IF(deleted_at IS NULL, name, NULL)');
$table->unique('active_name');

// SQLite / PostgreSQL branch
DB::statement('CREATE UNIQUE INDEX customers_active_name_unique ON customers (name) WHERE deleted_at IS NULL');
```

The MariaDB unique index is evaluated with the **column's** collation. The SQLite partial index is evaluated with
**BINARY** (byte-wise) comparison. Confirmed from the live schema:

```
SHOW CREATE TABLE customers;
-> `name` varchar(255) NOT NULL, ...
   `active_name` varchar(255) GENERATED ALWAYS AS (if(`deleted_at` is null,`name`,NULL)) STORED,
   UNIQUE KEY `customers_active_name_unique` (`active_name`)
   ... COLLATE=utf8mb4_unicode_ci
```

`utf8mb4_unicode_ci` is case-**insensitive**, so on MariaDB `'Acme'` and `'acme'` collide. On SQLite the partial
index would treat them as distinct.

Evidence:

- `database/migrations/2026_09_25_000000_create_customers_table.php:27-40`, `2026_09_28_173607_create_projects_table.php:26-40`,
  `2026_09_30_175706_create_epics_table.php:24-38` — identical dual-branch structure.
- The Form Request layer has the same asymmetry: `CustomerRequest.php:40-43` builds
  `Rule::unique(Customer::class)->ignore(...)->whereNull('deleted_at')`. `Rule::unique` resolves to the model's
  connection and therefore inherits `utf8mb4_unicode_ci` on MariaDB but BINARY semantics on SQLite.
- `tests/Unit/ModelSchemaParityTest.php` runs against the **configured** connection (`phpunit.xml` sets
  `DB_CONNECTION=mysql`), so the SQLite branch is never exercised by the suite. `ARCHITECTURE.md:50-51` claims
  "SQLite is used for isolated in-memory tests" — it is not; `phpunit.xml:22` pins `mysql`.

Impact:

Behaviour differs by environment in a way nothing tests: on MariaDB `acme` then `Acme` is rejected; on SQLite it is
accepted. The two branches also use different index names (`customers_active_name_unique` in both, but with
different columns), so `SHOW INDEX` output is not comparable across drivers.

Recommendation:

Pick one canonical rule and make both branches implement it. Simplest, no new infrastructure: normalise the
comparison in the application layer instead of relying on collation — e.g. store/compare a lower-cased name, or
add `'collation' => 'utf8mb4_unicode_ci'` semantics explicitly on the SQLite side via
`COLLATE NOCASE` in the partial index:

```sql
CREATE UNIQUE INDEX customers_active_name_unique ON customers (name COLLATE NOCASE) WHERE deleted_at IS NULL;
```

Also correct `ARCHITECTURE.md:50-51`, which states SQLite is used for isolated tests when `phpunit.xml:22` pins MySQL.

## DB-002 — `active_name` is a STORED generated column duplicated into the row

Severity: LOW
Category: Database / Schema
File: database/migrations/2026_09_25_000000_create_customers_table.php
Line: 37
Confidence: HIGH

Problem:

`active_name` is declared `STORED`, so MySQL/MariaDB materialises the value on disk for every row rather than
computing it on read.

Evidence:

```
SHOW CREATE TABLE customers;
-> `active_name` varchar(255) GENERATED ALWAYS AS (if(`deleted_at` is null,`name`,NULL)) STORED,
```

Impact:

Every row of `customers`, `projects` and `epics` carries an extra up-to-255-character column. On `epics` the
combined unique key is `(project_id, active_name)` — an 8-byte bigint plus a utf8mb4 varchar(255), i.e. up to
1028 bytes, which is comfortably inside InnoDB's 3072-byte limit for `DYNAMIC` row format, so there is no
"key too long" failure today. The cost is storage and write amplification on a table that will hold very few rows
in this application.

Recommendation:

No action. This is a correct, well-known MySQL pattern for "unique among non-deleted", it is enforced by the
database rather than by application code, and `UniqueConstraintViolation` exists precisely to absorb the race.
Flagging only so the `STORED` choice is understood as deliberate rather than accidental.

## DB-003 — `down()` for the `active` migration drops columns without restoring prior state safely on all drivers

Severity: LOW
Category: Database / Migration rollback
File: database/migrations/2026_10_02_180040_add_active_to_domain_and_users_tables.php
Line: 32-38
Confidence: HIGH

Problem:

The rollback drops `active` from four tables with no data preservation and no guard. Because the column is
`NOT NULL DEFAULT 1`, a `down()`/`up()` cycle resets every row's deactivation state to active — silently
reactivating every previously deactivated customer, project, epic and user.

Evidence:

```php
// migration:32-38
public function down(): void
{
    foreach ($this->tables as $tableName) {
        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropColumn('active');
        });
    }
}
```

There is no `down()` in the three create-table migrations that would restore `active_name` cleanly — they call
`Schema::dropIfExists(...)` on the whole table, which is fine.

Impact:

Only bites on rollback of an already-applied migration in an environment with real data. Standard for this kind of
feature migration and not unusual, but it is a data-loss path with no warning.

Recommendation:

Acceptable. If you want a guard, the cheapest form is a comment in `down()` stating that `active` state is lost.
Do not build a reversible migration framework for one column.

## DB-004 — Trash and inactive lists have no supporting index on `deleted_at` or `active`

Severity: LOW
Category: Database / Indexes
File: database/migrations/2026_09_25_000000_create_customers_table.php
Line: 14-19
Confidence: HIGH

Problem:

`customers`, `projects` and `epics` carry only `PRIMARY KEY` plus the generated-column unique index. There is no
index on `deleted_at`, `active`, or on the ordering columns used by the list queries.

Evidence — actual indexes on the live database:

```
SHOW INDEX FROM customers;
  PRIMARY            (id)
  customers_active_name_unique (active_name)

SHOW INDEX FROM epics;
  PRIMARY            (id)
  epics_project_id_active_name_unique (project_id, active_name)

EXPLAIN SELECT epics.* FROM epics
  inner join projects as epic_projects on epic_projects.id = epics.project_id
  inner join customers as epic_customers on epic_customers.id = epic_projects.customer_id
  WHERE epics.deleted_at is null and epics.active = 1
  ORDER BY epics.start_date IS NULL, epics.start_date LIMIT 5;
-> table: epics, type: ALL, possible_keys: epics_project_id_active_name_unique, key: NULL,
   rows: 1, Extra: "Using where; Using filesort"
```

Impact:

Full scan plus filesort on the primary list query. Row counts today are trivial
(`SELECT COUNT(*) FROM customers` → 5, `projects` → 2, `epics` → 1), so this costs nothing now. It becomes
material only if these tables grow into the tens of thousands, which is not this application's shape.

Recommendation:

**No action now.** Recorded so the EXPLAIN evidence exists if the data ever grows. Adding speculative indexes to
five-row tables would violate the project's "do not optimize speculatively" rule.

## DB-005 — `epic_comments` has no index covering the `(epic_id, created_at DESC)` order used by the comments query

Severity: LOW
Category: Database / Indexes
File: database/migrations/2026_09_30_175707_create_epic_comments_table.php
Line: 12-19
Confidence: HIGH

Problem:

The comments query filters by `epic_id` and orders by `created_at DESC, id DESC`. Only the FK index on `epic_id`
exists.

Evidence:

```sql
EXPLAIN SELECT * FROM epic_comments WHERE epic_id in (1,2,3) ORDER BY created_at DESC, id DESC;
-> type: range, key: epic_comments_epic_id_foreign, Extra: "Using index condition; Using filesort"
```

Index list on `epic_comments`: `PRIMARY`, `epic_comments_epic_id_foreign`, `epic_comments_user_id_foreign`. No
`(epic_id, created_at)` composite.

Impact:

Filesort over at most `RECENT_COMMENTS_LIMIT` (20) rows per epic — negligible. The index would only matter if a
single epic accumulated thousands of comments.

Recommendation:

No action. If a single epic ever exceeds a few thousand comments, add
`$table->index(['epic_id', 'created_at'])`.

## DB-006 — `EpicListQuery` inner-joins `projects` and `customers`, which silently drops epics whose parent is soft-deleted

Severity: MEDIUM
Category: Database / Query correctness
File: app/Queries/Epics/EpicListQuery.php
Line: 88-96
Confidence: MEDIUM

Problem:

`withProjectAndCustomer()` builds `join('projects as epic_projects', ...)` and `join('customers as epic_customers', ...)`.
Laravel's `SoftDeletes` global scope is an Eloquent-level concern and **does not** apply to a raw `join`, so
soft-deleted parents are *not* filtered out — the join still matches. That is the intended behaviour and is
consistent with `Epic::project()` using `->withTrashed()` (`app/Models/Epic.php:49`) and `Project::customer()` using
`->withTrashed()` (`app/Models/Project.php:50`).

The real risk is the opposite direction: because the joins are `INNER`, an epic whose `project_id` points at a row
that no longer exists would vanish from the list. That cannot happen — `epics_project_id_foreign` is
`FOREIGN KEY (project_id) REFERENCES projects (id)` with no `ON DELETE` clause, i.e. `RESTRICT`
(confirmed: `CONSTRAINT epics_project_id_foreign FOREIGN KEY (project_id) REFERENCES projects (id)`).
`projects_customer_id_foreign` is likewise `RESTRICT`.

Evidence:

```php
// app/Queries/Epics/EpicListQuery.php:88-96
return $query
    ->with('project.customer')
    ->join('projects as epic_projects', 'epic_projects.id', '=', 'epics.project_id')
    ->join('customers as epic_customers', 'epic_customers.id', '=', 'epic_projects.customer_id')
    ->select('epics.*')
    ->withCount('comments');
```

Same structure in `app/Queries/Projects/ProjectListQuery.php:76-82`.

Impact:

No defect today. The finding is that the `INNER` join is load-bearing on the FK's `RESTRICT` action, and nothing
documents that. If someone later relaxes the FK to `ON DELETE SET NULL` or `CASCADE`, epics would silently disappear
from every list — including the active list and the trash list — with no test failing.

Recommendation:

One-line documentation, no code change. Add to `ARCHITECTURE.md` next to the existing "Important constraints"
paragraph: the epics/projects list queries inner-join their parents, so the parent FKs must stay `RESTRICT`.

## DB-007 — `projects.customer_id` uses `restrictOnDelete` while the application soft-deletes; force-delete is guarded in the controller, not the schema

Severity: MEDIUM
Category: Database / Data integrity
File: database/migrations/2026_09_28_173607_create_projects_table.php
Line: 18
Confidence: HIGH

Problem:

`$table->foreignId('customer_id')->constrained()->restrictOnDelete();` protects the database against hard-deleting a
customer that still has projects. But the application's "can this be deleted?" rule is implemented only in PHP:

```php
// app/Http/Controllers/CustomerController.php:86-91
if ($customer->projects()->withTrashed()->exists()) {
    return to_route('customers.index')->with('error', __('Customer cannot be deleted while it has projects.'));
}
$customer->delete();
```

`delete()` is a soft delete, so the `RESTRICT` constraint never fires on this path. It only fires if the FK is
somehow violated, which the controller prevents by check-then-act.

Evidence:

- `SHOW CREATE TABLE projects` → `CONSTRAINT projects_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES customers (id)` (no cascade action = RESTRICT).
- `SHOW CREATE TABLE epic_comments` → `epic_id` has `ON DELETE CASCADE`, `user_id` has `ON DELETE SET NULL`.
- The check-then-act pattern is at `CustomerController.php:86`, `ProjectController.php:74`,
  `CustomerTrashController.php:61`, `ProjectTrashController.php:62`.

Impact:

Two consequences, both bounded:

1. **Force-delete of a trashed customer with trashed projects.** `CustomerTrashController::destroy` re-checks
   `projects()->withTrashed()->exists()` (line 61), so it is covered by the same guard. Covered.
2. **The comment cascade is genuinely correct.** `epic_comments.epic_id` is `ON DELETE CASCADE`, so permanently
   deleting an epic removes its comments — which is exactly what `ARCHITECTURE.md:69-70` promises. Covered.

So no data-integrity defect exists. The finding is that the invariant lives in application code only, and the
race window between `exists()` and `delete()` is the real (if narrow) risk — see CONC-002 in the concurrency report.

Recommendation:

No change to the schema. Optionally wrap the check-then-act in `DB::transaction()` in the two force-delete
controllers so the guard and the delete are atomic. That is a one-line change per controller and is the only
place where the database-level invariant and the application-level rule can diverge.

## Notes / not findings

- **`unique_name` is enforced in the database, not only in the Form Request.** Confirmed by
  `SHOW CREATE TABLE customers/projects/epics`. This is the single best decision in the schema: it makes
  `UniqueConstraintViolation::rethrowAsValidationError()` (`app/Support/Database/UniqueConstraintViolation.php:30`)
  the correct and complete answer to a validation race. Not a finding.
- **Generated column vs. application uniqueness** were compared and the generated column is the right choice here —
  it is enforced atomically inside the INSERT and cannot be bypassed. See DB-002 for the storage caveat only.
- **`epic_comments.user_id` nullable + `ON DELETE SET NULL`** matches `ARCHITECTURE.md:70` ("keep a null author when
  the user is deleted"), and `EpicCommentTransformer` renders `__('Deleted user')` for the null case
  (`EpicListTransformer.php:58`). Correct. Not a finding.
- **Migration `2026_10_02_180040_add_active_to_domain_and_users_tables.php`** adds `active` to four tables in one
  migration with a private `$tables` property. Adding a boolean with `NOT NULL DEFAULT 1` to existing tables is
  lock-light on MariaDB 11.7 and does not rewrite the table when the default is constant. Not a finding.
- **No destructive migration.** No `dropColumn` on data-bearing columns except the `active` rollback discussed in
  DB-003, and no `->change()` calls that rewrite large tables.
- **`tests/Unit/ModelSchemaParityTest.php`** is a genuinely good guard: it asserts every non-system column is
  `#[Fillable]` and every cast exists in the schema. It is why adding a column without updating the model fails
  the suite. Not a finding.