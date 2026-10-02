# Clean Code Review — 2026-10-02

## Findings

### CLEAN-001 — One shared error-conversion helper exists but two call sites bypass it

Severity: LOW
Category: Clean code / Consistency
File: app/Http/Controllers/CustomerTrashController.php,
      app/Http/Controllers/ProjectTrashController.php,
      app/Http/Controllers/EpicTrashController.php
Line: 41-47 / 42-48 / 47-53
Confidence: HIGH

Problem: `App\Support\Database\UniqueConstraintViolation` already offers
`rethrowAsValidationError(QueryException $e, string $field = 'name'): never`, and all six
`store()`/`update()` methods use it. The three `restore()` methods instead inline the older form:

```php
try {
    $project->restore();
} catch (QueryException $exception) {
    if (! UniqueConstraintViolation::causedBy($exception)) {
        throw $exception;
    }

    return $this->restoreConflictResponse();
}
```

That block is byte-identical in all three trash controllers.

Impact: two idioms for the same decision. A change to the detection logic has to be reviewed in
four places, and a reader has to confirm the inline version is still equivalent.
Recommendation: keep the inline form (it returns a redirect instead of throwing, so the helper
does not fit) but extract the shared body onto `ListQueryBase`-style placement — the cheapest
option is a single `restoreConflictResponse()`-like static helper next to
`UniqueConstraintViolation`, e.g. `UniqueConstraintViolation::wasCausedBy()`. Purely cosmetic at
this size; acceptable to leave as is if you prefer explicit code over indirection.
**Do not** introduce a base trash controller.

## Verified clean (no findings)

- No repositories, no interfaces, no DTOs, no service layer, no domain layer — matching
  `.github/docs/architecture/ARCHITECTURE.md:118-124`.
- No duplicated business logic between the three resources beyond the structural mirroring that is
  the documented pattern (`.github/instructions/http.instructions.md`).
- Explicit types on every method signature, constructor promotion where applicable, curly braces
  everywhere, PHPDoc array shapes instead of inline comments — matches `AGENTS.md`.
- Enum keys, factories and `data-test` conventions are followed consistently.
- `Pint --test` passes on all 128 files.
- `PHPStan` level 9 passes on `app/`, `bootstrap/`, `config/`, `database/`, `resources/`,
  `routes/`, `tests/`.
- No dead code beyond the orphan translation key in `maintainability.md`.