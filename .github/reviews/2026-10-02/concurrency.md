# Concurrency Review — 2026-10-02

READ-ONLY. No jobs, no queues in use, no Redis, no Horizon. The database queue driver is
configured but no `ShouldQueue` class exists in `app/`.

## Findings

### CONC-001 — Check-then-act on parent deletion can break the documented "children ⇒ no trash" invariant

Severity: MEDIUM
Category: Concurrency / Data integrity
File: app/Http/Controllers/CustomerController.php, app/Http/Controllers/ProjectController.php,
      app/Http/Controllers/CustomerTrashController.php, app/Http/Controllers/ProjectTrashController.php
Line: 86-91 / 74-79 / 61-66 / 62-67
Confidence: MEDIUM

Problem: the guard is a `SELECT EXISTS` followed by a `DELETE`, with no transaction and no row
lock. The foreign keys are `restrictOnDelete`, which only protects **hard** deletes — a soft
delete does not touch the FK at all.

Evidence:

```php
// app/Http/Controllers/CustomerController.php:86-91
if ($customer->projects()->withTrashed()->exists()) {
    return to_route('customers.index')
        ->with('error', __('Customer cannot be deleted while it has projects.'));
}

$customer->delete();
```

Reachable interleaving:

1. Request A (delete customer) — `exists()` returns false.
2. Request B (create project) — `ProjectRequest` validates
   `Rule::exists(Customer::class, 'id')->whereNull('deleted_at')` (still not deleted ⇒ passes) and
   inserts a project.
3. Request A — `delete()` soft-deletes the customer.

Result: a project attached to a soft-deleted customer, contradicting
`.github/docs/architecture/ARCHITECTURE.md:65` ("Customers with projects cannot be moved to the
trash or permanently deleted"). The UI tolerates it (`withTrashed()` on the relations and joins),
so there is no crash — but the invariant is broken and recovery is manual.

Impact: narrow window (requires two requests to interleave in a few milliseconds), no data loss,
but a documented business rule is not actually enforced.

Recommendation (minimal, no new layers): wrap the guard and the write in `DB::transaction()` and
re-check with `lockForUpdate()` on the parent row inside the transaction, e.g.

```php
DB::transaction(function () use ($customer): void {
    $locked = Customer::withTrashed()->lockForUpdate()->findOrFail($customer->id);

    if ($locked->projects()->withTrashed()->exists()) {
        throw new CustomerHasProjectsException;   // or return the flash as today
    }

    $locked->delete();
});
```

Add one test per resource that asserts the guard still rejects the request when a child appears
between the check and the write (the pattern already exists in
`tests/Feature/Projects/ProjectTrashTest.php:139-171`).

### CONC-002 — `deactivate` / `reactivate` are unguarded read-modify-write

Severity: LOW
Category: Concurrency
File: app/Http/Controllers/CustomerInactiveController.php (28-43),
      app/Http/Controllers/ProjectInactiveController.php (29-45),
      app/Http/Controllers/EpicInactiveController.php (29-45)
Confidence: MEDIUM

Problem: `$model->active = false; $model->save();` — the current value is never read, so two
concurrent requests on the same record can both report success while only the last write survives.

Impact: cosmetic — the user may see "Record deactivated successfully." for an action that a
concurrent request immediately reverted. No invariant depends on `active`, and the operation is
idempotent.
Recommendation: optional. `->update(['active' => false])` is the same race; a real fix needs
`lockForUpdate()` or a version column, which is not worth it for a boolean display flag. Recorded
so the choice is deliberate rather than accidental.

## Races that are already DB-enforced (NOT bugs)

- **Concurrent duplicate-name creation.** `CustomerRequest`/`ProjectRequest`/`EpicRequest` use
  `Rule::unique(...)->whereNull('deleted_at')` *and* the generated-column unique index
  (`customers_active_name_unique`, `projects_active_name_unique`,
  `epics_project_id_active_name_unique`). The DB is the final arbiter.
- **The loser of that race is handled cleanly.** `UniqueConstraintViolation::rethrowAsValidationError()`
  converts the `QueryException` into a field validation error, so the user gets
  "The name has already been taken." instead of a 500. Same helper is used by all three resources.
- **Concurrent restore of two records with the same name.** The `exists()` pre-check in
  `*TrashController::restore` is only a fast path; the unique index catches the race and the
  `catch (QueryException)` branch returns the conflict flash. There are explicit tests for exactly
  this interleaving in `tests/Feature/Projects/ProjectTrashTest.php:139-171` and
  `tests/Feature/Epics/EpicTrashTest.php` (`test_restore_returns_conflict_when_name_becomes_active_after_precheck`).
  Good coverage.
- **Concurrent force-delete of the same customer/project.** `forceDelete()` on an already-deleted
  row 404s via `onlyTrashed()->findOrFail()`. Harmless.
- **Concurrent activate/deactivate of a user.** `FortifyServiceProvider::configureActiveUsers()`
  re-checks `active` on every `Login` event, and `EnsureUserIsActive` re-checks on every request,
  so the window between the check and the session use is at most one request. Acceptable.

## Not applicable

- No jobs, no listeners, no scheduled tasks, no queued writes — nothing to make idempotent.
- No shared mutable counters. `withCount('comments')` is computed per request.
- No `SELECT ... FOR UPDATE` gaps in the name-conflict store() flow: the generated-column index
  covers it.