# Concurrency Specialist Review — 2026-10-02

Snapshot: HEAD `781ccbb` + working tree. Engine MariaDB 11.7.2. Isolation level: server default (REPEATABLE READ).

Existing race coverage found (this is a strength of the codebase, and it changes what is worth reporting):

| Resource | store | update | restore |
|---|---|---|---|
| Customer | `CustomerCrudTest.php:159` | `CustomerCrudTest.php:176` | `CustomerTrashTest.php:108` |
| Project | `ProjectCrudTest.php:114` | `ProjectCrudTest.php:136` | `ProjectTrashTest.php:145` |
| Epic | `EpicCrudTest.php:175` | `EpicCrudTest.php:195` | `EpicTrashTest.php:159` |

All three resources have the duplicate-name race covered for all three write paths, using `DB::listen` to inject a
competing INSERT between validation and the model's write. The `catch (QueryException)` branch is genuinely tested
everywhere.

## CONC-001 — `deactivate` / `reactivate` are read-modify-write with no concurrency guard

Severity: LOW
Category: Concurrency / Lost update
File: app/Http/Controllers/CustomerInactiveController.php
Line: 28-35
Confidence: HIGH

Problem:

```php
public function deactivate(Customer $customer): RedirectResponse
{
    $this->authorize('deactivate', $customer);
    $customer->active = false;
    $customer->save();

    return to_route('customers.index')->with('status', __('Record deactivated successfully.'));
}
```

`$customer` was hydrated by route-model-binding at the start of the request. `save()` issues
`UPDATE customers SET active = ?, updated_at = ? WHERE id = ?` with **no** `WHERE active = ...` predicate and no
lock, so a concurrent `reactivate` (or a concurrent unrelated edit) is silently overwritten by last-writer-wins.

Evidence:

- `CustomerInactiveController.php:31-32`, `ProjectInactiveController.php:32-33`, `EpicInactiveController.php:32-33`
  (deactivate) and the mirrored `reactivate` methods at lines 37-44 / 39-46 / 39-46.
- The two endpoints are separate PATCH routes (`routes/web.php:19-20,29-30,39-40,49-50`), so two users can hit
  them simultaneously on the same record.

Impact:

Bounded and benign for this application. `active` is a boolean with only two states, both reachable from the UI, and
the outcome of a lost update is "the record ends in one of the two valid states" — never corrupted data. The visible
symptom would be a user clicking "Deactivate" and seeing the record still listed as active after a concurrent
"Reactivate". No test covers it.

Recommendation:

Make the write atomic so the outcome is deterministic without adding locks:

```php
Customer::whereKey($customer->getKey())->update(['active' => false]);
```

`QueryBuilder::update()` bypasses model events but this model has none, and it removes the stale-hydration window
entirely. Apply to all six `deactivate`/`reactivate` methods. This is a mechanical change with no new abstractions.

## CONC-002 — "Cannot delete a parent that has children" is check-then-act, not atomic

Severity: LOW
Category: Concurrency / Race
File: app/Http/Controllers/CustomerController.php
Line: 86-91
Confidence: HIGH

Problem:

```php
if ($customer->projects()->withTrashed()->exists()) {
    return to_route('customers.index')->with('error', __('Customer cannot be deleted while it has projects.'));
}
$customer->delete();
```

Between the `EXISTS` and the `DELETE`, another request can insert a project for that customer. The soft delete then
succeeds and the invariant stated in `ARCHITECTURE.md:58,65` ("Customers with projects cannot be moved to the trash")
is violated.

Evidence:

- `CustomerController.php:86-91` (soft delete), `ProjectController.php:74-79` (soft delete),
  `CustomerTrashController.php:61-66` (force delete), `ProjectTrashController.php:62-67` (force delete).
- The DB cannot save the soft-delete path: `delete()` is an `UPDATE`, and `projects_customer_id_foreign` is
  `RESTRICT` on *delete*, which an `UPDATE` never triggers (confirmed via `SHOW CREATE TABLE projects`).
- The force-delete path *is* protected by the FK, because `Project::forceDelete()` issues a real `DELETE` that
  `RESTRICT` will reject — so the worst outcome there is an unhandled `QueryException` (HTTP 500) rather than data loss.

Impact:

Requires two concurrent requests hitting the same customer, which for a small internal tool is unlikely. The
invariant that breaks is exactly one the architecture document calls out as important.

Recommendation:

Wrap the check and the delete in a transaction so the pair is atomic:

```php
DB::transaction(function () use ($customer): void {
    if ($customer->projects()->withTrashed()->lockForUpdate()->exists()) {
        throw new CustomerHasProjects;   // or return the conflict response from inside the closure
    }
    $customer->delete();
});
```

Given the project's explicit "no repositories, no service layers" rule, the simplest safe version is a plain
`DB::transaction()` inside the four controllers, returning the redirect from inside the closure. Do **not**
introduce exception classes or a service layer for this.

## CONC-003 — `UniqueConstraintViolation::causedBy()` cannot distinguish which unique index was violated

Severity: LOW
Category: Concurrency / Correctness
File: app/Support/Database/UniqueConstraintViolation.php
Line: 10-21
Confidence: HIGH

Problem:

