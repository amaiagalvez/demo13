---
applyTo: "app/Http/**,app/Policies/**,app/Queries/**,app/Transformers/**"
---

# HTTP / domain rules

- Canonical resource: `CustomerController` + `CustomerRequest` + `CustomerPolicy` + `Queries\Customers\CustomerListQuery` + `CustomerListTransformer`. Mirror it (never reinvent it) when adding a resource.
- Store/update: catch `QueryException` and call `UniqueConstraintViolation::rethrowAsValidationError()`. Restore flows return their own conflict response instead.
- `CustomerController::store` also returns JSON `201`/`409` responses for inline customer creation from the project selector; `CustomerCrudTest` fixes that contract.
- Deleting or force-deleting a parent is blocked if children exist, including trashed ones (`withTrashed()->exists()`). Customer→Project and Project→Epic work the same way.
- Form Requests authorize through the resource policy. Policies currently allow every verified user; this is documented in ARCHITECTURE.md, do not add roles unasked.
- List querying lives in `app/Queries`, row shaping in `app/Transformers`; keep controllers thin.
- A `max` rule never carries a literal number: it reads `config('validation.max_length.string')` for a varchar-backed field or `config('validation.max_length.longtext')` for a `longText` one. `ValidationCoverageTest::test_string_length_limits_come_from_the_config` fails on a literal or on a key that does not resolve.
