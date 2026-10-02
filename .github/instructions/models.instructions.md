---
applyTo: "app/Models/**"
---
# Model rules
- Models declare `#[Fillable]`, `#[UsePolicy]`, `casts()` and relationships only. Validation lives in Form Requests, authorization in policies, list querying in `app/Queries`.
- Soft deletes per resource; names are unique among active records, enforced in the database (`App\Support\Database\UniqueConstraintViolation` absorbs the race).
- Relationships use explicit `belongsTo`/`hasMany` return types; trashed children count where deletion is blocked (`withTrashed()`).
- A new column needs three things together: migration, `#[Fillable]`/`casts()` entry and Request rules — architecture tests enforce it.