```php
public static function causedBy(QueryException $exception): bool
{
    $errorInfo = $exception->errorInfo;
    $sqlState = (string) ($errorInfo[0] ?? $exception->getCode());
    if ($sqlState === '23505') { return true; }               // PostgreSQL unique_violation
    return $sqlState === '23000'
        && in_array((int) ($errorInfo[1] ?? 0), [19, 1062], true);  // SQLite 19, MySQL 1062
}
```

The check is "was this *any* unique-constraint violation", not "was it the `active_name` index". On these three tables
the only non-primary unique indexes are the `active_name` ones, so in practice the mapping is correct. But the
error-code 19 is SQLite's generic `SQLITE_CONSTRAINT`, which is also raised for `NOT NULL` and `FOREIGN KEY`
violations — not only for uniqueness.

Evidence:

- `app/Support/Database/UniqueConstraintViolation.php:19-20`.
- `tests/Unit/Support/Database/UniqueConstraintViolationTest.php` exists and covers this helper.
- The unique indexes in play (verified via `SHOW CREATE TABLE`): `customers_active_name_unique`,
  `projects_active_name_unique`, `epics_project_id_active_name_unique`. No other candidate unique index exists on
  these tables, so a `1062` on `customers` can only be the name index.

Impact:

On MariaDB (the production engine) the mapping is exact: `1062` is `ER_DUP_ENTRY` and `23000` is
`SQLSTATE INTEGRITY_CONSTRAINT_VIOLATION`. The only false-positive scenario would be a SQLite run where a `NOT NULL`
violation on one of these tables surfaces as a "name is already taken" validation message instead of a 500. That is
a development-only concern and is misleading rather than dangerous.

Recommendation:

No change for the current schema. If SQLite parity matters (see DB-001), tighten the SQLite branch to check the
message text as well, e.g. `str_contains($errorInfo[2] ?? '', 'UNIQUE constraint failed')`.

## CONC-004 — The database-level unique index makes the whole Form Request race a non-issue

Severity: INFO (positive finding — the correct design, recorded so it is not "optimised" away)
Category: Concurrency
File: database/migrations/2026_09_25_000000_create_customers_table.php
Line: 37-38
Confidence: HIGH

Problem:

None. This is the strongest concurrency decision in the codebase.

Evidence:

```php
$table->string('active_name')->nullable()->storedAs('IF(deleted_at IS NULL, name, NULL)');
$table->unique('active_name');
```

Verified live: `UNIQUE KEY customers_active_name_unique (active_name)` where `active_name` is
`GENERATED ALWAYS AS (if(deleted_at is null, name, NULL)) STORED`. The uniqueness is evaluated **inside the INSERT**,
atomically, so no amount of concurrency can produce two non-deleted rows with the same name. The Form Request check
is therefore only a UX affordance; correctness does not depend on it.

Impact:

None — this is why `CONC-003` and the TOCTOU findings are all LOW rather than HIGH.

Recommendation:

Keep. Never replace the generated column with a plain unique index on `name` (that would forbid reusing the name of
a soft-deleted record, which `ARCHITECTURE.md:62-63` explicitly requires) and never remove it in favour of
application-only validation.

## CONC-005 — No transactions are used anywhere in the write paths

Severity: INFO
Category: Concurrency
File: app/ (all controllers)
Line: —
Confidence: HIGH

Problem:

None today. Each write action performs exactly one `INSERT`/`UPDATE`/`DELETE`; there is no multi-step business
transaction to make atomic.

Evidence:

- `EpicCommentController::store()` makes one comment and calls `save()` once — one `INSERT`.
- Every `store`/`update`/`destroy`/`restore`/`deactivate`/`reactivate` method issues exactly one write.
- No `DB::transaction`, `lockForUpdate`, or `sharedLock` appears anywhere in `app/`
  (`tests/Unit/ArchitectureTest.php:67` additionally forbids `DB::` in controllers outright).

Impact:

None. `ARCHITECTURE.md:72-73` states this accurately: "no multi-step business transaction or queued write flow
exists currently."

Recommendation:

No change now. If a future feature adds a multi-step write, note that `ArchitectureTest` line 67 forbids `DB::` in
controllers — that guard will need widening to `DB::transaction(` when the time comes. Flagging so the test is
updated deliberately rather than worked around.

## Notes / not findings (rejected after challenge)

- **Deadlocks / lock ordering.** No application code takes explicit locks, so there are no lock-ordering cycles.
  Rejected.
- **Queue double-processing.** No jobs exist (`ARCHITECTURE.md:77-81`); the database queue driver is configured but
  unused. Rejected.
- **Optimistic-locking on `updated_at`.** No `optimisticLock()` on any model. Adding it would surface conflicts to
  users in a single-user-per-record tool where conflicts are not meaningful. **Rejected as over-engineering.**
- **`Project::customer()` / `Epic::project()` using `withTrashed()`.** Not a concurrency concern; this is a
  deliberate display decision documented in `ARCHITECTURE.md:69`. Rejected.
- **`CustomerInactiveController::deactivate` needing to cascade to children.** It must not cascade
  (`ARCHITECTURE.md:57`: "Deactivation does not cascade to children"). Correct as written. Rejected.