# Database Review — 2026-10-02

READ-ONLY. Live MariaDB 11.7 inspected with `SHOW CREATE TABLE` and `EXPLAIN` against
`laravel_test` / `laravel`. No DDL and no DML were executed.

## Schema as actually created (verbatim from `SHOW CREATE TABLE`)

```sql
CREATE TABLE `customers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `active_name` varchar(255) GENERATED ALWAYS AS (if(`deleted_at` is null,`name`,NULL)) STORED,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `customers_active_name_unique` (`active_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
```

```sql
CREATE TABLE `projects` ( ... `active_name` ... , `active` ...,
  PRIMARY KEY (`id`),
  UNIQUE KEY `projects_active_name_unique` (`active_name`),
  KEY `projects_customer_id_foreign` (`customer_id`),
  CONSTRAINT `projects_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`)
)
```

```sql
CREATE TABLE `epics` ( ... , PRIMARY KEY (`id`),
  UNIQUE KEY `epics_project_id_active_name_unique` (`project_id`,`active_name`),
  CONSTRAINT `epics_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`)
)
```

```sql
CREATE TABLE `epic_comments` ( ...,
  KEY `epic_comments_epic_id_foreign` (`epic_id`),
  KEY `epic_comments_user_id_foreign` (`user_id`),
  CONSTRAINT `epic_comments_epic_id_foreign` FOREIGN KEY (`epic_id`) REFERENCES `epics` (`id`) ON DELETE CASCADE,
  CONSTRAINT `epic_comments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
)
```

## Findings

### DB-001 — No index supports any list filter or sort column

Severity: LOW
Category: Database / Performance
File: database/migrations/2026_09_25_000000_create_customers_table.php,
      database/migrations/2026_09_28_173607_create_projects_table.php,
      database/migrations/2026_09_30_175706_create_epics_table.php
Line: 21-22 / 28-29 / 27-28 (only the generated-column unique index is added)
Confidence: HIGH

Problem: the only secondary indexes are the generated-column unique indexes and the implicit FK
indexes. Every list query filters or orders by `active`, `deleted_at`, `name`, `start_date`,
`end_date` or `updated_at`, none of which is indexed.

Evidence:

```console
$ EXPLAIN SELECT * FROM projects p JOIN customers c ON c.id=p.customer_id
          WHERE p.deleted_at IS NULL AND p.active=1 ORDER BY p.start_date, p.end_date, c.name, p.name, p.id LIMIT 5;
id  select_type: SIMPLE
table: p
 type: ALL        <- full scan
possible_keys: projects_customer_id_foreign
key:  NULL
Extra: Using where; Using temporary; Using filesort
```

Impact: irrelevant at the current size (5 customers / 67 projects / 35 epics — read from
`AUTO_INCREMENT`). Becomes a measurable regression somewhere past ~50k rows, at which point every
list page is a full scan plus a filesort, and the `LIKE '%term%'` search in
`app/Queries/ListQueryBase.php:30-34` can never use an index regardless.

Recommendation: do **not** add indexes speculatively now. Record this as the trigger to revisit
when the domain tables pass a few tens of thousands of rows; at that point a composite index per
list query (for example `(deleted_at, active, start_date)` for the project active list) is the
right fix. Cost is low when the table shape is still small and the list queries are stable.

### DB-002 — The soft-delete uniqueness trick relies on the table collation and is only exercised on MySQL

Severity: LOW
Category: Database / Portability
File: database/migrations/2026_09_25_000000_create_customers_table.php
Line: 15-19 (and the equivalent block in the projects/epics migrations)
Confidence: HIGH

Problem: the MySQL/MariaDB branch adds a `STORED` generated column
`IF(deleted_at IS NULL, name, NULL)` with a unique index. The `sqlite`/`pgsql` branch instead
issues a partial unique index. Two consequences were verified:

1. Uniqueness is **case- and accent-insensitive** because the generated column inherits the table
   collation `utf8mb4_unicode_ci`. Verified on the live server:
   `SELECT 'ACME' = 'acme' COLLATE utf8mb4_unicode_ci` → `1`.
   This is **consistent** with `Rule::unique(Customer::class)`, which also compares through the same
   collation, so validation and the database never disagree. **Not a defect** — recorded so nobody
   "fixes" the collation later and silently changes behaviour.
2. The `sqlite`/`pgsql` branch is never executed by the test suite: `phpunit.xml:19` pins
   `DB_CONNECTION=mysql`. Running the suite on SQLite fails (see `testing.md`, TEST-001), so the
   partial-index SQL is unverified by CI.

Impact: the non-MySQL branch is untested code. If a developer ever switches the suite to SQLite
(as `ARCHITECTURE.md:50-51` claims), the LIKE-escaping code — not the index — is the first thing
that breaks.

Recommendation: either drop the `sqlite`/`pgsql` branch and state MySQL-only in `ARCHITECTURE.md`,
or add a cheap job that migrates on SQLite to keep the branch honest. Do not keep documenting
SQLite as the test database while the suite runs on MySQL.

## Verified correct (no findings)

- **Generated-column uniqueness works.** Confirmed by the test-suite failure
  `SQLSTATE[23000] ... 1062 Duplicate entry 'Active record' for key 'customers_active_name_unique'`,
  which proves the index rejects a second non-deleted duplicate.
- **`UniqueConstraintViolation::causedBy()`** handles `23505` (Postgres `unique_violation`) and
  `23000` + errno `1062` (MySQL `Duplicate entry`) / `19` (SQLite `UNIQUE constraint failed`).
  Consistent with what `QueryException::$errorInfo` carries on each driver.
- **Foreign-key `onDelete` semantics match the documented rules.** `epic_comments` →
  `ON DELETE CASCADE` satisfies "epic comments are removed when their epic is permanently deleted";
  `epic_comments.user_id` → `ON DELETE SET NULL` satisfies "comments keep a null author when the
  user is deleted". Both asserted in `tests/Feature/Epics/EpicCommentTest.php:160+`.
- **`restrictOnDelete` on `projects.customer_id` and `epics.project_id` is correct** for *hard*
  deletes. Note it cannot protect against the *soft* delete race in `bug/correctness` — see the
  consolidated report.
- **`epics` has no separate index on `project_id`** because the composite unique index
  `(project_id, active_name)` satisfies InnoDB's FK requirement on the leftmost prefix.
- **`paginate()` totals are correct despite the joins.** `ProjectListQuery::withCustomer()` and
  `EpicListQuery::withProjectAndCustomer()` join many-to-one tables only, so no row multiplication
  inflates the count.
- **`withCount('comments')` + `with(['comments' => …limit(20)])` behaves as documented.**
  `app/Queries/Epics/EpicListQuery.php:17` claims "the most recent comments … in each list row".
  Verified on an isolated review database with three epics holding 30 comments each: every epic
  loaded exactly 20, sum 60, and the emitted SQL is a single window-function query
  (`row_number() over (partition by epic_id order by created_at desc, id desc)`), not N queries.
  **Hypothesis of a global limit refuted.**
- **`add_active_to_domain_and_users_table` adds `active boolean default true` to
  `customers/projects/epics/users`** and every model mirrors it with
  `protected $attributes = ['active' => true]` plus a `boolean` cast. `down()` drops the columns.