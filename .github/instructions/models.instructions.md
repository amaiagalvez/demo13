---
applyTo: "app/Models/**"
---
# Model rules
- Models declare `#[Fillable]`, `#[UsePolicy]`, `casts()` and relationships only. Validation lives in Form Requests, authorization in policies, list querying in `app/Queries`. A derived read-only label is allowed when the same string is built for several surfaces (`Project::fullName()`).
- Soft deletes per resource; names are unique among active records, enforced in the database (`App\Support\Database\UniqueConstraintViolation` absorbs the race).
- A record is refused deletion while it has children, trashed ones included. The guard is a `deleting` hook registered in `AppServiceProvider::blockDeletionWithChildren()`, which locks the row inside its own transaction: it is what makes the delete safe, so a plain `$model->delete()` is protected too and callers must not lock the row again.
- Relationships use explicit `belongsTo`/`hasMany` return types; trashed children count where deletion is blocked (`withTrashed()`).
- A new column needs three things together: migration, `#[Fillable]`/`casts()` entry and Request rules — architecture tests enforce it. `epic_comments` is the known exception: it keeps `deleted_at` and `active` for a comment lifecycle that does not exist yet, so do not rely on them and do not copy them to a new table.
- A parent relation is never optional: `projects.customer_id` and `epics.project_id` are NOT NULL foreign keys with `restrictOnDelete`, and every `name` is NOT NULL. Read those relations directly — no `?->`, no `'—'` fallback — and never write a test that fabricates the missing state with `setRelation()`.
